{{--
    The register header: it names the cashier on duty from `staff/{uid}` and
    offers Switch account — sign out, then the one Google sign-in button — so a
    shared register still records known hands.
--}}
<header class="register-header">
    <span class="register-header__brand">{{ config('app.name') }} · POS</span>
    <nav class="register-header__nav" aria-label="Register">
        <a href="{{ route('cashier.reservations.index') }}" @if (request()->routeIs('cashier.reservations.*')) aria-current="page" @endif>Reservations</a>
    </nav>
    <p class="register-header__duty">
        <span class="register-header__duty-label">Cashier on duty</span>
        <span class="register-header__duty-name">{{ $cashier?->name ?? 'Signed in' }}</span>
    </p>
    <form method="POST" action="{{ route('auth.switch-account') }}" class="register-header__switch">
        @csrf
        <button type="submit" class="link-button">Switch account</button>
    </form>
</header>
