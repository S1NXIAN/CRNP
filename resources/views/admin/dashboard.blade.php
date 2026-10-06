<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="site-header">
        <span class="site-title">{{ config('app.name') }} · Admin</span>
        <nav class="site-account" aria-label="Admin">
            <a href="{{ route('admin.products.index') }}">Products</a>
            <a href="{{ route('admin.categories.index') }}">Categories</a>
            <a href="{{ route('admin.staff.index') }}">Staff</a>
            <a href="{{ route('admin.settings.edit') }}">Settings</a>
        </nav>
    </header>

    <main class="site-main">
        <h1>Dashboard</h1>

        @unless ($setupComplete)
            <p class="setup-banner" role="alert">
                <strong>Complete setup.</strong>
                Ordering stays closed until both the GCash number and the official
                QR image are configured.
                <a href="{{ route('admin.settings.edit') }}">Open settings</a>.
            </p>
        @endunless

        <p>KPIs, products, and reports arrive with the admin slices.</p>
    </main>
</body>
</html>
