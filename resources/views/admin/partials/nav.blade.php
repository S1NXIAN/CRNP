@php
    /** @var string $active */
    $active = $active ?? '';
@endphp
<header class="site-header">
    <span class="site-title">{{ config('app.name') }} · Admin</span>
    <nav class="site-account" aria-label="Admin">
        <a href="{{ route('admin.dashboard') }}" @if ($active === 'dashboard') aria-current="page" @endif>Dashboard</a>
        <a href="{{ route('admin.reports.sales') }}" @if ($active === 'reports') aria-current="page" @endif>Reports</a>
        <a href="{{ route('admin.activity') }}" @if ($active === 'activity') aria-current="page" @endif>Activity</a>
        <a href="{{ route('admin.products.index') }}">Products</a>
        <a href="{{ route('admin.rentals.index') }}">Inventory</a>
        <a href="{{ route('admin.staff.index') }}">Staff</a>
        <a href="{{ route('admin.settings.edit') }}">Settings</a>
    </nav>
</header>
