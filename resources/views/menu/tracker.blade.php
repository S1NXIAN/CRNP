<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Order {{ $state->code }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('menu.partials.header')

    @php
        // The proof surface is only live while the order is still in a payment
        // state; once it is cooking/served/dismissed/rejected the customer
        // attaches nothing (the controller enforces the same guard).
        $canAttachProof = in_array($state->status, ['awaiting_payment', 'verifying'], true);
    @endphp

    {{--
        The order tracker: a live status screen polling the state endpoint every
        5–10 s (no websockets). The order code is shown, never typed, and the
        account-bound active order reopens with zero typing. The GCash number and
        the official QR image sit untouched inside the branded payment frame.
    --}}
    <main
        class="site-main tracker"
        data-poll-url="{{ route('orders.state', ['order' => $state->orderId]) }}"
        data-poll-seconds="{{ $pollSeconds }}"
    >
        <header class="tracker__head">
            <p class="tracker__code-label">Order code</p>
            <p class="tracker__code">{{ $state->code }}</p>
        </header>

        <p
            class="tracker__status"
            id="tracker-status"
            role="status"
            aria-live="polite"
            data-status="{{ $state->status }}"
        >{{ $state->label }}</p>

        @if ($state->pickupAt !== null)
            <p class="tracker__pickup">
                Pickup at
                {{ \Illuminate\Support\Carbon::parse($state->pickupAt)->timezone(config('settings.timezone'))->format('g:i A') }}
            </p>
        @else
            <p class="tracker__pickup">Pickup ASAP</p>
        @endif

        {{-- The payment block: the GCash number to copy/pay-by-number and the
             official QR image, never re-rendered. --}}
        @if ($gcashNumber !== '' || $gcashQr !== '')
            <section class="payment-frame" aria-labelledby="payment-heading">
                <h2 class="payment-frame__title" id="payment-heading">Pay with GCash</h2>

                @if ($gcashQr !== '')
                    <img class="payment-frame__qr" src="{{ $gcashQr }}" alt="Official GCash QR code">
                @endif

                @if ($gcashNumber !== '')
                    <p class="payment-frame__number">
                        <span class="payment-frame__number-label">GCash number</span>
                        <span class="payment-frame__number-value">{{ $gcashNumber }}</span>
                    </p>
                @endif

                @if ($state->deadlineAt !== null)
                    <p class="payment-frame__deadline">
                        Pay by
                        <span class="payment-frame__deadline-value">
                            {{ \Illuminate\Support\Carbon::parse($state->deadlineAt)->timezone(config('settings.timezone'))->format('g:i A') }}
                        </span>
                    </p>
                @endif
            </section>
        @endif

        <p class="tracker__total">
            <span>Total</span>
            <span class="tracker__total-amount">₱{{ number_format($state->total, 2) }}</span>
        </p>

        {{-- A flagged hold shows the entered amount beside the order total, so
             the customer can self-correct at the counter — no blame, no reason
             codes. #27's Reject writes the same sentence; the numbers ride on
             the order node either way. Rendered always and revealed by the
             poll, so a hold caused by the customer's own upload appears without
             a page reload. --}}
        <section class="tracker__hold" id="tracker-hold" aria-labelledby="hold-heading" @if ($state->enteredAmount === null) hidden @endif>
            <h2 class="tracker__hold-title" id="hold-heading">Payment needs checking — see the counter</h2>
            <p class="tracker__hold-numbers">
                <span class="tracker__hold-figure">
                    <span class="tracker__hold-label">Amount you entered</span>
                    <span class="tracker__hold-value" id="hold-entered">@if ($state->enteredAmount !== null)₱{{ number_format($state->enteredAmount, 2) }}@endif</span>
                </span>
                <span class="tracker__hold-figure">
                    <span class="tracker__hold-label">Order total</span>
                    <span class="tracker__hold-value" id="hold-total">₱{{ number_format($state->total, 2) }}</span>
                </span>
            </p>
        </section>

        @if ($canAttachProof)
            {{-- Attach payment screenshot: one button opens the native picker on
                 the newest image; selection auto-uploads with no Submit. The
                 amount prefills with the order total — edit only what differs —
                 and the GCash ref# is typed. A failure shows "Upload failed —
                 tap to retry" plus one automatic retry, and the upload is marked
                 in flight the instant it begins so it holds the order past 15:00
                 (#25). The image is compressed client-side (adaptive JPEG,
                 ≤300 KB, ≥720 px floor) and re-encoded server-side to the same
                 ceiling. --}}
            <section
                class="proof"
                data-proof-upload
                data-start-url="{{ route('orders.proof.start', ['order' => $state->orderId]) }}"
                data-store-url="{{ route('orders.proof.store', ['order' => $state->orderId]) }}"
                aria-labelledby="proof-heading"
            >
                <h2 class="proof__title" id="proof-heading">Proof of payment</h2>

                <input
                    class="visually-hidden"
                    type="file"
                    id="proof-input"
                    accept="image/*"
                    data-proof-input
                >

                <button class="primary-button proof__attach" type="button" data-proof-attach>
                    Attach payment screenshot
                </button>

                <p class="proof__feedback" id="proof-feedback" role="status" aria-live="polite" data-state="idle">
                    <span class="proof__spinner" data-proof-spinner aria-hidden="true" hidden></span>
                    <span data-proof-message></span>
                </p>

                <label class="field proof__field">
                    <span class="field__label">Amount paid</span>
                    <input
                        class="field__input"
                        type="number"
                        name="amount"
                        id="proof-amount"
                        inputmode="decimal"
                        step="0.01"
                        min="0"
                        value="{{ number_format($state->total, 2, '.', '') }}"
                    >
                </label>

                <label class="field proof__field">
                    <span class="field__label">GCash ref#</span>
                    <input
                        class="field__input"
                        type="text"
                        name="ref"
                        id="proof-ref"
                        inputmode="numeric"
                        autocomplete="off"
                        maxlength="64"
                    >
                </label>
            </section>
        @endif

        <p class="tracker__hint">
            This screen refreshes itself. Keep it open — it flips to READY the
            instant the counter marks your order served.
        </p>
    </main>

    <script>
        (function () {
            var root = document.querySelector('.tracker');
            if (!root) { return; }

            var url = root.getAttribute('data-poll-url');
            var seconds = parseInt(root.getAttribute('data-poll-seconds'), 10) || 5;
            var status = document.getElementById('tracker-status');
            var hold = document.getElementById('tracker-hold');
            var holdEntered = document.getElementById('hold-entered');
            var holdTotal = document.getElementById('hold-total');
            var proofPanel = document.querySelector('[data-proof-upload]');
            var proofStates = ['awaiting_payment', 'verifying'];

            function pesos(value) {
                return '₱' + Number(value).toFixed(2);
            }

            // The flagged-hold numbers ride on the order node, so the poll can
            // reveal them even when the customer's own upload caused the hold
            // without a page reload.
            function renderHold(state) {
                if (!hold) { return; }

                if (state.enteredAmount === null || state.enteredAmount === undefined) {
                    hold.hidden = true;
                    return;
                }

                if (holdEntered) { holdEntered.textContent = pesos(state.enteredAmount); }
                if (holdTotal) { holdTotal.textContent = pesos(state.total); }
                hold.hidden = false;
            }

            // The proof surface is live only while the order is still in a
            // payment state; once it verifies/flags the server drops it, and
            // the client hides it the same instant on the poll.
            function poll() {
                fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (state) {
                        if (!state || !status) { return; }
                        status.textContent = state.label;
                        status.setAttribute('data-status', state.status);
                        renderHold(state);

                        if (proofPanel && proofStates.indexOf(state.status) === -1) {
                            proofPanel.hidden = true;
                        }
                    })
                    .catch(function () { /* keep the last known state on a blip */ })
                    .then(function () { window.setTimeout(poll, seconds * 1000); });
            }

            window.setTimeout(poll, seconds * 1000);
        })();

        (function () {
            var panel = document.querySelector('[data-proof-upload]');
            if (!panel) { return; }

            var csrf = document.querySelector('meta[name="csrf-token"]');
            var token = csrf ? csrf.getAttribute('content') : '';
            var input = panel.querySelector('[data-proof-input]');
            var attach = panel.querySelector('[data-proof-attach]');
            var feedback = document.getElementById('proof-feedback');
            var spinner = panel.querySelector('[data-proof-spinner]');
            var message = panel.querySelector('[data-proof-message]');
            var amount = document.getElementById('proof-amount');
            var ref = document.getElementById('proof-ref');
            var startUrl = panel.getAttribute('data-start-url');
            var storeUrl = panel.getAttribute('data-store-url');

            // The proof bounds — the same ceiling the server re-encodes to:
            // adaptive JPEG ≤300 KB with a 720px legibility floor.
            var MAX_BYTES = 300 * 1024;
            var MAX_EDGE = 1600;
            var FLOOR_EDGE = 720;
            var START_QUALITY = 0.85;
            var QUALITY_STEP = 0.07;
            var MIN_QUALITY = 0.4;
            var EDGE_STEP = 40;

            var reduceMotion = window.matchMedia
                && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            function setFeedback(state, text) {
                if (!feedback) { return; }
                feedback.setAttribute('data-state', state);

                if (message) { message.textContent = text || ''; }
                if (spinner) { spinner.hidden = state !== 'uploading'; }
            }

            function busy() {
                if (attach) { attach.disabled = true; }
                setFeedback('uploading', reduceMotion ? 'Uploading…' : 'Uploading your screenshot…');
            }

            function idle() {
                if (attach) { attach.disabled = false; }
            }

            // Compress to the ceiling on a canvas before upload — a 3 MB
            // screenshot never leaves the phone.
            function compress(file, done) {
                var image = new Image();
                var objectUrl = URL.createObjectURL(file);

                image.onload = function () {
                    URL.revokeObjectURL(objectUrl);

                    var sourceLong = Math.max(image.width, image.height);
                    var edge = Math.min(sourceLong, MAX_EDGE);
                    if (edge < FLOOR_EDGE) { edge = Math.min(FLOOR_EDGE, sourceLong); }

                    var canvas = document.createElement('canvas');
                    var context = canvas.getContext('2d');
                    var result = null;

                    while (true) {
                        var scale = edge / sourceLong;
                        var width = Math.max(1, Math.round(image.width * scale));
                        var height = Math.max(1, Math.round(image.height * scale));
                        canvas.width = width;
                        canvas.height = height;

                        // JPEG has no alpha; flatten onto white first.
                        context.fillStyle = '#ffffff';
                        context.fillRect(0, 0, width, height);
                        context.drawImage(image, 0, 0, width, height);

                        var quality = START_QUALITY;
                        var dataUrl = '';

                        do {
                            dataUrl = canvas.toDataURL('image/jpeg', quality);
                            // base64 is ~4/3 of the byte size.
                            if (dataUrl.length * 0.75 <= MAX_BYTES) { break; }
                            quality -= QUALITY_STEP;
                        } while (quality >= MIN_QUALITY);

                        result = dataUrl;

                        if (dataUrl.length * 0.75 <= MAX_BYTES || edge <= FLOOR_EDGE) {
                            break;
                        }

                        edge = Math.max(FLOOR_EDGE, edge - EDGE_STEP);
                    }

                    done(result);
                };

                image.onerror = function () {
                    URL.revokeObjectURL(objectUrl);
                    done(null);
                };

                image.src = objectUrl;
            }

            function dataUrlToBlob(dataUrl) {
                var parts = dataUrl.split(',');
                var mime = parts[0].match(/:(.*?);/)[1];
                var binary = atob(parts[1]);
                var bytes = new Uint8Array(binary.length);

                for (var i = 0; i < binary.length; i++) {
                    bytes[i] = binary.charCodeAt(i);
                }

                return new Blob([bytes], { type: mime });
            }

            // A request sent with no terminal response counts as in flight: the
            // marker is persisted the instant the upload begins, so the expiry
            // sweep holds the order across 15:00 (#25).
            function markInFlight() {
                return fetch(startUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                }).catch(function () { /* a blip on the marker must not block the upload */ });
            }

            function send(file, attempt) {
                compress(file, function (dataUrl) {
                    if (!dataUrl) {
                        fail();
                        return;
                    }

                    var body = new FormData();
                    body.append('image', dataUrlToBlob(dataUrl), 'proof.jpg');
                    body.append('amount', amount ? amount.value : '');
                    body.append('ref', ref ? ref.value : '');

                    fetch(storeUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                        body: body,
                    }).then(function (response) {
                        if (response.ok) {
                            idle();
                            setFeedback('received', 'Screenshot received — checking your payment…');
                            return;
                        }

                        throw new Error('upload failed');
                    }).catch(function () {
                        // One automatic retry, then the manual "tap to retry".
                        if (attempt < 1) {
                            send(file, attempt + 1);
                            return;
                        }

                        fail();
                    });
                });
            }

            function fail() {
                idle();
                setFeedback('failed', 'Upload failed — tap to retry');
            }

            function begin() {
                if (!input || !input.files || input.files.length === 0) { return; }

                busy();
                markInFlight();
                send(input.files[0], 0);
            }

            if (attach && input) {
                attach.addEventListener('click', function () { input.click(); });
                input.addEventListener('change', begin);
            }

            if (feedback) {
                feedback.addEventListener('click', function () {
                    if (feedback.getAttribute('data-state') === 'failed') { begin(); }
                });
            }
        })();
    </script>
</body>
</html>
