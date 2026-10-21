<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Modules\Chat\app\Http\Controllers\ConversationController;
use Modules\Chat\app\Http\Controllers\MessageController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware([SetLocale::class, 'auth:sanctum'])->prefix('v1/chat')->group(function () {
    Route::get('conversations', [ConversationController::class, 'index']);
    Route::post('conversations', [ConversationController::class, 'store']);
    Route::delete('conversations/{id}', [ConversationController::class, 'destroy']);
    Route::put('conversations/{id}/read', [ConversationController::class, 'markAsRead']);

    Route::get('conversations/{id}/messages', [MessageController::class, 'index']);
    Route::post('conversations/{id}/messages', [MessageController::class, 'store']);
    Route::post('conversations/{id}/media', [MessageController::class, 'storeMedia']);
});
