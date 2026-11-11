<nav class="booking-context-box" aria-label="Booking navigation">
    <a class="booking-context-back" href="{{ $backUrl }}">
        <span aria-hidden="true">←</span>
        <span>Back to {{ $backLabel }}</span>
    </a>
    <div class="booking-context-identifiers">
        <div class="booking-context-item">
            <small>Room</small>
            <strong>{{ $roomName }}</strong>
        </div>
        @if(!empty($bookingReference))
            <div class="booking-context-item booking-context-reference">
                <small>Booking reference</small>
                <strong>{{ $bookingReference }}</strong>
            </div>
        @endif
    </div>
</nav>
