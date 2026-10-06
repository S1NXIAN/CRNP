<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="site-header">
        <span class="logo-chip">
            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }} logo">
        </span>
        <span class="site-title">{{ config('app.name') }}</span>
        <nav class="site-account" aria-label="Account">
            @auth
                <span class="site-account__name">{{ auth()->user()?->name ?? 'Signed in' }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="link-button">Sign out</button>
                </form>
            @else
                @include('auth.partials.google-button')
            @endauth
        </nav>
    </header>

    <main class="site-main">
        <h1>{{ config('app.name') }}</h1>
        <p>One diner system: customers order online for pickup, onsite customers
            are rung up at the counter, the kitchen cooks off a read-only board.</p>

        <section class="empty-state" aria-labelledby="menu-heading">
            <h2 class="empty-state__title" id="menu-heading">The menu is being set up</h2>
            <p>No products yet — the owner adds them from the back office.</p>
        </section>
    </main>
</body>
</html>
