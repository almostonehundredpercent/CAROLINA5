@push('styles')
<link rel="stylesheet" href="{{ asset('css/booking-cancel-dialog.css') }}?v={{ filemtime(public_path('css/booking-cancel-dialog.css')) }}">
@endpush

<dialog class="booking-cancel-dialog" data-booking-cancel-dialog aria-labelledby="booking-cancel-title" aria-describedby="booking-cancel-copy booking-cancel-booking booking-cancel-policy booking-cancel-note">
    <section class="booking-cancel-card">
        <button class="booking-cancel-close" type="button" data-booking-cancel-close aria-label="Close confirmation">×</button>
        <span class="booking-cancel-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 3.5 21 20H3l9-16.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9v4.5m0 3h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
        <p class="booking-cancel-kicker">BOOKING CANCELLATION</p>
        <h2 id="booking-cancel-title">Cancel this booking?</h2>
        <p class="booking-cancel-copy" id="booking-cancel-copy">Are you sure you want to cancel your reservation? These dates will be made available again.</p>

        <div class="booking-cancel-booking" id="booking-cancel-booking">
            <span class="booking-cancel-room-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M3.5 20.5h17M5 20V6.5L12 3l7 3.5V20M9 20v-5h6v5M8.5 9h.01M15.5 9h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <span class="booking-cancel-booking-copy"><strong data-cancel-room>Selected room</strong><span><span data-cancel-check-in>Check-in</span> <span aria-hidden="true">→</span> <span data-cancel-check-out>Check-out</span></span></span>
            <span class="booking-cancel-reference">Ref <b data-cancel-reference>—</b></span>
        </div>

        <div class="booking-cancel-policy" id="booking-cancel-policy" aria-label="Cancellation and refund policy">
            <div class="booking-cancel-policy-item">
                <span class="booking-cancel-policy-mark refundable" aria-hidden="true">✓</span>
                <span><strong>3 or more days before check-in</strong><small>Eligible for a full refund</small></span>
            </div>
            <div class="booking-cancel-policy-item">
                <span class="booking-cancel-policy-mark nonrefundable" aria-hidden="true">!</span>
                <span><strong>Less than 3 days before check-in</strong><small>Cancellation allowed, but no refund</small></span>
            </div>
        </div>
        <p class="booking-cancel-note" id="booking-cancel-note">Any eligible refund is handled separately by Carolina.</p>

        <div class="booking-cancel-actions">
            <button class="booking-cancel-keep" type="button" data-booking-cancel-close autofocus>Keep booking</button>
            <button class="booking-cancel-confirm" type="button" data-booking-cancel-accept>Confirm cancellation</button>
        </div>
    </section>
</dialog>
<script src="{{ asset('js/booking-cancel-dialog.js') }}?v={{ filemtime(public_path('js/booking-cancel-dialog.js')) }}" defer></script>
