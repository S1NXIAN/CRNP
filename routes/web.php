<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Auth\GoogleSignInController;
use App\Http\Controllers\Auth\SignInPromptController;
use App\Http\Controllers\Auth\SwitchAccountController;
use App\Http\Controllers\Cashier\RegisterController;
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

// Switch account on a shared register: sign out, then the one Google button.
Route::post('/auth/switch-account', SwitchAccountController::class)
    ->middleware('auth')
    ->name('auth.switch-account');

// Staff areas: the role is read from `staff/{uid}` per request, so a Google
// account absent from the staff list is refused and a demotion takes effect on
// the next request. `/cashier` is for cashiers, `/admin` for admins only.
Route::middleware(['auth', 'staff.role:cashier'])->group(function (): void {
    Route::get('/cashier', RegisterController::class)->name('cashier.register');
});

Route::middleware(['auth', 'staff.role:admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

    // Business settings. Each write goes through an action that persists the
    // whole `settings` node and evicts the catalog cache, so a force-close
    // reaches the customer site on the next request.
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings/hours', [SettingsController::class, 'updateHours'])->name('settings.hours');
    Route::put('/settings/banner', [SettingsController::class, 'updateBanner'])->name('settings.banner');
    Route::post('/settings/closures', [SettingsController::class, 'storeClosure'])->name('settings.closures.store');
    Route::delete('/settings/closures/{closure}', [SettingsController::class, 'destroyClosure'])->name('settings.closures.destroy');
    Route::post('/settings/payment', [SettingsController::class, 'updatePayment'])->name('settings.payment');
    Route::post('/settings/kitchen-secret', [SettingsController::class, 'rotateKitchenSecret'])->name('settings.kitchen-secret');
    Route::put('/settings/capacities', [SettingsController::class, 'updateCapacities'])->name('settings.capacities');
});
