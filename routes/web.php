<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Unified SPA Frontend Serving
|--------------------------------------------------------------------------
|
| The React application (kartu-keluarga) builds its static assets
| into public/. All non-API routes are routed to public/index.html.
|
*/

Route::get('/{any}', function () {
    $indexPath = public_path('index.html');
    if (file_exists($indexPath)) {
        return response()->file($indexPath);
    }
    return view('welcome');
})->where('any', '^(?!api|sanctum|up).*$');
