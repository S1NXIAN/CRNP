{{--
    The public site header — the one logo on its dark chip, the always-visible
    labeled nav (never an unlabeled icon), and the account control. The logo is
    served as-is; it is white-on-transparent, so the chip keeps it legible.
--}}
<header class="site-header">
    <a class="site-brand" href="{{ route('home') }}">
        <span class="logo-chip">
            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }} logo">
        </span>
        <span class="site-title">{{ config('app.name') }}</span>
    </a>
    <nav class="site-nav" aria-label="Primary">
        <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>Menu</a>
        <a href="{{ route('occupancy') }}" @if (request()->routeIs('occupancy')) aria-current="page" @endif>Availability</a>
        <a href="{{ route('rentals.index') }}" @if (request()->routeIs('rentals.index', 'rentals.show')) aria-current="page" @endif>Rentals</a>
        <a href="{{ route('about') }}" @if (request()->routeIs('about')) aria-current="page" @endif>About</a>
        @auth
            <a href="{{ route('cart.show') }}" @if (request()->routeIs('cart.show', 'checkout.show')) aria-current="page" @endif>Cart</a>
            <a href="{{ route('orders.tracker') }}" @if (request()->routeIs('orders.*')) aria-current="page" @endif>My order</a>
            <a href="{{ route('rentals.mine') }}" @if (request()->routeIs('rentals.mine', 'rentals.track')) aria-current="page" @endif>My rentals</a>
        @endauth
    </nav>
    <div class="site-account">
        @auth
            <span class="site-account__name">{{ auth()->user()?->name ?? 'Signed in' }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="link-button">Sign out</button>
            </form>
        @else
            @include('auth.partials.google-button')
        @endauth
    </div>
</header>
