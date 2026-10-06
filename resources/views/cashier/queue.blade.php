<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Online queue · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('cashier.partials.register-header')

    {{--
        The online pickup queue. A flagged hold (entered amount ≠ total or a
        blank ref#) is the only exception: both numbers sit side by side for
        one-tap Reject. Prepaid verified orders arrive ready-to-pay with no
        approval tap and sit in the recently-verified list for at-leisure
        spot-check, where Reject stays reachable until Mark served. In-window
        orders with no screenshot yet show as awaiting proof. A Rejected order
        is its own list: Approve (the undo) or Void, never Restore, with the
        claimed refund beside the total. Mark served is the one happy-path tap.
        The screen polls the coalesced state endpoint and reloads the
        server-rendered rows only when the membership signature changes.
    --}}
    <main
        class="site-main queue"
        data-poll-url="{{ route('cashier.queue.state') }}"
        data-poll-seconds="{{ $pollSeconds }}"
        data-signature="{{ $snapshot->signature() }}"
    >
        <h1 class="queue__title">Online pickup queue</h1>

        <section class="queue-section" aria-labelledby="queue-flagged-heading">
            <h2 class="queue-section__title" id="queue-flagged-heading">Flagged holds</h2>
            <p class="queue-section__hint">Entered amount or ref# mismatch — the only thing that interrupts auto-verify.</p>

            @if ($snapshot->flaggedHolds === [])
                <p class="empty-state">No flagged holds — the line is clear.</p>
            @else
                <ul class="queue-list">
                    @foreach ($snapshot->flaggedHolds as $order)
                        <li class="queue-row">
                            <span class="queue-row__code">{{ $order->code }}</span>
                            <span class="queue-row__figures">
                                <span class="queue-row__figure">
                                    <span class="queue-row__figure-label">Entered</span>
                                    <span class="queue-row__figure-value">₱{{ number_format($order->enteredAmount ?? $order->total, 2) }}</span>
                                </span>
                                <span class="queue-row__figure">
                                    <span class="queue-row__figure-label">Total</span>
                                    <span class="queue-row__figure-value">₱{{ number_format($order->total, 2) }}</span>
                                </span>
                            </span>
                            <form method="POST" action="{{ route('cashier.orders.reject', ['order' => $order->id]) }}" class="queue-row__action">
                                @csrf
                                <button type="submit" class="link-button">Reject</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="queue-section" aria-labelledby="queue-ready-heading">
            <h2 class="queue-section__title" id="queue-ready-heading">Ready to pay</h2>

            @if ($snapshot->readyToPay === [])
                <p class="empty-state">No orders ready to pay — prepaid orders appear here the moment they verify.</p>
            @else
                <ul class="queue-list">
                    @foreach ($snapshot->readyToPay as $order)
                        <li class="queue-row">
                            <span class="queue-row__code">{{ $order->code }}</span>
                            <span class="queue-row__total">₱{{ number_format($order->total, 2) }}</span>
                            <form method="POST" action="{{ route('cashier.orders.serve', ['order' => $order->id]) }}" class="queue-row__action">
                                @csrf
                                <button type="submit" class="primary-button">Mark served</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="queue-section" aria-labelledby="queue-verified-heading">
            <h2 class="queue-section__title" id="queue-verified-heading">Recently verified</h2>
            <p class="queue-section__hint">At-leisure spot-check — Reject stays reachable until Mark served.</p>

            @if ($snapshot->recentlyVerified === [])
                <p class="empty-state">Nothing to spot-check yet.</p>
            @else
                <ul class="queue-list">
                    @foreach ($snapshot->recentlyVerified as $order)
                        <li class="queue-row">
                            <span class="queue-row__code">{{ $order->code }}</span>
                            <span class="queue-row__total">₱{{ number_format($order->total, 2) }}</span>
                            <form method="POST" action="{{ route('cashier.orders.reject', ['order' => $order->id]) }}" class="queue-row__action">
                                @csrf
                                <button type="submit" class="link-button">Reject</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="queue-section" aria-labelledby="queue-awaiting-heading">
            <h2 class="queue-section__title" id="queue-awaiting-heading">Awaiting proof</h2>
            <p class="queue-section__hint">In the payment window with no screenshot yet.</p>

            @if ($snapshot->awaitingProof === [])
                <p class="empty-state">No customers waiting on proof.</p>
            @else
                <ul class="queue-list">
                    @foreach ($snapshot->awaitingProof as $order)
                        <li class="queue-row">
                            <span class="queue-row__code">{{ $order->code }}</span>
                            <span class="queue-row__total">₱{{ number_format($order->total, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="queue-section" aria-labelledby="queue-rejected-heading">
            <h2 class="queue-section__title" id="queue-rejected-heading">Rejected</h2>
            <p class="queue-section__hint">Payment refused — the claimed amount is owed. Undo it, or Void to end it.</p>

            @if ($snapshot->rejected === [])
                <p class="empty-state">No rejected payments.</p>
            @else
                <ul class="queue-list">
                    @foreach ($snapshot->rejected as $order)
                        <li class="queue-row">
                            <span class="queue-row__code">{{ $order->code }}</span>
                            <span class="queue-row__figures">
                                <span class="queue-row__figure">
                                    <span class="queue-row__figure-label">Claimed</span>
                                    <span class="queue-row__figure-value">₱{{ number_format($order->refundOwed ?? $order->total, 2) }}</span>
                                </span>
                                <span class="queue-row__figure">
                                    <span class="queue-row__figure-label">Total</span>
                                    <span class="queue-row__figure-value">₱{{ number_format($order->total, 2) }}</span>
                                </span>
                            </span>
                            <span class="queue-row__actions">
                                <form method="POST" action="{{ route('cashier.orders.approve', ['order' => $order->id]) }}" class="queue-row__action">
                                    @csrf
                                    <button type="submit" class="primary-button">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('cashier.orders.void', ['order' => $order->id]) }}" class="queue-row__action">
                                    @csrf
                                    <button type="submit" class="link-button">Void</button>
                                </form>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </main>

    <script>
        (function () {
            var root = document.querySelector('.queue');
            if (!root) { return; }

            var url = root.getAttribute('data-poll-url');
            var seconds = parseInt(root.getAttribute('data-poll-seconds'), 10) || 5;
            var signature = root.getAttribute('data-signature');

            // The state read is served from the same coalesced entry the page
            // rendered from, so a poll only reloads when the membership actually
            // moved — no flicker on an unchanged queue.
            function poll() {
                fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (state) {
                        if (state && state.signature && state.signature !== signature) {
                            window.location.reload();
                            return;
                        }
                    })
                    .catch(function () { /* keep the last known queue on a blip */ })
                    .then(function () { window.setTimeout(poll, seconds * 1000); });
            }

            window.setTimeout(poll, seconds * 1000);
        })();
    </script>
</body>
</html>
