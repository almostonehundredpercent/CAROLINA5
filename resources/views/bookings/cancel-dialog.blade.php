@push('styles')
<link rel="stylesheet" href="{{ asset('css/booking-cancel-dialog.css') }}?v={{ filemtime(public_path('css/booking-cancel-dialog.css')) }}">
@endpush

<dialog class="booking-cancel-dialog" data-booking-cancel-dialog aria-labelledby="booking-cancel-title" aria-describedby="booking-cancel-refund-policy">
    <section class="booking-cancel-card">
        <h2 id="booking-cancel-title">Cancel this booking now?</h2>
        <p id="booking-cancel-refund-policy"><strong>3-day refund rule:</strong> Cancel <strong>at least 72 hours before check-in</strong> to qualify for a refund of eligible payments. Cancellations made less than 72 hours before check-in are <strong>non-refundable</strong>.</p>
        <div class="booking-cancel-actions">
            <button class="booking-cancel-yes" type="button" data-booking-cancel-accept>Yes</button>
            <button class="booking-cancel-no" type="button" data-booking-cancel-close autofocus>No</button>
        </div>
    </section>
</dialog>
<script src="{{ asset('js/booking-cancel-dialog.js') }}?v={{ filemtime(public_path('js/booking-cancel-dialog.js')) }}" defer></script>
