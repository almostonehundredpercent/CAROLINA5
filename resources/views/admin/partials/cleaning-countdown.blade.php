@php
    $minutesRemaining = intdiv($remainingSeconds, 60);
    $secondsRemaining = $remainingSeconds % 60;
@endphp
<div class="cleaning-countdown" role="timer" aria-label="Cleaning time remaining" data-cleaning-countdown data-remaining-seconds="{{ $remainingSeconds }}">
    <span class="cleaning-countdown-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
    </span>
    <span class="cleaning-countdown-copy">
        <b data-countdown-label>Cleaning time remaining</b>
        <span data-countdown-complete hidden>Cleaning window complete — check the room's status.</span>
    </span>
    <time class="cleaning-countdown-clock" data-countdown-value>{{ sprintf('%02d:%02d', $minutesRemaining, $secondsRemaining) }}</time>
</div>
