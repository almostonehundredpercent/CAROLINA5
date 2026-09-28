@php
    $stay = $room->display_booking;
    $block = $room->display_block;
    $stayLabel = $stay ? ($stay->check_in_at?->lte(now()) && $stay->check_out_at?->gt(now()) ? 'Guest in house' : 'Next arrival') : 'Guest stay';
    $blockLabel = $block ? ($block->starts_at->lte(now()) ? 'Current task' : 'Next task') : 'Operations';
    $operationStart = now()->second(0)->minute(now()->minute >= 30 ? 30 : 0);
    if ($operationStart->lt(now())) $operationStart->addMinutes(30);
    $operationEnd = $operationStart->copy()->addHour();
    $timeOptions = range(0, 47);
@endphp

<article
    class="room-card room-detail-card {{ $room->display_status }}"
    id="room-detail-{{ $slot }}"
    data-room-detail="{{ $slot }}"
    aria-labelledby="room-detail-title-{{ $slot }}"
    hidden
>
    <div class="room-detail-heading">
        <span class="room-detail-number">Room {{ $slot }}</span>
        <button class="room-detail-close" type="button" aria-label="Close room details">×</button>
    </div>
    <div class="room-card-main">
        <div class="room-card-top">
            <div>
                <h2 class="room-card-title" id="room-detail-title-{{ $slot }}">{{ $room->name }}</h2>
                <p class="room-card-meta">{{ $room->room_type }} · Sleeps {{ $room->guests }}</p>
                <span class="room-card-rate">₱{{ number_format($room->price_per_night) }} {{ $room->rate_label }}</span>
            </div>
            <span class="room-status {{ $room->display_status }}">{{ ucfirst($room->display_status) }}</span>
        </div>
        <div class="room-card-schedule">
            <div class="schedule-item {{ $stay ? '' : 'empty' }}">
                <small>{{ $stayLabel }}</small>
                @if($stay)
                    <b>{{ $stay->guest_name ?? $stay->user?->name ?? 'Guest booking' }}</b>
                    <span>{{ $stay->check_in_at?->format('M j, g A') }} – {{ $stay->check_out_at?->format('M j, g A') }}</span>
                @else
                    <b>No guest stay scheduled</b>
                @endif
            </div>
            <div class="schedule-item {{ $block ? '' : 'empty' }}">
                <small>{{ $blockLabel }}</small>
                @if($block)
                    <b>{{ ucfirst($block->status) }}</b>
                    <span>{{ $block->starts_at->format('M j, g A') }} – {{ $block->ends_at->format('M j, g A') }}</span>
                @else
                    <b>No cleaning or maintenance planned</b>
                @endif
            </div>
        </div>
        @if(auth()->user()->isAdmin())
            <div class="room-card-actions">
                <a class="room-action-link" href="{{ route('admin.rooms.edit', $room) }}">Edit details</a>
                <form method="POST" action="{{ route('admin.rooms.archive', $room) }}" onsubmit="return confirm('Archive this room? Its booking history will be kept.')">
                    @csrf
                    @method('DELETE')
                    <button class="room-archive-button" type="submit">Archive</button>
                </form>
            </div>
        @endif
    </div>
    <details class="room-manage">
        <summary>Manage cleaning or maintenance</summary>
        <form method="POST" action="{{ route('admin.rooms.status', $room) }}" class="room-operation-form">
            @csrf
            @method('PATCH')
            <div class="operation-form-grid">
                <div class="operation-field full">
                    <label for="status-{{ $room->id }}">Operation type</label>
                    <select id="status-{{ $room->id }}" name="operational_status">
                        <option value="cleaning">Cleaning</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="available">Clear an active block now</option>
                    </select>
                </div>
            </div>
            <div class="operation-time-panel">
                <section class="operation-moment">
                    <p class="operation-moment-title">Starts</p>
                    <div class="operation-moment-fields">
                        <input id="start-date-{{ $room->id }}" name="operational_start_date" class="operation-start-date" type="date" min="{{ $operationStart->format('Y-m-d') }}" value="{{ $operationStart->format('Y-m-d') }}" aria-label="Operation start date">
                        <select name="operational_start_time" class="operation-start-time" aria-label="Operation start time">
                            @foreach($timeOptions as $minutes)
                                @php($value = sprintf('%02d:%02d', intdiv($minutes, 2), $minutes % 2 ? 30 : 0))
                                <option value="{{ $value }}" @selected($value === $operationStart->format('H:i'))>{{ \Carbon\Carbon::createFromFormat('H:i', $value)->format('g:i A') }}</option>
                            @endforeach
                        </select>
                    </div>
                </section>
                <section class="operation-moment">
                    <p class="operation-moment-title">Ends</p>
                    <div class="operation-moment-fields">
                        <input id="end-date-{{ $room->id }}" name="operational_end_date" class="operation-end-date" type="date" min="{{ $operationStart->format('Y-m-d') }}" value="{{ $operationEnd->format('Y-m-d') }}" aria-label="Operation end date">
                        <select name="operational_end_time" class="operation-end-time" aria-label="Operation end time">
                            @foreach($timeOptions as $minutes)
                                @php($value = sprintf('%02d:%02d', intdiv($minutes, 2), $minutes % 2 ? 30 : 0))
                                <option value="{{ $value }}" @selected($value === $operationEnd->format('H:i'))>{{ \Carbon\Carbon::createFromFormat('H:i', $value)->format('g:i A') }}</option>
                            @endforeach
                        </select>
                    </div>
                </section>
            </div>
            <div class="schedule-timer" aria-live="polite">
                <div><div class="schedule-timer-label">Scheduled length</div><div class="schedule-timer-value">1 hour</div></div>
                <div class="duration-presets" aria-label="Quick duration">
                    <button type="button" class="duration-button is-selected" data-minutes="60">1 hr</button>
                    <button type="button" class="duration-button" data-minutes="120">2 hrs</button>
                    <button type="button" class="duration-button" data-minutes="240">4 hrs</button>
                    <button type="button" class="duration-button" data-minutes="480">8 hrs</button>
                    <button type="button" class="duration-button" data-minutes="1440">24 hrs</button>
                </div>
            </div>
            <div class="operation-form-grid">
                <div class="operation-field full">
                    <label for="note-{{ $room->id }}">Staff note (optional)</label>
                    <input id="note-{{ $room->id }}" name="notes" maxlength="255" placeholder="For example: deep clean after checkout">
                </div>
            </div>
            <p class="operation-help">Choose a quick duration to set a clean end time automatically, or adjust either date and time for a custom task.</p>
            <div class="operation-footer">
                @if(auth()->user()->isAdmin())
                    <label class="override-note"><input type="checkbox" name="force_override" value="1"> Manager override if this overlaps a confirmed stay.</label>
                @else
                    <span class="override-note">Confirmed stays cannot be overridden by your role.</span>
                @endif
                <button class="operation-save" type="submit">Save task</button>
            </div>
        </form>
    </details>
</article>
