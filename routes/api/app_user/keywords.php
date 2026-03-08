<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Keyword\Controllers\KeywordController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('keywords', [KeywordController::class, 'index']);
    Route::get('keywords/statuses', \Src\Application\AppUser\Keyword\Controllers\KeywordStatusController::class);
    Route::get('keywords/{id}', [KeywordController::class, 'show']);
    Route::post('keywords', [KeywordController::class, 'store']);
    Route::put('keywords/{id}', [KeywordController::class, 'update']);
    Route::delete('keywords/{id}', [KeywordController::class, 'destroy']);
});
