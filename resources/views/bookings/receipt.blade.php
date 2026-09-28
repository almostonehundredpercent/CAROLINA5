@extends('layouts.app')

@section('content')
<style>.receipt-card{max-width:760px;margin:0 auto 18px}.receipt-summary{max-width:760px;margin:0 auto 28px;padding:22px;border:1px solid var(--line);border-radius:10px;background:#fff;text-align:left}.receipt-summary-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin:16px 0}.receipt-summary-grid div{padding:14px;border-radius:7px;background:var(--sand)}.receipt-summary-grid small{display:block;color:var(--muted);margin-bottom:5px}.receipt-summary-grid b{font-size:1.15rem}.receipt-status{display:inline-flex;padding:6px 10px;border-radius:20px;background:#fff0d4;color:#8d5700;font-size:.8rem;font-weight:700}.receipt-note{margin:0;color:var(--muted)}.paymongo-submit{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-width:220px}.paymongo-submit:disabled{cursor:wait;opacity:.82}.paymongo-spinner{display:none;width:15px;height:15px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:paymongo-spin .7s linear infinite}.paymongo-submit[aria-busy="true"] .paymongo-spinner{display:inline-block}.paymongo-loading-message{display:none;margin:9px 0 0;color:var(--muted);font-size:.82rem}.paymongo-loading-message.visible{display:block}@keyframes paymongo-spin{to{transform:rotate(360deg)}}@media(prefers-reduced-motion:reduce){.paymongo-spinner{animation-duration:1.8s}}@media(max-width:600px){.receipt-summary{padding:17px}.receipt-summary-grid{grid-template-columns:1fr}.paymongo-submit{width:100%}}</style>
<section class="confirmation receipt-page">
    <span class="eyebrow">REQUEST RECEIVED</span>
    <h1>Your reservation request is in.</h1>
    <p>Carolina will check availability and contact you. You may also complete a safe PayMongo GCash test payment for your presentation.</p>

    <div class="confirmation-card receipt-card">
        <div><small>Booking reference</small><b>{{ $booking->reference }}</b></div>
        <div><small>Stay type</small><b>{{ $booking->booking_type === 'hourly' ? $booking->hours . ' hours' : $booking->nights . ' night' . ($booking->nights === 1 ? '' : 's') }}</b></div>
        <div><small>Room</small><b>{{ $booking->room->name }}</b></div>
        <div><small>Stay</small><b>{{ $booking->check_in_at && $booking->check_out_at ? $booking->check_in_at->format('M j, g A') . ' – ' . $booking->check_out_at->format('M j, g A') : $booking->check_in->format('M j') . ' – ' . $booking->check_out->format('M j, Y') }}</b></div>
        <div><small>Guests</small><b>{{ $booking->guests }} total{{ $booking->children_count ? ' · ' . $booking->children_count . ' child' . ($booking->children_count > 1 ? 'ren' : '') : '' }}</b></div>
        <div><small>Pets</small><b>{{ $booking->pets_count ? $booking->pets_count . ' pet' . ($booking->pets_count > 1 ? 's' : '') : 'None' }}</b></div>
    </div>

    <section class="receipt-summary">
        <span class="receipt-status">Awaiting staff review</span>
        <div class="receipt-summary-grid">
            <div><small>Booking total</small><b>₱{{ number_format($booking->total_amount, 2) }}</b></div>
            <div><small>Next step</small><b>Staff confirmation</b></div>
        </div>
        <p class="receipt-note">Please keep your reference number. Carolina will review your reservation and contact you with any next steps.</p>
        @if($booking->payment_status !== 'paid')
            <form id="paymongo-checkout-form" method="POST" action="{{ route('bookings.paymongo.start', $booking) }}" style="margin-top:16px">
                @csrf
                <button class="button paymongo-submit" type="submit" aria-busy="false">
                    <span class="paymongo-spinner" aria-hidden="true"></span>
                    <span class="paymongo-submit-label">Pay with GCash test mode</span>
                    <span class="paymongo-submit-arrow" aria-hidden="true">→</span>
                </button>
                <p id="paymongo-loading-message" class="paymongo-loading-message" role="status" aria-live="polite">Connecting to PayMongo securely… Keep this page open.</p>
                <small style="display:block;margin-top:9px;color:var(--muted)">For thesis testing only. No real money is collected.</small>
            </form>
        @else
            <p class="receipt-note" style="margin-top:16px"><strong>GCash test payment confirmed.</strong> Staff review is still required.</p>
        @endif
    </section>
    <div class="receipt-actions">
        <a class="button" href="{{ \App\Http\Controllers\StayController::link($booking) }}">Your arrival page</a>
        <a class="button button-outline" href="{{ route('home') }}">Back to home</a>
        <button class="button light" type="button" onclick="window.print()">Print / save receipt</button>
    </div>
</section>
<script>
    document.getElementById('paymongo-checkout-form')?.addEventListener('submit', function (event) {
        const button = this.querySelector('.paymongo-submit');
        if (!button || button.disabled) {
            event.preventDefault();
            return;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.querySelector('.paymongo-submit-label').textContent = 'Opening secure checkout…';
        button.querySelector('.paymongo-submit-arrow').hidden = true;
        document.getElementById('paymongo-loading-message')?.classList.add('visible');
    });
</script>
@endsection
