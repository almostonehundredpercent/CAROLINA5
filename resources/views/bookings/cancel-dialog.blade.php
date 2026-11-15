@push('styles')
<link rel="stylesheet" href="{{ asset('css/booking-cancel-dialog.css') }}?v={{ filemtime(public_path('css/booking-cancel-dialog.css')) }}">
@endpush

<dialog class="booking-cancel-dialog" data-booking-cancel-dialog aria-labelledby="booking-cancel-title" aria-describedby="booking-cancel-copy booking-cancel-policy booking-cancel-note">
    <section class="booking-cancel-card">
        <button class="booking-cancel-close" type="button" data-booking-cancel-close aria-label="Close confirmation">×</button>
        <span class="booking-cancel-icon" aria-hidden="true">!</span>
        <p class="booking-cancel-kicker">CONFIRM CANCELLATION</p>
        <h2 id="booking-cancel-title">Cancel this booking?</h2>
        <p class="booking-cancel-copy" id="booking-cancel-copy">Are you sure you want to cancel your reservation? The room dates will be made available again.</p>

        <div class="booking-cancel-policy" id="booking-cancel-policy" aria-label="Cancellation and refund policy">
            <p><strong>At least 3 days before check-in</strong><span>Eligible for a full refund</span></p>
            <p><strong>Less than 3 days before check-in</strong><span>You can cancel, but it is non-refundable</span></p>
        </div>
        <p class="booking-cancel-note" id="booking-cancel-note">Any eligible refund is handled separately by Carolina.</p>

        <div class="booking-cancel-actions">
            <button class="booking-cancel-keep" type="button" data-booking-cancel-close autofocus>Keep my booking</button>
            <button class="booking-cancel-confirm" type="button" data-booking-cancel-accept>Yes, cancel booking</button>
        </div>
    </section>
</dialog>
<script src="{{ asset('js/booking-cancel-dialog.js') }}?v={{ filemtime(public_path('js/booking-cancel-dialog.js')) }}" defer></script>
