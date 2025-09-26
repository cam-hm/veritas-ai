<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Camh\Ollama\Support\Conversation;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/chat/stream', function (Request $request) {
    $userId = '123';
    $conversation = Conversation::load($userId) ?? new Conversation($userId, 'You are a helpful assistant.');

    $userMessage = 'Now explain it to me like I am five years old. And using Vietnamese language.';
    $reply = Ollama::conversation($conversation, $userMessage);

    // Optionally, save after each turn (already done in method)
    $conversation->save();

    return response()->json([
        'reply' => $reply,
        'history' => $conversation->getMessages(),
    ]);
});