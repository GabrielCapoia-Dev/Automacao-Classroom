<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\GoogleMainAuthController;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;


Route::get('/', function () {
    return view('home');
});

Route::get('/test', function () {
    return view('test');
});

Route::get('/oauth/redirect/google', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/oauth/callback/google', [GoogleAuthController::class, 'callback'])->name('google.callback');

// conta MAIN do sistema
Route::get('/settings/google/connect', [GoogleMainAuthController::class, 'redirect'])
    ->name('google.main.connect');

Route::get('/settings/google/callback', [GoogleMainAuthController::class, 'callback'])
    ->name('google.main.callback');
