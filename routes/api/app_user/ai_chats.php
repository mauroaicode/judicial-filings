<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\AiChat\Controllers\AiChatStreamController;
use Src\Application\AppUser\AiChat\Controllers\ListAiChatController;
use Src\Application\AppUser\AiChat\Controllers\ListAiChatMessagesController;
use Src\Application\AppUser\AiChat\Controllers\StoreAiChatController;

Route::prefix('ai-chats')->name('ai-chats.')->middleware('auth:sanctum')->group(function () {
    Route::post('/', StoreAiChatController::class)->name('store');
    Route::get('/process/{processId}', ListAiChatController::class)->name('index');
    Route::post('/{chatId}/stream', AiChatStreamController::class)->name('stream');
    Route::get('/{chatId}/messages', ListAiChatMessagesController::class)->name('messages');
});
