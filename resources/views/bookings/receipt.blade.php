@extends('layouts.app')

@section('content')
@php
    $expiredHold = $booking->hasExpiredHold() || $booking->isExpiredHoldRecord();
    $unavailable = $expiredHold || $booking->status === 'cancelled';
    $paymentComplete = in_array($booking->payment_status, ['paid', 'refunded'], true);
    $canPayOnline = ! $unavailable && ! $paymentComplete;
    $temporaryHold = ! $unavailable && $booking->status === 'pending' && ! $booking->hasProtectedInventory();
    $holdDeadline = $temporaryHold ? $booking->holdDeadline() : null;
    $remainingHoldSeconds = $holdDeadline ? max(0, $holdDeadline->timestamp - now()->timestamp) : 0;
@endphp
<style>
    .confirmation.receipt-page{width:min(100%,1260px);max-width:1260px;padding:24px 28px 42px;text-align:center}
    .receipt-page>.eyebrow{margin-bottom:6px}
    .receipt-page>h1{max-width:1120px;margin:0 auto 6px;font-size:clamp(2.2rem,3.5vw,3.25rem);line-height:1.02}
    .receipt-page>h1+p{max-width:900px;margin:0 auto 18px;font-size:.95rem;line-height:1.45}
    .receipt-layout{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(320px,.8fr);align-items:start;gap:16px;max-width:1120px;margin:0 auto 18px;text-align:left}
    .receipt-card,.receipt-summary{width:100%;max-width:none;margin:0;text-align:left}
    .receipt-card{grid-template-columns:repeat(2,minmax(0,1fr));align-content:start;gap:13px 16px;padding:18px;border-radius:14px;background:var(--paper)}
    .receipt-card .receipt-details-title{grid-column:1/-1;margin:0 0 2px;font:700 1.05rem 'DM Sans',sans-serif;color:var(--ink)}
    .receipt-card .receipt-reference{grid-column:1/-1;padding-bottom:10px;border-bottom:1px solid var(--line)}
    .receipt-card>div:not(.receipt-reference){padding-bottom:8px;border-bottom:1px solid var(--line)}
    .receipt-card small{margin-bottom:3px;font-size:.75rem}
    .receipt-card b{font-size:.9rem;line-height:1.4}
    .receipt-summary{padding:20px;border:1px solid #e3b35d;border-radius:14px;background:linear-gradient(135deg,#fff8eb,#fffdf9);box-shadow:0 12px 30px rgba(104,64,16,.08)}
    .receipt-payment-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
    .receipt-payment-heading>span:first-child{color:#935300;font-size:.72rem;font-weight:800;letter-spacing:.12em}
    .receipt-payment-title{margin:0 0 14px;color:var(--ink);font-size:1.25rem}
    .receipt-summary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:0 0 12px}
    .receipt-summary-grid div{min-width:0;padding:12px;border:1px solid rgba(116,81,42,.1);border-radius:9px;background:#fff}
    .receipt-summary-grid small{display:block;margin-bottom:5px;color:var(--muted)}
    .receipt-summary-grid b{font-size:1.12rem}
    .receipt-summary-grid .receipt-amount-card b{display:block;color:#7d4300;font-size:clamp(1.8rem,4vw,2.25rem);line-height:1.1;letter-spacing:-.035em}
    .receipt-status{display:inline-flex;padding:7px 11px;border-radius:999px;background:#fff0d4;color:#805000;font-size:.78rem;font-weight:800}
    .receipt-status.paid{background:#dcfce7;color:#166534}
    .receipt-note{margin:0;color:var(--muted);line-height:1.55}
    .receipt-hold-alert{margin:12px 0 0;padding:12px 14px;border:1px solid #e9c17e;border-radius:9px;background:#fff3d9;color:#65400d}
    .receipt-hold-alert>p{margin:5px 0 0;font-size:.9rem;line-height:1.5}
    .receipt-hold-alert strong{color:#50320b}
    .paymongo-submit{display:flex;align-items:center;justify-content:center;gap:9px;width:100%;min-height:52px;margin-top:12px;padding:12px 18px;font-size:1rem;font-weight:800}
    .paymongo-submit:disabled{cursor:wait;opacity:.82}
    .paymongo-spinner{display:none;width:17px;height:17px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:paymongo-spin .7s linear infinite}
    .paymongo-submit[aria-busy="true"] .paymongo-spinner{display:inline-block}
    .paymongo-loading-message{display:none;margin:9px 0 0;color:var(--muted);font-size:.9rem}
    .paymongo-loading-message.visible{display:block}
    .paymongo-loading-message.error{color:#b42318;font-weight:600}
    .dark-mode .receipt-summary{border-color:#80602e;background:linear-gradient(145deg,#33271c,#251d17);box-shadow:0 16px 32px rgba(0,0,0,.24)}
    .dark-mode .receipt-payment-heading>span:first-child{color:#ffc16b}
    .dark-mode .receipt-summary-grid div{border-color:rgba(255,220,182,.1);background:#1d1713;color:#fff4e8}
    .dark-mode .receipt-summary-grid small,.dark-mode .receipt-note{color:#d4c3b2}
    .dark-mode .receipt-summary-grid .receipt-amount-card b{color:#ffd18a}
    .dark-mode .receipt-status.paid{background:#17432b;color:#b7f7c5}
    .dark-mode .receipt-hold-alert{border-color:#8b622b;background:#3c2d1a;color:#f3d8a7}
    .dark-mode .receipt-hold-alert strong{color:#ffe1a9}
    @keyframes paymongo-spin{to{transform:rotate(360deg)}}
    @media(prefers-reduced-motion:reduce){.paymongo-spinner{animation-duration:1.8s}}
    .receipt-actions{margin:14px auto 0}
    @media(max-width:900px){.receipt-layout{grid-template-columns:minmax(0,1fr) minmax(280px,.85fr);gap:12px}}
    @media(max-width:800px){.confirmation.receipt-page{padding:34px 20px 42px}.receipt-page>h1{font-size:clamp(2.15rem,6vw,3rem)}.receipt-layout{grid-template-columns:1fr;max-width:700px}.receipt-card{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:600px){.confirmation.receipt-page{padding:24px 16px 34px}.receipt-page>h1{font-size:clamp(2rem,9vw,2.6rem)}.receipt-summary{padding:17px}.receipt-summary-grid{grid-template-columns:1fr}.receipt-payment-heading{align-items:flex-start}.receipt-hold-alert{padding:11px 12px}.receipt-card{padding:16px;gap:11px 12px}.receipt-card b{overflow-wrap:anywhere}}
</style>
<section class="confirmation receipt-page">
    <span class="eyebrow">{{ $booking->payment_status === 'refunded' ? 'REFUND RECORD' : ($booking->payment_status === 'paid' ? 'PAYMENT RECEIPT' : ($booking->status === 'confirmed' ? 'BOOKING CONFIRMED' : 'REQUEST RECEIVED')) }}</span>
    <h1>{{ $expiredHold ? 'Reservation hold expired' : ($booking->status === 'confirmed' ? 'Your reservation is confirmed.' : ($booking->status === 'cancelled' ? 'This reservation was cancelled.' : 'Your reservation request is in.')) }}</h1>
    <p>@if($unavailable)This request no longer reserves the room. Choose another available stay or contact Carolina for help. This page keeps your booking and payment details for your records.@elseif($booking->status === 'confirmed')Carolina has confirmed your reservation.@else Keep your reference below. Staff confirmation is required before your stay is final.@endif</p>

    <div class="receipt-layout">
    <section class="receipt-summary receipt-payment-primary">
        <div class="receipt-payment-heading">
            <span>PAYMENT</span>
            <span class="receipt-status {{ $booking->payment_status ?? 'pending' }}">{{ $expiredHold ? 'Hold expired' : 'Payment ' . ucfirst($booking->payment_status ?? 'pending') }}</span>
        </div>
        <h2 class="receipt-payment-title">{{ $booking->payment_status === 'paid' ? 'Payment recorded' : ($booking->payment_status === 'refunded' ? 'Refund recorded' : ($unavailable ? 'Payment summary' : 'Complete your payment')) }}</h2>
        <div class="receipt-summary-grid">
            <div class="receipt-amount-card"><small>{{ $booking->payment_status === 'paid' ? 'Amount paid' : ($booking->payment_status === 'refunded' || $unavailable ? 'Booking total' : 'Amount to pay') }}</small><b>₱{{ number_format($booking->total_amount, 2) }}</b>@if($booking->discount_amount > 0)<small><s>₱{{ number_format($booking->original_amount, 2) }}</s> · {{ $booking->promo_code }} saved ₱{{ number_format($booking->discount_amount, 2) }}</small>@endif</div>
            <div>
                @if($paymentComplete)
                    <small>Payment method</small><b>{{ strtoupper($booking->payment_method ?? 'payment') }}</b><small>{{ $booking->payment_status === 'paid' ? 'Payment complete' : 'Refund complete' }}@if($booking->paid_at) · {{ $booking->paid_at->format('M j, Y g:i A') }}@endif</small>
                @elseif($unavailable)
                    <small>Payment</small><b>{{ ucfirst($booking->payment_status ?? 'pending') }}</b><small>Online checkout is unavailable for this request</small>
                @else
                    <small>Pay online with</small><b>GCash</b><small>Secure checkout via PayMongo</small>
                @endif
            </div>
        </div>
        @if($temporaryHold)
            <div class="receipt-hold-alert" role="note">
                <strong>{{ \App\Models\Booking::holdMinutes() }}-minute payment window</strong>
                <p>Your room is held for up to {{ \App\Models\Booking::holdMinutes() }} minutes, until <strong>{{ $holdDeadline->copy()->timezone('Asia/Manila')->format('M j, Y, g:i A') }} Philippine time</strong>. If payment is not recorded by then, the room will be released.</p>
                <p data-hold-countdown data-deadline="{{ $holdDeadline->timestamp }}" data-server-now="{{ now()->timestamp }}" data-remaining-seconds="{{ $remainingHoldSeconds }}" role="status" aria-live="polite">Time remaining: <strong data-countdown-value>{{ intdiv($remainingHoldSeconds, 60) }}m {{ str_pad((string) ($remainingHoldSeconds % 60), 2, '0', STR_PAD_LEFT) }}s</strong>.</p>
            </div>
        @elseif($canPayOnline && $booking->status === 'confirmed')
            <div class="receipt-hold-alert receipt-payment-window-info" role="note">
                <strong>The 30-minute hold does not apply to this confirmed booking</strong>
                <p>This limit applies only to unpaid requests awaiting confirmation.</p>
            </div>
        @endif
        @if($canPayOnline)
            <form id="paymongo-checkout-form" method="POST" action="{{ route('bookings.paymongo.start', $booking) }}" data-no-loading>
                @csrf
                <button class="button paymongo-submit" type="submit" aria-busy="false" data-loading-text="Opening the secure PayMongo checkout…">
                    <span class="paymongo-spinner" aria-hidden="true"></span>
                    <span class="paymongo-submit-label">Pay ₱{{ number_format($booking->total_amount, 2) }} with GCash</span>
                    <span class="paymongo-submit-arrow" aria-hidden="true">→</span>
                </button>
                <p id="paymongo-loading-message" class="paymongo-loading-message" role="status" aria-live="polite">Connecting to PayMongo securely… Keep this page open.</p>
                <small style="display:block;margin-top:9px;color:var(--muted)">For thesis testing only. No real money is collected.</small>
            </form>
        @endif
        @if($booking->payment_status === 'paid')
            <p class="receipt-note"><strong>Payment recorded.</strong> {{ $unavailable ? 'The room was not re-reserved. Contact staff to resolve this payment before making another one.' : ($booking->status === 'confirmed' ? 'Your reservation is confirmed.' : 'Your slot remains reserved while staff reviews your request.') }}</p>
            <p class="receipt-note">Paid via {{ strtoupper($booking->payment_method ?? 'payment') }}@if($booking->paid_at) on {{ $booking->paid_at->format('M j, Y g:i A') }}@endif.</p>
        @elseif($booking->payment_status === 'refunded')
            <p class="receipt-note">This page keeps your booking and payment details for your records.</p>
            <p class="receipt-note" style="margin-top:16px"><strong>Refund recorded.</strong> Your {{ strtoupper($booking->payment_method ?? 'payment') }} refund was recorded @if($booking->paid_at) on {{ $booking->paid_at->format('M j, Y g:i A') }}@endif.</p>
        @elseif($unavailable)
            <p class="receipt-note">Contact staff before making another payment for this request.</p>
        @else
            <p class="receipt-note">Please keep your reference number. Carolina will review your reservation and contact you with any next steps.</p>
        @endif
    </section>

    <div class="confirmation-card receipt-card">
        <h2 class="receipt-details-title">Booking information</h2>
        <div class="receipt-reference"><small>Booking reference</small><b>{{ $booking->reference }}</b></div>
        <div><small>Stay type</small><b>{{ $booking->booking_type === 'hourly' ? $booking->hours . ' hours' : $booking->nights . ' night' . ($booking->nights === 1 ? '' : 's') }}</b></div>
        <div><small>Room</small><b>{{ $booking->room->name }}</b></div>
        <div><small>Stay</small><b>{{ $booking->check_in_at && $booking->check_out_at ? $booking->check_in_at->format('M j, g A') . ' – ' . $booking->check_out_at->format('M j, g A') : $booking->check_in->format('M j') . ' – ' . $booking->check_out->format('M j, Y') }}</b></div>
        <div><small>Guests</small><b>{{ $booking->guests }} total{{ $booking->children_count ? ' · ' . $booking->children_count . ' child' . ($booking->children_count > 1 ? 'ren' : '') : '' }}</b></div>
        <div><small>Pets</small><b>{{ $booking->pets_count ? $booking->pets_count . ' pet' . ($booking->pets_count > 1 ? 's' : '') : 'None' }}</b></div>
    </div>
    </div>
    @include('bookings.partials.location-map', ['booking' => $booking])
    <div class="receipt-actions">
        <a class="button" href="{{ \App\Http\Controllers\StayController::link($booking) }}">Your arrival page</a>
        <a class="button button-outline" href="{{ route('home') }}">Back to home</a>
        <button class="button light" type="button" onclick="window.print()">Print / save receipt</button>
    </div>
</section>
<script>
    document.getElementById('paymongo-checkout-form')?.addEventListener('submit', async function (event) {
        event.preventDefault();

        const form = this;
        const button = form.querySelector('.paymongo-submit');
        const message = document.getElementById('paymongo-loading-message');
        if (!button || button.disabled) return;

        const label = button.querySelector('.paymongo-submit-label');
        const arrow = button.querySelector('.paymongo-submit-arrow');
        const originalLabel = label.textContent;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        label.textContent = 'Opening secure checkout…';
        arrow.hidden = true;
        message.textContent = 'Connecting to PayMongo securely…';
        message.classList.remove('error');
        message.classList.add('visible');

        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 20000);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            const result = await response.json().catch(() => ({}));

            const checkoutUrl = typeof result.checkout_url === 'string' ? new URL(result.checkout_url) : null;
            if (!response.ok || !checkoutUrl || checkoutUrl.protocol !== 'https:' || !/(^|\.)paymongo\.com$/i.test(checkoutUrl.hostname)) {
                throw new Error(result.message || 'We could not open the GCash checkout. Please try again.');
            }

            message.textContent = 'Checkout is ready. Redirecting securely…';
            window.location.assign(checkoutUrl.href);
            window.setTimeout(() => {
                if (window.location.href === form.action || window.location.pathname.endsWith('/receipt')) {
                    message.textContent = 'Checkout did not open automatically.';
                    const link = document.createElement('a');
                    link.href = checkoutUrl.href;
                    link.className = 'button button-outline';
                    link.textContent = 'Open secure checkout';
                    message.append(' ', link);
                    message.classList.add('error');
                    label.textContent = 'Checkout ready';
                    arrow.hidden = true;
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'false');
                }
            }, 8000);
        } catch (error) {
            const timedOut = error.name === 'AbortError';
            message.textContent = timedOut
                ? 'PayMongo did not respond in time. Refresh this receipt before trying again.'
                : (error.message || 'We could not open the GCash checkout. Please try again.');
            message.classList.add('error');
            label.textContent = timedOut ? 'Refresh receipt before retrying' : originalLabel;
            arrow.hidden = timedOut;
            button.disabled = timedOut;
            button.setAttribute('aria-busy', 'false');
        } finally {
            window.clearTimeout(timeout);
        }
    });
</script>
@if($temporaryHold)
<script>
    (() => {
        const countdown = document.querySelector('[data-hold-countdown]');
        const value = countdown?.querySelector('[data-countdown-value]');
        if (!countdown || !value) return;

        const deadline = Number(countdown.dataset.deadline);
        const serverNow = Number(countdown.dataset.serverNow);
        const browserStart = Date.now();
        const button = document.querySelector('#paymongo-checkout-form .paymongo-submit');
        let refreshing = false;

        const updateCountdown = () => {
            const elapsed = Math.floor((Date.now() - browserStart) / 1000);
            const remaining = Math.max(0, deadline - serverNow - elapsed);
            if (remaining === 0) {
                value.textContent = 'Time ended';
                countdown.append(' The server is checking the hold and payment status…');
                if (button) button.disabled = true;
                if (!refreshing) {
                    refreshing = true;
                    window.setTimeout(() => window.location.reload(), 1000);
                }
                return;
            }

            value.textContent = `${Math.floor(remaining / 60)}m ${String(remaining % 60).padStart(2, '0')}s`;
        };

        updateCountdown();
        window.setInterval(updateCountdown, 1000);
    })();
</script>
@endif
@endsection
