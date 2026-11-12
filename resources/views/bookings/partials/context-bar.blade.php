<nav class="booking-context-box" aria-label="Booking navigation">
    <x-site-back-link :href="$backUrl" :label="'Back to '.$backLabel" class="booking-context-back" />
    @if(!empty($roomName) || !empty($bookingReference))
        <div class="booking-context-identifiers" role="group" aria-label="Room and booking reference">
            @if(!empty($roomName))
                <div class="booking-context-item">
                    <small>Room</small>
                    <strong>{{ $roomName }}</strong>
                </div>
            @endif
            @if(!empty($bookingReference))
                <div class="booking-context-item booking-context-reference">
                    <small>Booking reference</small>
                    <strong>{{ $bookingReference }}</strong>
                </div>
            @endif
        </div>
    @endif
</nav>
