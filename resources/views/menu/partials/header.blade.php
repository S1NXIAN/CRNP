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
        <a href="{{ route('about') }}" @if (request()->routeIs('about')) aria-current="page" @endif>About</a>
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
