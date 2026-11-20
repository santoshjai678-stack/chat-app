<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ChatController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Chat routes
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::get('/messages/{userId}', [ChatController::class, 'fetchMessages']);
    Route::post('/send-message', [ChatController::class, 'sendMessage']);
    Route::post('/messages/read', [ChatController::class, 'markAsRead']);
    Route::get('/messages/unread-counts', [ChatController::class, 'unreadCounts']);
    Route::post('/typing', [ChatController::class, 'typing']);
});

require __DIR__.'/auth.php';
