<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reports - Carolina Admin</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}"><style>.report-line{max-width:720px;margin-inline:auto}.report-line svg{height:auto;aspect-ratio:600/220}.report-line .chart-labels{max-width:720px;margin-inline:auto}.booking-filters{display:flex;flex-wrap:wrap;gap:8px;align-items:end;margin:0 0 18px}.booking-filters label{display:grid;gap:4px;font-size:11px;font-weight:700;color:var(--muted)}.booking-filters input{border:1px solid var(--line);border-radius:6px;padding:8px;background:#fff;font:inherit}.booking-filters button{border:0;border-radius:6px;padding:9px 12px;background:var(--orange);color:#fff;font-weight:700;cursor:pointer}.booking-filters small{color:var(--muted);font-size:11px}.booking-filters .report-range-error{color:#b42318;font-weight:700;flex-basis:100%}.booking-filters #report-range-hint{flex-basis:100%}</style></head>
<body><div class="admin-shell">@include('admin.partials.sidebar')
<main class="admin-main"><header class="admin-topbar"><div><p class="admin-kicker">ANALYTICS</p><h1>Reports</h1><p class="admin-subtitle">Track collected payments, bookings, and guest activity.</p></div><div class="admin-user"><span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><div><b>{{ auth()->user()->name }}</b><small>Administrator</small></div></div></header>
<section class="report-tabs"><a class="{{ $metric === 'payments' ? 'active' : '' }}" href="{{ route('admin.reports', ['metric' => 'payments', 'period' => $period] + $range) }}">Payments collected</a><a class="{{ $metric === 'bookings' ? 'active' : '' }}" href="{{ route('admin.reports', ['metric' => 'bookings', 'period' => $period] + $range) }}">Bookings</a><a class="{{ $metric === 'guests' ? 'active' : '' }}" href="{{ route('admin.reports', ['metric' => 'guests', 'period' => $period] + $range) }}">Guests</a><form method="GET" class="report-period"><input type="hidden" name="metric" value="{{ $metric }}"><button name="period" value="daily" class="{{ $period === 'daily' ? 'active' : '' }}">D</button><button name="period" value="weekly" class="{{ $period === 'weekly' ? 'active' : '' }}">W</button><button name="period" value="monthly" class="{{ $period === 'monthly' ? 'active' : '' }}">M</button><button name="period" value="yearly" class="{{ $period === 'yearly' ? 'active' : '' }}">Y</button></form></section>
@php($rangeError = $errors->first('from') ?: $errors->first('to'))
<form id="report-range-form" method="GET" class="booking-filters" aria-label="Custom report range"><input type="hidden" name="metric" value="{{ $metric }}"><input type="hidden" name="period" value="custom"><label>From <input type="date" name="from" required value="{{ old('from', $range['from'] ?? '') }}"></label><label>To <input type="date" name="to" required value="{{ old('to', $range['to'] ?? '') }}"></label><button type="submit">Custom range</button><small id="report-range-error" class="report-range-error" role="alert" aria-live="polite" @if(!$rangeError) hidden @endif>{{ $rangeError }}</small><small id="report-range-hint">Choose a start date; the end date will fill in 3 months later. You can shorten it.</small></form>
@if($metric === 'payments')<section class="report-booking-metrics"><article><small>Paid in period</small><strong>₱{{ number_format($financialMetrics['period_ledger']['paid'], 2) }}</strong><em>Recorded paid payment entries</em></article><article><small>Refunded in period</small><strong>₱{{ number_format($financialMetrics['period_ledger']['refunds'], 2) }}</strong><em>Recorded refund entries</em></article><article><small>Net collected</small><strong>₱{{ number_format($financialMetrics['period_ledger']['net_collected'], 2) }}</strong><em>Paid minus refunds in this period</em></article><article><small>Outstanding balance</small><strong>₱{{ number_format($financialMetrics['outstanding_balance'], 2) }}</strong><em>Current balance on bookings created in this period</em></article></section>@endif
@if($metric === 'bookings')<section class="report-booking-metrics"><article><small>Bookings created</small><strong>{{ $bookingReportMetrics['total'] }}</strong><em>In the selected period</em></article><article><small>Cancelled bookings</small><strong>{{ $bookingReportMetrics['cancelled'] }}</strong><em>Among bookings created in this period</em></article><article><small>Rooms booked</small><strong>{{ $bookingReportMetrics['roomsBooked'] }}</strong><em>Distinct rooms in non-cancelled bookings</em></article><article><small>Stays checked out</small><strong>{{ $bookingReportMetrics['completed'] }}</strong><em>Check-outs recorded in this period</em></article></section>@endif
@if($metric === 'guests')<section class="report-booking-metrics guest-report-metrics"><article><small>Guests in period</small><strong>{{ $guestReportMetrics['total'] }}</strong><em>Unique guests with a booking in this period</em></article><article><small>First-time guests</small><strong>{{ $guestReportMetrics['new'] }}</strong><em>No earlier booking on record</em></article><article><small>Returning guests</small><strong>{{ $guestReportMetrics['returning'] }}</strong><em>Had a booking before this period</em></article><article><small>Average booked duration</small><strong>{{ $guestReportMetrics['averageDurationHours'] === null ? '—' : number_format($guestReportMetrics['averageDurationHours'], 1).' hours' }}</strong><em>Recorded times or saved duration; unknown stays excluded</em></article></section>@endif
@php($maxValue = max(1, $chartValues->max()))<section class="panel report-main-card"><p class="admin-kicker">{{ strtoupper($period) }} {{ strtoupper($chartTitle) }}</p><strong class="report-total">{{ $metric === 'payments' ? '₱' . number_format($total) : number_format($total) }}</strong><div class="line-graph report-line"><svg viewBox="0 0 600 220" preserveAspectRatio="none"><line x1="0" y1="200" x2="600" y2="200" class="graph-axis"/><line x1="0" y1="100" x2="600" y2="100" class="graph-grid"/><line x1="0" y1="35" x2="600" y2="35" class="graph-grid"/><polyline class="booking-line" points="@foreach($chartValues as $index => $value){{ $chartValues->count() > 1 ? ($index / ($chartValues->count() - 1) * 600) : 300 }},{{ 200 - ($value / $maxValue * 160) }} @endforeach"/>@foreach($chartValues as $index => $value)<circle cx="{{ $chartValues->count() > 1 ? ($index / ($chartValues->count() - 1) * 600) : 300 }}" cy="{{ 200 - ($value / $maxValue * 160) }}" r="5" class="booking-dot"><title>{{ $chartLabels[$index] }}: {{ $value }}</title></circle>@endforeach</svg><div class="chart-labels">@foreach($chartLabels as $label)<small>{{ $label }}</small>@endforeach</div></div></section>
<section class="report-bottom-grid"><article class="panel"><div class="panel-heading"><div><p class="admin-kicker">BREAKDOWN</p><h2>{{ $chartTitle }} by period</h2></div></div><div class="report-bars">@foreach($chartValues as $index => $value)<div><span style="height:{{ max(8, $value / $maxValue * 130) }}px"></span><b>{{ $metric === 'payments' ? '₱' . number_format($value) : $value }}</b><small>{{ $chartLabels[$index] }}</small></div>@endforeach</div></article><article class="panel"><div class="panel-heading"><div><p class="admin-kicker">BOOKING DETAIL · SELECTED PERIOD</p><h2>Latest bookings</h2></div></div><div class="report-table-wrap"><table class="report-table"><thead><tr><th>Date</th><th>Guest</th><th>Room</th><th>Total</th></tr></thead><tbody>@forelse($latestBookings as $booking)<tr><td>{{ $booking->created_at->format('Y-m-d') }}</td><td>{{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }}</td><td>{{ $booking->room?->name ?? '—' }}</td><td>₱{{ number_format($booking->total_amount) }}</td></tr>@empty<tr><td colspan="4" class="no-bookings">No bookings were created in this period.</td></tr>@endforelse</tbody></table></div></article></section>
<section class="panel latest-transactions"><div class="panel-heading"><div><p class="admin-kicker">RECENT ACTIVITY · SELECTED PERIOD</p><h2>Recent booking activity</h2></div></div><div class="transaction-grid">@forelse($latestBookings as $booking)<div><small>{{ $booking->created_at->format('Y-m-d H:i') }}</small><b>{{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }}</b><span>{{ $booking->room?->name ?? 'Room' }}</span><strong>₱{{ number_format($booking->total_amount) }}</strong></div>@empty<p class="empty-copy">Bookings will appear after reservations are placed in this period.</p>@endforelse</div></section>
</main></div><script>
(() => {
    const form = document.getElementById('report-range-form');
    const from = form.querySelector('[name="from"]');
    const to = form.querySelector('[name="to"]');
    const submit = form.querySelector('button[type="submit"]');
    const error = document.getElementById('report-range-error');
    let serverError = error.textContent.trim();
    let hasInteracted = false;
    let anchor = from.value ? 'from' : 'to';
    error.style.cssText = 'color: #b42318; font-weight: 700; flex-basis: 100%;';

    const utcDay = (value) => {
        const [year, month, day] = value.split('-').map(Number);
        return Date.UTC(year, month - 1, day);
    };

    const shiftCalendarMonths = (value, months) => {
        const [year, month, day] = value.split('-').map(Number);
        const targetMonth = month - 1 + months;
        const targetYear = year + Math.floor(targetMonth / 12);
        const normalizedMonth = ((targetMonth % 12) + 12) % 12;
        const lastDayOfMonth = new Date(Date.UTC(targetYear, normalizedMonth + 1, 0)).getUTCDate();
        const targetDay = Math.min(day, lastDayOfMonth);
        return String(targetYear) + '-' + String(normalizedMonth + 1).padStart(2, '0') + '-' + String(targetDay).padStart(2, '0');
    };

    const shiftCalendarDays = (value, days) => {
        const date = new Date(utcDay(value) + days * 86400000);
        return String(date.getUTCFullYear()) + '-' + String(date.getUTCMonth() + 1).padStart(2, '0') + '-' + String(date.getUTCDate()).padStart(2, '0');
    };

    const earliestStartDay = (endValue) => {
        let earliest = shiftCalendarMonths(endValue, -3);
        while (utcDay(shiftCalendarMonths(earliest, 3)) < utcDay(endValue)) {
            earliest = shiftCalendarDays(earliest, 1);
        }
        return earliest;
    };

    const updateDateBounds = () => {
        from.removeAttribute('min');
        from.removeAttribute('max');
        to.removeAttribute('min');
        to.removeAttribute('max');

        if (anchor === 'to' && to.value) {
            from.min = earliestStartDay(to.value);
            from.max = to.value;
        } else if (from.value) {
            to.min = from.value;
            to.max = shiftCalendarMonths(from.value, 3);
        } else if (to.value) {
            from.min = earliestStartDay(to.value);
            from.max = to.value;
        }
    };

    const setSubmitDisabled = (disabled) => {
        submit.disabled = disabled;
        submit.style.opacity = disabled ? '0.65' : '1';
        submit.style.cursor = disabled ? 'not-allowed' : 'pointer';
    };

    const validateRange = () => {
        if (!from.value || !to.value) {
            const message = serverError || (hasInteracted ? 'Choose both dates to continue.' : '');
            error.textContent = message;
            error.hidden = !message;
            setSubmitDisabled(true);
            return;
        }

        const endDay = utcDay(to.value);
        const message = endDay < utcDay(from.value)
            ? 'The end date must be on or after the start date.'
            : endDay > utcDay(shiftCalendarMonths(from.value, 3))
                ? 'Choose a custom report range of three months or less.'
                : '';

        error.textContent = message;
        error.hidden = !message;
        setSubmitDisabled(Boolean(message));
    };

    const handleDateChange = (event) => {
        serverError = '';
        hasInteracted = true;
        anchor = event.target === to ? 'to' : 'from';

        if (anchor === 'from' && from.value) {
            const latestTo = shiftCalendarMonths(from.value, 3);
            if (!to.value || utcDay(to.value) < utcDay(from.value) || utcDay(to.value) > utcDay(latestTo)) {
                to.value = latestTo;
            }
        }

        updateDateBounds();
        validateRange();
    };

    from.addEventListener('input', handleDateChange);
    from.addEventListener('change', handleDateChange);
    to.addEventListener('input', handleDateChange);
    to.addEventListener('change', handleDateChange);
    form.addEventListener('submit', (event) => {
        updateDateBounds();
        validateRange();
        if (submit.disabled) event.preventDefault();
    });

    updateDateBounds();
    validateRange();
})();
</script>
</body></html>
