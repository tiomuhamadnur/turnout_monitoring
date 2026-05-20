<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// Auth endpoints used by the SPA. Sanctum's statefulApi() middleware ensures
// these are CSRF-protected and run inside the web session group.
Route::post('/login',  [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// SPA catch-all. Anything that is not an /api, /sanctum, /storage, or asset
// route falls through to the Vue Router on the client side.
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api|sanctum|storage|build|up).*$');
