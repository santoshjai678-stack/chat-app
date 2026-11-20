<x-app-layout>
<div class="flex h-screen">
    <!-- Users List -->
    <div class="w-1/4 bg-gray-200 p-4">
        <h2 class="font-bold mb-4">Users</h2>
        @foreach($users as $user)
            <div class="user cursor-pointer p-2 bg-white mb-2 flex items-center justify-between rounded transition-all hover:bg-gray-100"
                 onclick="openChat({{ $user->id }})"
                 data-user-id="{{ $user->id }}">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-green-500 hidden" id="active-{{ $user->id }}"></div>
                    <span>{{ $user->name }}</span>
                </div>
                <span class="text-xs bg-red-500 text-white rounded-full px-2 py-0.5 hidden"
                      id="badge-{{ $user->id }}">0</span>
            </div>
        @endforeach
    </div>

    <!-- Chat Box -->
    <div class="w-3/4 p-4 flex flex-col">
        <div id="chatHeader" class="bg-white border rounded p-3 mb-3 font-semibold text-gray-700 hidden">
            <span id="chatHeaderName"></span>
        </div>
        <div id="chatBox" class="h-96 overflow-y-scroll border p-3 mb-3"></div>
        <div id="typingIndicator" class="text-sm text-gray-600 h-5"></div>

        <div class="flex items-center gap-2 mb-2">
            <button type="button"
                    id="emojiToggle"
                    class="px-2 py-1 border rounded text-sm">
                😊
            </button>
            <div class="relative">
                <input type="file" id="fileInput" class="hidden">
                <button type="button"
                        id="fileButton"
                        class="px-2 py-1 border rounded text-sm">
                    📎 File
                </button>
                <span id="fileName" class="ml-2 text-xs text-gray-600"></span>
            </div>
        </div>

        <div id="emojiPicker"
             class="border p-2 mb-2 rounded bg-white shadow text-lg hidden">
            <span class="cursor-pointer mx-1">😀</span>
            <span class="cursor-pointer mx-1">😁</span>
            <span class="cursor-pointer mx-1">😂</span>
            <span class="cursor-pointer mx-1">😍</span>
            <span class="cursor-pointer mx-1">😎</span>
            <span class="cursor-pointer mx-1">👍</span>
            <span class="cursor-pointer mx-1">🙏</span>
            <span class="cursor-pointer mx-1">🎉</span>
        </div>

        <input type="hidden" id="to_user_id">
        <div class="flex items-center gap-2">
            <input type="text" id="message"
                   class="border rounded-full px-4 py-2 flex-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Type a message"
                   onkeypress="sendMessage(event)">
            <button type="button" 
                    id="sendButton"
                    onclick="sendCurrentMessage()"
                    class="bg-blue-500 hover:bg-blue-600 text-white rounded-full p-3 flex items-center justify-center transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    style="min-width: 48px; min-height: 48px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
        </div>
    </div>
</div>
<script>
window.addEventListener('load', () => {
    const authUserId = {{ auth()->id() }};
    let currentUser = null;
    let currentUserName = '';
    let typingTimeout = null;

    if (!window.Echo) {
        console.error('Echo is not ready yet.');
        return;
    }

    window.Echo.private(`chat.${authUserId}`)
        .listen('.message.sent', (e) => {
            console.log('Message received:', e);
            const fromUserId = e.message.from_user_id;

            incrementUnread(fromUserId);

            if (currentUser === null || currentUser !== fromUserId) {
                openChat(fromUserId);
            } else {
                loadMessages();
            }
        });

    window.Echo.private(`chat.${authUserId}`)
        .listen('.message.read', (e) => {
            // Reader read messages from auth user
            if (currentUser === e.readerId) {
                updateMessageStatuses();
            }
        })
        .listen('.typing', (e) => {
            if (currentUser === e.fromUserId) {
                showTyping(e.isTyping);
            }
        });

    function openChat(userId) {
        currentUser = userId;
        document.getElementById('to_user_id').value = userId;

        // Remove active state from all users
        document.querySelectorAll('.user').forEach(u => {
            u.classList.remove('bg-blue-100', 'border-l-4', 'border-blue-500');
            const indicator = u.querySelector('[id^="active-"]');
            if (indicator) indicator.classList.add('hidden');
        });

        // Add active state to selected user
        const activeUser = document.querySelector(`.user[data-user-id="${userId}"]`);
        if (activeUser) {
            activeUser.classList.add('bg-blue-100', 'border-l-4', 'border-blue-500');
            const indicator = document.getElementById(`active-${userId}`);
            if (indicator) indicator.classList.remove('hidden');
        }

        // Get name from the user list item
        const userItem = document.querySelector(`.user[data-user-id="${userId}"] span`);
        currentUserName = userItem ? userItem.textContent.trim() : 'User';

        // Update chat header
        const chatHeader = document.getElementById('chatHeader');
        const chatHeaderName = document.getElementById('chatHeaderName');
        if (chatHeader && chatHeaderName) {
            chatHeaderName.textContent = currentUserName;
            chatHeader.classList.remove('hidden');
        }

        resetUnread(userId);
        loadMessages();
        markAsRead(userId);
        updateSendButton();
    }

    function loadMessages() {
        if (!currentUser) return;

        fetch(`/messages/${currentUser}`)
            .then(res => res.json())
            .then(data => {
                let html = '';
                data.forEach(msg => {
                    const isMine = msg.from_user_id == authUserId;
                    const alignClass = isMine ? 'justify-end' : 'justify-start';
                    const bubbleClass = isMine
                        ? 'bg-blue-500 text-white'
                        : 'bg-gray-200 text-gray-900';

                    const attachmentHtml = renderAttachment(msg);

                    html += `<div class="mb-2 flex ${alignClass}">
                        <div class="max-w-xs rounded-lg px-3 py-2 ${bubbleClass} relative">
                            <div class="text-xs opacity-80 mb-1">
                                ${isMine ? 'Me' : currentUserName}
                            </div>
                            <div class="pr-12">${msg.message || ''}</div>
                            ${attachmentHtml}
                            <div style="position: absolute; bottom: 4px; right: 8px; font-size: 11px; opacity: 0.8; display: flex; align-items: center; gap: 2px;">
                                <span style="font-size: 9px; opacity: 0.7;">${formatTime(msg.created_at)}</span>
                                ${renderStatus(msg)}
                            </div>
                        </div>
                    </div>`;
                });
                document.getElementById('chatBox').innerHTML = html;
                scrollToBottom();
            });
    }

    function scrollToBottom() {
        const chatBox = document.getElementById('chatBox');
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    function sendMessage(e) {
        if (e.key === 'Enter' && currentUser) {
            sendCurrentMessage();
        }
    }

    function sendCurrentMessage() {
        if (!currentUser) return;

        const messageInput = document.getElementById('message');
        const fileInput = document.getElementById('fileInput');

        const hasFile = fileInput.files.length > 0;
        const text = messageInput.value;

        if (!hasFile && !text.trim()) {
            return;
        }

        const formData = new FormData();
        formData.append('to_user_id', currentUser);
        formData.append('message', text);
        if (hasFile) {
            formData.append('attachment', fileInput.files[0]);
        }

        fetch('/send-message', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: formData
        }).then(() => {
            messageInput.value = '';
            fileInput.value = '';
            document.getElementById('fileName').textContent = '';
            updateSendButton();
            loadMessages();
            markAsRead(currentUser);
        });
    }

    function markAsRead(userId) {
        fetch('/messages/read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ from_user_id: userId })
        }).then(() => {
            resetUnread(userId);
            updateMessageStatuses();
        });
    }

    function updateMessageStatuses() {
        if (!currentUser) return;
        loadMessages();
    }

    function renderStatus(msg) {
        if (msg.from_user_id != authUserId) return '';
        
        // WhatsApp-style check marks
        if (msg.read_at) {
            return `<span style="color: #53bdeb; font-size: 14px; font-weight: bold; letter-spacing: -4px;">✓✓</span>`;
        }
        if (msg.delivered_at) {
            return `<span style="color: rgba(255,255,255,0.6); font-size: 14px; font-weight: bold; letter-spacing: -4px;">✓✓</span>`;
        }
        return `<span style="color: rgba(255,255,255,0.5); font-size: 14px;">✓</span>`;
    }

    function formatTime(timestamp) {
        const date = new Date(timestamp);
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        return `${hours}:${minutes}`;
    }

    function renderAttachment(msg) {
        if (!msg.file_path) return '';
        const url = `/storage/${msg.file_path}`;
        const name = msg.file_name || 'Attachment';

        if (msg.file_type && msg.file_type.startsWith('image/')) {
            return `<div class="mt-1">
                <a href="${url}" target="_blank">
                    <img src="${url}" alt="${name}" class="max-h-40 rounded mt-1" />
                </a>
            </div>`;
        }

        return `<div class="mt-1 text-xs">
            <a href="${url}" target="_blank" class="underline text-blue-700">
                📎 ${name}
            </a>
        </div>`;
    }

    function incrementUnread(userId) {
        if (currentUser === userId) return;
        const badge = document.getElementById(`badge-${userId}`);
        if (!badge) return;
        const count = parseInt(badge.textContent || '0', 10) + 1;
        badge.textContent = count;
        badge.classList.remove('hidden');
    }

    function resetUnread(userId) {
        const badge = document.getElementById(`badge-${userId}`);
        if (!badge) return;
        badge.textContent = '0';
        badge.classList.add('hidden');
    }

    function fetchUnreadCounts() {
        fetch('/messages/unread-counts')
            .then(res => res.json())
            .then(data => {
                data.forEach(item => {
                    const badge = document.getElementById(`badge-${item.user_id}`);
                    if (badge) {
                        badge.textContent = item.unread;
                        if (item.unread > 0) {
                            badge.classList.remove('hidden');
                        } else {
                            badge.classList.add('hidden');
                        }
                    }
                });
            });
    }

    const messageInput = document.getElementById('message');
    const sendButton = document.getElementById('sendButton');
    const fileInput = document.getElementById('fileInput');

    function updateSendButton() {
        if (!sendButton) return;
        if (!currentUser) {
            sendButton.disabled = true;
            return;
        }
        const hasText = messageInput && messageInput.value.trim().length > 0;
        const hasFile = fileInput && fileInput.files.length > 0;
        sendButton.disabled = !hasText && !hasFile;
    }

    messageInput.addEventListener('input', () => {
        if (!currentUser) return;
        updateSendButton();
        sendTyping(true);
        if (typingTimeout) clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => sendTyping(false), 800);
    });

    fileInput.addEventListener('change', () => {
        updateSendButton();
        const fileName = fileInput.files[0]?.name || '';
        document.getElementById('fileName').textContent = fileName;
    });

    function sendTyping(isTyping) {
        fetch('/typing', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                to_user_id: currentUser,
                is_typing: isTyping
            })
        });
    }

    function showTyping(isTyping) {
        const el = document.getElementById('typingIndicator');
        el.textContent = isTyping ? 'Typing...' : '';
    }

    // Emoji picker
    const emojiToggle = document.getElementById('emojiToggle');
    const emojiPicker = document.getElementById('emojiPicker');
    emojiToggle.addEventListener('click', () => {
        emojiPicker.classList.toggle('hidden');
    });

    emojiPicker.addEventListener('click', (event) => {
        if (event.target.tagName.toLowerCase() === 'span') {
            const emoji = event.target.textContent;
            messageInput.value += emoji;
            messageInput.focus();
        }
    });

    // File input button handler
    const fileButton = document.getElementById('fileButton');
    if (fileButton) {
        fileButton.addEventListener('click', () => {
            fileInput.click();
        });
    }

    // Initial data
    fetchUnreadCounts();
    updateSendButton();

    // Expose handlers to inline HTML
    window.openChat = openChat;
    window.sendMessage = sendMessage;
    window.sendCurrentMessage = sendCurrentMessage;
});
</script>

</x-app-layout>
