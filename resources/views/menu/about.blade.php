<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>About · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('menu.partials.header')

    <main class="site-main">
        <section class="about" aria-labelledby="about-heading">
            <h1 id="about-heading">About {{ config('app.name') }}</h1>
            <p class="site-main__copy">
                A neighbourhood diner. Order online for pickup, pay by GCash,
                collect with your order code — no table, no queue at the counter.
            </p>
        </section>

        <section class="about" aria-labelledby="branches-heading">
            <h2 id="branches-heading">Branches</h2>
            <ul class="branch-list">
                <li class="branch-list__item">
                    <span class="branch-list__name">Main Branch</span>
                    <span class="branch-list__detail">Open daily · pickup counter</span>
                </li>
            </ul>
        </section>
    </main>
</body>
</html>
