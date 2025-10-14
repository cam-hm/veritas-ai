<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\StreamController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/documents', [DocumentController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('documents.index');

Route::get('/chat', [ChatController::class, 'general'])
    ->middleware(['auth', 'verified'])
    ->name('chat.general');

Route::get('/chat/{document}', [ChatController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('chat.show');

Route::post('/chat/stream', [StreamController::class, 'stream'])
    ->middleware(['auth'])
    ->name('chat.stream');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
