<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Auth\GoogleSignInController;
use App\Http\Controllers\Auth\SignInPromptController;
use App\Http\Controllers\Auth\SwitchAccountController;
use App\Http\Controllers\Cashier\RegisterController;
use App\Http\Controllers\Cashier\ReservationController;
use App\Http\Controllers\Cashier\WalkInController;
use App\Http\Controllers\Customer\AboutController;
use App\Http\Controllers\Customer\AddOnPrefsController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\FavoriteToggleController;
use App\Http\Controllers\Customer\MenuController;
use App\Http\Controllers\Customer\OccupancyController;
use App\Http\Controllers\Customer\OrderTrackerController;
use App\Http\Controllers\Customer\ProductPageController;
use App\Http\Controllers\Customer\ProofController;
use Illuminate\Support\Facades\Route;

// The public site: the menu and product pages serve from the coalesced catalog
// cache (PLAN §4 Quota discipline, rule 2), so a page view never re-reads the
// base64 product images. Browsing never requires a sign-in.
Route::get('/', MenuController::class)->name('home');
Route::get('/menu/{product}', ProductPageController::class)->name('menu.product');
Route::get('/about', AboutController::class)->name('about');

// The public read-only occupancy view: seats taken vs capacity per slot for the
// week ahead, confirmed bookings only, aggregate only — it books nothing.
Route::get('/occupancy', OccupancyController::class)->name('occupancy');

// Gated per-account actions: the heart and the saved add-on selection prompt
// the Google button while signed out (browsing never does) and sync through
// `users/{uid}`, so they follow the customer across devices. The cart is gated
// the same way — building one requires a live session.
Route::middleware('auth')->group(function (): void {
    Route::post('/menu/{product}/favorite', FavoriteToggleController::class)->name('favorites.toggle');
    Route::post('/menu/{product}/add-ons', AddOnPrefsController::class)->name('prefs.add-ons');

    // Cart: inline steppers and add-on picks; totals recompute server-side.
    Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
    Route::post('/cart', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{product}', [CartController::class, 'destroy'])->name('cart.destroy');

    // Order tracker: a live status screen bound to the session and
    // `users/{uid}`, reopenable with zero typing; the code is shown, never typed.
    Route::get('/orders', [OrderTrackerController::class, 'reopen'])->name('orders.tracker');
    Route::get('/orders/{order}', [OrderTrackerController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/state', [OrderTrackerController::class, 'state'])->name('orders.state');

    // Proof of payment: an in-flight marker when the upload begins, then the
    // screenshot + amount + ref#. The server re-encodes the image and
    // auto-verifies (or flags the hold); the client re-polls the tracker.
    Route::post('/orders/{order}/proof', [ProofController::class, 'store'])->name('orders.proof.store');
    Route::post('/orders/{order}/proof/start', [ProofController::class, 'start'])->name('orders.proof.start');
});

// Checkout: pickup time (default ASAP, earliest now + 15 min) then Place order.
// The POST is validated and throttled (10/min per user, 30/min per real IP for
// anonymous) and carries a client-generated idempotency key. Sign-in is
// enforced in the controller, not by `auth`, so the throttle counts anonymous
// traffic before the sign-in redirect (PLAN §4 *Auth & roles*).
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])
    ->middleware('throttle:checkout')
    ->name('checkout.store');

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

    // Walk-in ring-up: tap tiles → one typed cash number → receipt. The order
    // draws its code at ring-up and only reaches the kitchen board at settle.
    Route::prefix('cashier/walkins')->name('cashier.walkins.')->group(function (): void {
        Route::post('/', [WalkInController::class, 'store'])->name('store');
        Route::get('/{walkin}', [WalkInController::class, 'show'])->name('show');
        Route::post('/{walkin}/items', [WalkInController::class, 'addItem'])->name('items.store');
        Route::delete('/{walkin}/items/{product}', [WalkInController::class, 'removeItem'])->name('items.destroy');
        Route::post('/{walkin}/settle', [WalkInController::class, 'settle'])->name('settle');
        Route::get('/{walkin}/receipt', [WalkInController::class, 'receipt'])->name('receipt');
    });

    // Reservation entry is lookup-first: the phone typeahead prefills a
    // returning customer; a new customer gets a guided two-step capture. The
    // server-run conflict and duplicate check blocks a double-booking with a
    // reason before the write and again on confirm.
    Route::get('/cashier/reservations', [ReservationController::class, 'index'])->name('cashier.reservations.index');
    Route::get('/cashier/reservations/new', [ReservationController::class, 'create'])->name('cashier.reservations.create');
    Route::get('/cashier/reservations/lookup', [ReservationController::class, 'lookup'])->name('cashier.reservations.lookup');
    Route::post('/cashier/reservations/details', [ReservationController::class, 'details'])->name('cashier.reservations.details');
    Route::post('/cashier/reservations', [ReservationController::class, 'store'])->name('cashier.reservations.store');
    Route::post('/cashier/reservations/{reservation}/confirm', [ReservationController::class, 'confirm'])->name('cashier.reservations.confirm');
    Route::post('/cashier/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('cashier.reservations.cancel');
});

Route::middleware(['auth', 'staff.role:admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

    // Categories — the admin-managed grouping products reference by id.
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    // Products — CRUD, the one-tap hide toggle (86 board), and the one-click
    // Add promo control. The product form stays promo-free.
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::put('/products/{product}/visibility', [ProductController::class, 'visibility'])->name('products.visibility');
    Route::post('/products/{product}/promo', [ProductController::class, 'addPromo'])->name('products.promo.store');
    Route::delete('/products/{product}/promo', [ProductController::class, 'removePromo'])->name('products.promo.destroy');

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
