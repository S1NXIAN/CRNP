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
            <a href="{{ route('admin.dashboard') }}" aria-current="page">Dashboard</a>
            <a href="{{ route('admin.reports.sales') }}">Reports</a>
            <a href="{{ route('admin.activity') }}">Activity</a>
            <a href="{{ route('admin.products.index') }}">Products</a>
            <a href="{{ route('admin.rentals.index') }}">Inventory</a>
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

        {{-- Badges: last backup + refunds owed, the two standing obligations. --}}
        <section class="badge-row" aria-label="Standing badges">
            <div class="badge-card">
                <span class="badge-card__label">Last backup</span>
                <span class="badge-card__value">
                    @if ($lastBackupAt !== null)
                        {{ \Illuminate\Support\Carbon::parse($lastBackupAt)->setTimezone('Asia/Manila')->format('Y-m-d H:i') }}
                    @else
                        Never
                    @endif
                </span>
                @if ($lastBackupPath !== null)
                    <span class="badge-card__detail">{{ $lastBackupPath }}</span>
                    <a class="badge-card__action" href="{{ route('admin.backup.download') }}">Download</a>
                @endif
            </div>
            <div class="badge-card badge-card--refunds">
                <span class="badge-card__label">Refunds owed</span>
                <span class="badge-card__value">₱{{ number_format($refundsTotal, 2) }}</span>
                <span class="badge-card__detail">{{ $refundsCount }} outstanding</span>
            </div>
        </section>

        {{-- KPIs for today. --}}
        <section class="kpi-row" aria-label="Key figures">
            <div class="kpi">
                <span class="kpi__label">Sales today</span>
                <span class="kpi__value">₱{{ number_format($metrics->salesToday, 2) }}</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Orders today</span>
                <span class="kpi__value">{{ $metrics->ordersToday }}</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Sales · 7 days</span>
                <span class="kpi__value">₱{{ number_format($metrics->salesWindow, 2) }}</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Orders · 7 days</span>
                <span class="kpi__value">{{ $metrics->ordersWindow }}</span>
            </div>
        </section>

        <section class="dashboard-panel" aria-labelledby="trend-heading">
            <h2 id="trend-heading">7-day trend</h2>

            @php($maxTrend = max(array_map(fn($day) => $day['total'], $metrics->trend)) ?: 1.0)

            <ul class="trend">
                @foreach ($metrics->trend as $day)
                    <li class="trend__day">
                        <span class="trend__bar-wrap">
                            <span
                                class="trend__bar"
                                style="height: {{ round(($day['total'] / $maxTrend) * 100, 1) }}%"
                            ></span>
                        </span>
                        <span class="trend__value">₱{{ number_format($day['total'], 0) }}</span>
                        <span class="trend__date">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D j M') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <div class="dashboard-columns">
            <section class="dashboard-panel" aria-labelledby="best-sellers-heading">
                <h2 id="best-sellers-heading">Best sellers</h2>

                @if ($metrics->bestSellers === [])
                    <p class="empty-state">No sales in the last seven days — the ranking fills itself as orders settle.</p>
                @else
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col">Units</th>
                                <th scope="col">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($metrics->bestSellers as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['units'] }}</td>
                                    <td>₱{{ number_format($row['revenue'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            <section class="dashboard-panel" aria-labelledby="peak-hours-heading">
                <h2 id="peak-hours-heading">Peak hours</h2>

                @if ($metrics->peakHours === [])
                    <p class="empty-state">No orders in the last seven days.</p>
                @else
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Hour</th>
                                <th scope="col">Orders</th>
                                <th scope="col">Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($metrics->peakHours as $row)
                                <tr>
                                    <td>{{ $row['hour'] }}</td>
                                    <td>{{ $row['orders'] }}</td>
                                    <td>₱{{ number_format($row['total'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </div>
    </main>
</body>
</html>
