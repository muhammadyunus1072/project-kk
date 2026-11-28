<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KkBatchController;
use App\Http\Controllers\Api\KkDetailController;
use App\Http\Controllers\Api\KkPdfController;
use App\Http\Controllers\Api\TranslationController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Strict Sanctum Authentication: Every KK translation, batch upload,
| and PDF generation endpoint requires a valid Sanctum Bearer token.
|
*/

// ── Public Auth Routes ──
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// ── Authenticated Routes (Strict Sanctum Guard) ──
Route::middleware(['auth:sanctum'])->group(function () {
    // Current user info & logout
    Route::prefix('auth')->group(function () {
        Route::get('/me',     [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // Pusher Private Channel Authentication
    Broadcast::routes(['middleware' => ['auth:sanctum']]);

    // KK Batch Upload, Status & Deletion
    Route::prefix('kk')->group(function () {
        Route::post('/batch',                        [KkBatchController::class, 'store']);
        Route::get('/batch/{batch}',                 [KkBatchController::class, 'show']);
        Route::delete('/batch/{batch}',              [KkBatchController::class, 'destroy']);

        // DomPDF A4 Japanese 戸籍謄本 Generation
        Route::get('/submissions/{submission}/pdf',  [KkPdfController::class, 'download']);
        Route::post('/render-pdf',                   [KkPdfController::class, 'renderCustomPdf']);

        // KK Details (Queue, Rendered Data, Typo Corrections, Status Toggle & Client-Side Verification)
        Route::get('/details',                               [KkDetailController::class, 'index']);
        Route::post('/details',                              [KkDetailController::class, 'store']);
        Route::get('/details/{detail}',                      [KkDetailController::class, 'show']);
        Route::put('/details/{detail}',                      [KkDetailController::class, 'update']);
        Route::post('/details/{detail}/mark-submitted',      [KkDetailController::class, 'markSubmitted']);
        Route::post('/details/{detail}/toggle-submitted',    [KkDetailController::class, 'toggleSubmitted']);
        Route::delete('/details/{detail}',                   [KkDetailController::class, 'destroy']);
        Route::get('/details/{detail}/pdf',                  [KkDetailController::class, 'regeneratePdf']);
        Route::get('/details/{detail}/original-file',        [KkDetailController::class, 'originalFile']);
    });

    // Japanese Katakana Fast Transliterator (mirrors server.ts)
    Route::post('/katakana-name',                            [TranslationController::class, 'katakanaName']);
});
