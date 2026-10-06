<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('cashier.partials.register-header')

    <main class="site-main">
        <section class="empty-state" aria-labelledby="register-heading">
            <h1 class="empty-state__title" id="register-heading">The register is being set up</h1>
            <p>The walk-in ring-up and the online pickup queue arrive with the cashier slices.</p>
        </section>
    </main>
</body>
</html>
