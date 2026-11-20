<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use App\Events\MessageSent;
use App\Events\MessageRead;
use App\Events\UserTyping;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function index()
    {
        $users = User::where('id', '!=', auth()->id())->get();

        return view('chat.index', compact('users'));
    }

    public function fetchMessages($userId)
    {
        $messages = Message::where(function ($q) use ($userId) {
                $q->where('from_user_id', auth()->id())
                  ->where('to_user_id', $userId);
            })
            ->orWhere(function ($q) use ($userId) {
                $q->where('from_user_id', $userId)
                  ->where('to_user_id', auth()->id());
            })
            ->orderBy('created_at')
            ->get();

        // Mark inbound messages as delivered/read when the chat is opened.
        $unread = $messages->where('from_user_id', $userId)
            ->where('to_user_id', auth()->id())
            ->whereNull('read_at')
            ->pluck('id');

        if ($unread->isNotEmpty()) {
            Message::whereIn('id', $unread)->update([
                'delivered_at' => DB::raw('COALESCE(delivered_at, NOW())'),
                'read_at' => now(),
            ]);

            broadcast(new MessageRead($userId, auth()->id()));
        }

        // Mark delivered for any inbound messages not yet delivered.
        $undelivered = $messages->where('from_user_id', $userId)
            ->where('to_user_id', auth()->id())
            ->whereNull('delivered_at')
            ->pluck('id');

        if ($undelivered->isNotEmpty()) {
            Message::whereIn('id', $undelivered)->update(['delivered_at' => now()]);
        }

        // Refresh updated state
        return Message::whereIn('id', $messages->pluck('id'))
            ->orderBy('created_at')
            ->get();
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'to_user_id' => ['required', 'exists:users,id'],
            'message'    => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);
        // , 'required_without:attachment'
        if (!$request->filled('message') && !$request->hasFile('attachment')) {
            return response()->json([
                'status' => 'error',
                'error' => 'Message or attachment required'
            ], 422);
        }

        $filePath = null;
        $fileName = null;
        $fileType = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filePath = $file->store('attachments', 'public');
            $fileName = $file->getClientOriginalName();
            $fileType = $file->getClientMimeType();
        }

        $message = Message::create([
            'from_user_id' => auth()->id(),
            'to_user_id'   => $request->to_user_id,
            'message'      => $request->message,
            'file_path'    => $filePath,
            'file_name'    => $fileName,
            'file_type'    => $fileType,
        ]);

        // broadcast(new MessageSent($message))->toOthers();
        broadcast(new MessageSent($message));
        
        return response()->json([
            'status' => 'success',
            'message' => $message
        ]);
    }

    public function markAsRead(Request $request)
    {
        $request->validate([
            'from_user_id' => ['required', 'exists:users,id'],
        ]);

        $count = Message::where('from_user_id', $request->from_user_id)
            ->where('to_user_id', auth()->id())
            ->whereNull('read_at')
            ->update([
                'delivered_at' => DB::raw('COALESCE(delivered_at, NOW())'),
                'read_at' => now(),
            ]);

        if ($count > 0) {
            broadcast(new MessageRead($request->from_user_id, auth()->id()));
        }

        return response()->json(['status' => 'ok']);
    }

    public function unreadCounts()
    {
        $counts = Message::selectRaw('from_user_id as user_id, COUNT(*) as unread')
            ->where('to_user_id', auth()->id())
            ->whereNull('read_at')
            ->groupBy('from_user_id')
            ->get();

        return response()->json($counts);
    }

    public function typing(Request $request)
    {
        $request->validate([
            'to_user_id' => ['required', 'exists:users,id'],
            'is_typing' => ['required', 'boolean'],
        ]);

        broadcast(new UserTyping(auth()->id(), $request->to_user_id, $request->boolean('is_typing')));

        return response()->json(['status' => 'ok']);
    }
}
