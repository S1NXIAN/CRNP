<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleSignInController;
use App\Http\Controllers\Auth\SignInPromptController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

// The one sign-in surface: a Google button, never a form. Browsing stays open;
// only ordering, favoriting, and preference sync route a visitor here.
Route::get('/login', SignInPromptController::class)->name('login');
Route::get('/auth/google/redirect', [GoogleSignInController::class, 'redirect'])
    ->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleSignInController::class, 'callback'])
    ->name('auth.google.callback');
Route::post('/logout', [GoogleSignInController::class, 'logout'])->name('logout');
