@extends('layouts.app')

@section('content')
<style>.receipt-status.paid{background:#dcfce7;color:#166534}.dark-mode .receipt-status.paid{background:#17432b;color:#b7f7c5}</style>
<style>.receipt-card{max-width:760px;margin:0 auto 18px}.receipt-summary{max-width:760px;margin:0 auto 28px;padding:22px;border:1px solid var(--line);border-radius:10px;background:#fff;text-align:left}.receipt-summary-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin:16px 0}.receipt-summary-grid div{padding:14px;border-radius:7px;background:var(--sand)}.receipt-summary-grid small{display:block;color:var(--muted);margin-bottom:5px}.receipt-summary-grid b{font-size:1.15rem}.receipt-status{display:inline-flex;padding:6px 10px;border-radius:20px;background:#fff0d4;color:#8d5700;font-size:.8rem;font-weight:700}.receipt-note{margin:0;color:var(--muted)}.paymongo-submit{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-width:220px}.paymongo-submit:disabled{cursor:wait;opacity:.82}.paymongo-spinner{display:none;width:15px;height:15px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:paymongo-spin .7s linear infinite}.paymongo-submit[aria-busy="true"] .paymongo-spinner{display:inline-block}.paymongo-loading-message{display:none;margin:9px 0 0;color:var(--muted);font-size:.9rem}.paymongo-loading-message.visible{display:block}.paymongo-loading-message.error{color:#b42318;font-weight:600}@keyframes paymongo-spin{to{transform:rotate(360deg)}}@media(prefers-reduced-motion:reduce){.paymongo-spinner{animation-duration:1.8s}}@media(max-width:600px){.receipt-summary{padding:17px}.receipt-summary-grid{grid-template-columns:1fr}.paymongo-submit{width:100%}}</style>
<section class="confirmation receipt-page">
    <span class="eyebrow">{{ $booking->payment_status === 'refunded' ? 'REFUND RECORD' : ($booking->payment_status === 'paid' ? 'PAYMENT RECEIPT' : ($booking->status === 'confirmed' ? 'BOOKING CONFIRMED' : 'REQUEST RECEIVED')) }}</span>
    <h1>{{ $booking->status === 'confirmed' ? 'Your reservation is confirmed.' : ($booking->status === 'cancelled' ? 'This reservation was cancelled.' : 'Your reservation request is in.') }}</h1>
    <p>@if($booking->status === 'confirmed')Carolina has confirmed your reservation.@elseif($booking->status === 'cancelled')This reservation was cancelled. This page keeps its booking and payment details for your records.@else Carolina will check availability and contact you.@endif @if(! in_array($booking->payment_status, ['paid', 'refunded'], true) && $booking->status !== 'cancelled') You may also complete a safe PayMongo GCash test payment for your presentation.@endif</p>

    <div class="confirmation-card receipt-card">
        <div><small>Booking reference</small><b>{{ $booking->reference }}</b></div>
        <div><small>Stay type</small><b>{{ $booking->booking_type === 'hourly' ? $booking->hours . ' hours' : $booking->nights . ' night' . ($booking->nights === 1 ? '' : 's') }}</b></div>
        <div><small>Room</small><b>{{ $booking->room->name }}</b></div>
        <div><small>Stay</small><b>{{ $booking->check_in_at && $booking->check_out_at ? $booking->check_in_at->format('M j, g A') . ' – ' . $booking->check_out_at->format('M j, g A') : $booking->check_in->format('M j') . ' – ' . $booking->check_out->format('M j, Y') }}</b></div>
        <div><small>Guests</small><b>{{ $booking->guests }} total{{ $booking->children_count ? ' · ' . $booking->children_count . ' child' . ($booking->children_count > 1 ? 'ren' : '') : '' }}</b></div>
        <div><small>Pets</small><b>{{ $booking->pets_count ? $booking->pets_count . ' pet' . ($booking->pets_count > 1 ? 's' : '') : 'None' }}</b></div>
    </div>

    <section class="receipt-summary">
        <span class="receipt-status {{ $booking->payment_status ?? 'pending' }}">{{ ucfirst($booking->status) }} · Payment {{ ucfirst($booking->payment_status ?? 'pending') }}</span>
        <div class="receipt-summary-grid">
            <div><small>Booking total</small><b>₱{{ number_format($booking->total_amount, 2) }}</b>@if($booking->discount_amount > 0)<small><s>₱{{ number_format($booking->original_amount, 2) }}</s> · {{ $booking->promo_code }} saved ₱{{ number_format($booking->discount_amount, 2) }}</small>@endif</div>
            <div><small>Payment</small><b>{{ ucfirst($booking->payment_status ?? 'pending') }}</b><small>{{ strtoupper($booking->payment_method ?? 'cash') }}@if($booking->paid_at) · {{ $booking->paid_at->format('M j, Y g:i A') }}@endif</small></div>
        </div>
        <p class="receipt-note">@if($booking->status === 'confirmed')Your reservation is confirmed. Keep this receipt with your booking reference for your records.@else Please keep your reference number. Carolina will review your reservation and contact you with any next steps.@endif</p>
        @if(! in_array($booking->payment_status, ['paid', 'refunded'], true) && $booking->status !== 'cancelled')
            <form id="paymongo-checkout-form" method="POST" action="{{ route('bookings.paymongo.start', $booking) }}" data-no-loading style="margin-top:16px">
                @csrf
                <button class="button paymongo-submit" type="submit" aria-busy="false" data-loading-text="Opening the secure PayMongo checkout…">
                    <span class="paymongo-spinner" aria-hidden="true"></span>
                    <span class="paymongo-submit-label">Pay with GCash test mode</span>
                    <span class="paymongo-submit-arrow" aria-hidden="true">→</span>
                </button>
                <p id="paymongo-loading-message" class="paymongo-loading-message" role="status" aria-live="polite">Connecting to PayMongo securely… Keep this page open.</p>
                <small style="display:block;margin-top:9px;color:var(--muted)">For thesis testing only. No real money is collected.</small>
            </form>
        @elseif($booking->payment_status === 'paid')
            <p class="receipt-note" style="margin-top:16px"><strong>Payment recorded.</strong> Your payment is marked paid via {{ strtoupper($booking->payment_method ?? 'payment') }}@if($booking->paid_at) on {{ $booking->paid_at->format('M j, Y g:i A') }}@endif.</p>
        @elseif($booking->payment_status === 'refunded')
            <p class="receipt-note" style="margin-top:16px"><strong>Refund recorded.</strong> Your {{ strtoupper($booking->payment_method ?? 'payment') }} refund was recorded@if($booking->paid_at) on {{ $booking->paid_at->format('M j, Y g:i A') }}@endif.</p>
        @endif
    </section>
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
@endsection
