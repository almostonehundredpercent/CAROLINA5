<div class="carol-recommendation-deck" data-carol-deck>
    <div class="carol-reminder-status" role="status">
        <span data-carol-reminder-copy>{{ ($paymentReminderEnabled ?? false) ? 'Payment reminders are on. CAROL sends one email five minutes before an unpaid hold expires.' : 'Payment reminders are off.' }}</span>
        <form method="POST" action="{{ route('admin.decision-support.payment-reminder', 'disable') }}" data-carol-action data-carol-reminder-state="on" @if(!($paymentReminderEnabled ?? false)) hidden @endif>
            @csrf
            <button class="carol-action-button" type="submit">Turn off</button>
        </form>
        <form method="POST" action="{{ route('admin.decision-support.payment-reminder', 'enable') }}" data-carol-action data-carol-reminder-state="off" @if($paymentReminderEnabled ?? false) hidden @endif>
            @csrf
            <button class="carol-action-button is-primary" type="submit">Turn on reminders</button>
        </form>
    </div>

    @forelse($signals as $index => $signal)
        @php($kind = $signal['kind'] ?? null)
        @php
            $confirmMessage = match ($kind) {
            'unpaid_hold_payment_reminder' => 'This will turn on one automatic email reminder five minutes before an unpaid booking hold expires.',
            'elevated_cancellations' => 'CAROL will show recent cancelled bookings here for review. No booking will be changed.',
            'no_rooms_available' => 'CAROL will show today’s arrivals here for review. No room or booking details will be changed.',
            'repeated_maintenance' => 'CAROL will show this room’s maintenance records here for review. Its status will not change.',
            'high_booking_demand' => 'CAROL will show this room’s recent bookings here for review. No bookings or rates will change.',
            'zero_turnover_gap' => 'CAROL will show both bookings for review here. It will not change their check-out or check-in times.',
            default => 'CAROL will show the matching records here for review. No booking details will be changed.',
            };
        @endphp
        <section class="carol-recommendation-card" data-carol-card aria-label="CAROL recommendation" @if($index > 0) hidden @endif>
            <div class="carol-recommendation-heading"><span aria-hidden="true">✦</span><span>CAROL · DECISION SUPPORT</span></div>
            <h3>{{ $signal['title'] }}</h3>
            <p>{{ $signal['evidence'] }}</p>
            @if(!empty($signal['recommendation']))<p class="carol-recommendation-copy">{{ $signal['recommendation'] }}</p>@endif
            @if(!empty($signal['rule']))<small>{{ $signal['rule'] }}</small>@endif

            @if($kind)
                <div class="carol-recommendation-actions" data-carol-actions>
                    <form method="POST" action="{{ $kind === 'unpaid_hold_payment_reminder' ? route('admin.decision-support.payment-reminder', 'apply') : route('admin.decision-support.recommendations.confirm', $kind) }}" data-carol-action data-carol-confirm-suggestion data-carol-confirm-message="{{ $confirmMessage }}">
                        @csrf
                        @if(!empty($signal['room_id']))<input type="hidden" name="room_id" value="{{ $signal['room_id'] }}">@endif
                        @if($kind !== 'unpaid_hold_payment_reminder')<input type="hidden" name="signature" value="{{ $signal['signature'] }}">@endif
                        <button class="carol-action-button is-primary" type="submit">Confirm suggestion</button>
                    </form>
                    @if($kind === 'unpaid_hold_payment_reminder')
                        <form method="POST" action="{{ route('admin.decision-support.payment-reminder', 'dismiss') }}" data-carol-action>
                            @csrf
                            <button class="carol-action-button" type="submit">Dismiss</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.decision-support.recommendations.dismiss', $kind) }}" data-carol-action>
                            @csrf
                            <input type="hidden" name="signature" value="{{ $signal['signature'] }}">
                            @if(!empty($signal['room_id']))<input type="hidden" name="room_id" value="{{ $signal['room_id'] }}">@endif
                            <button class="carol-action-button" type="submit">Dismiss</button>
                        </form>
                    @endif
                </div>
                <div class="carol-action-result" data-carol-result role="status" aria-live="polite" hidden></div>
                <button class="carol-action-button carol-next-button" type="button" data-carol-next hidden>Next recommendation</button>
            @else
                <small class="carol-readonly-note">This signal is temporarily read-only while dashboard data is restored.</small>
                <button class="carol-action-button" type="button" data-carol-next>Dismiss</button>
            @endif
        </section>
    @empty
        <p class="empty-copy" data-carol-no-recommendations>No operational rules were triggered by the current data.</p>
    @endforelse

    @if($signals->isNotEmpty())
        <p class="empty-copy" data-carol-caught-up hidden>No more CAROL recommendations need review.</p>
    @endif

    <dialog class="carol-confirm-dialog" data-carol-confirm-dialog aria-labelledby="carol-confirm-title">
        <div class="carol-confirm-dialog-content">
            <p class="admin-kicker">CAROL · CONFIRM ACTION</p>
            <h2 id="carol-confirm-title">Before you continue</h2>
            <p class="carol-confirm-recommendation" data-carol-confirm-recommendation></p>
            <p data-carol-confirm-message></p>
            <div class="carol-confirm-actions">
                <button class="carol-action-button" type="button" data-carol-confirm-cancel>Go back</button>
                <button class="carol-action-button is-primary" type="button" data-carol-confirm-continue>Continue</button>
            </div>
        </div>
    </dialog>
</div>

@once
    <script src="{{ asset('js/carol-recommendations.js') }}?v={{ filemtime(public_path('js/carol-recommendations.js')) }}" defer></script>
@endonce
