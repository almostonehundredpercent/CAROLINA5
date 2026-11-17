@push('styles')
<link rel="stylesheet" href="{{ asset('css/booking-cancel-dialog.css') }}?v={{ filemtime(public_path('css/booking-cancel-dialog.css')) }}">
@endpush

<dialog class="booking-cancel-dialog" data-booking-cancel-dialog aria-labelledby="booking-cancel-title">
    <section class="booking-cancel-card">
        <h2 id="booking-cancel-title">Request to cancel this booking?</h2>
        <div class="booking-cancel-actions">
            <button class="booking-cancel-no" type="button" data-booking-cancel-close autofocus>No</button>
            <button class="booking-cancel-yes" type="button" data-booking-cancel-accept>Yes</button>
        </div>
    </section>
</dialog>
<script src="{{ asset('js/booking-cancel-dialog.js') }}?v={{ filemtime(public_path('js/booking-cancel-dialog.js')) }}" defer></script>
