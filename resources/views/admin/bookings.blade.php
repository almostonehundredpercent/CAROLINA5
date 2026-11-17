<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bookings - Carolina Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <style>
        .booking-actions {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            justify-items: start;
            gap: 9px;
            min-width: 270px;
            max-width: 380px;
        }
        .booking-actions-cell { vertical-align: top; }
        .booking-status-stack {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
        }
        .booking-status-stack .mini-status {
            display: inline-flex;
            align-items: center;
            min-height: 32px;
            padding: 6px 11px;
            border: 1px solid;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.25;
        }
        .booking-status-stack .mini-status.confirmed {
            border-color: #c6dbc8;
            background: #e6f1e7;
            color: #285c36;
        }
        .booking-status-stack .booking-payment-status.paid {
            border-color: #ead4b2;
            background: #fff0da;
            color: #81531a;
        }
        .booking-status-stack .mini-status.pending {
            border-color: #ead4ae;
            background: #fff3df;
            color: #83520e;
        }
        .booking-status-stack .mini-status.hold-expired {
            border-color: #e5b9a4;
            background: #fff0e8;
            color: #8a3b1c;
        }
        .booking-status-stack .mini-status.cancelled {
            border-color: #e7c5bf;
            background: #f6e6e2;
            color: #8e3f36;
        }
        html.dark-mode .booking-status-stack .mini-status.confirmed {
            border-color: #476b4e;
            background: #26392a;
            color: #cfe5d1;
        }
        html.dark-mode .booking-status-stack .booking-payment-status.paid {
            border-color: #76562f;
            background: #3a2a1a;
            color: #f3d6aa;
        }
        html.dark-mode .booking-status-stack .mini-status.pending {
            border-color: #765b31;
            background: #42321c;
            color: #f1d4a1;
        }
        html.dark-mode .booking-status-stack .mini-status.hold-expired {
            border-color: #a66b4c;
            background: #432b22;
            color: #ffdcc6;
        }
        html.dark-mode .booking-status-stack .mini-status.cancelled {
            border-color: #79413b;
            background: #422523;
            color: #f3c7c0;
        }
        .booking-payment-method { display: block; }
        .booking-action-buttons {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            max-width: 100%;
        }
        .booking-action-buttons form { display: flex; margin: 0; }
        .booking-action-buttons .status-select {
            min-width: 88px;
            min-height: 38px !important;
            padding: 8px 12px !important;
            font-size: 12px !important;
            line-height: 1.2;
            white-space: nowrap;
        }
        .booking-action-buttons .restore-booking-button {
            min-height: 44px !important;
            white-space: normal;
            text-align: center;
        }
        .booking-note { color: var(--muted); font-size: 12px; margin: 5px 0; }
        .bookings-page .booking-stat-grid article { position: relative; }
        .bookings-page .booking-stat-grid .room-stat-trigger { position: absolute; inset: 0; box-sizing: border-box; display: block; width: 100%; height: 100%; padding: inherit; border: 0; border-radius: inherit; background: transparent; color: inherit; font: inherit; text-align: left; cursor: pointer; }
        .bookings-page .booking-stat-grid .room-stat-trigger:hover { filter: brightness(1.08); }
        .bookings-page .booking-stat-grid .room-stat-trigger:focus-visible { outline: 3px solid var(--orange); outline-offset: 3px; z-index: 1; }
        .booking-payment-proof {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            width: fit-content;
            max-width: 100%;
            padding: 5px 9px;
            border: 1px solid #c6dbc8;
            border-radius: 999px;
            background: #e6f1e7;
            color: #285c36;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.25;
        }
        .booking-payment-proof-icon { font-size: 13px; }
        html.dark-mode .booking-payment-proof {
            border-color: #476b4e;
            background: #26392a;
            color: #cfe5d1;
        }
        .hold-expiry { display: block; color: #9d6100; font-size: 11px; margin-top: 5px; }
        .status-select { cursor: pointer; }
        .booking-filters { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 16px; }
        .booking-filters input, .booking-filters select { border:1px solid var(--line); border-radius:7px; padding:9px; font:inherit; }
        .booking-filters button { border:0; border-radius:7px; padding:9px 13px; background:var(--orange); color:#fff; font-weight:700; cursor:pointer; }
        .refund-panel { width: min(100%, 340px); min-width: 0; margin: 0; }
        .refund-panel summary { color:var(--orange-dark); cursor:pointer; font-size:12px; font-weight:700; }
        .refund-form { display:grid; grid-template-columns:1fr 1fr auto; gap:6px; margin-top:8px; padding:9px; border:1px solid var(--line); border-radius:9px; background:var(--paper); }
        .refund-form label { display:grid; gap:3px; color:var(--muted); font-size:10px; font-weight:700; }
        .refund-form input { min-width:0; padding:7px; border:1px solid var(--line); border-radius:6px; font:inherit; }
        .refund-form button { align-self:end; min-height:33px; border:1px solid #c85b48; border-radius:7px; background:#fff4f1; color:#a23d2e; font-weight:700; cursor:pointer; }
        .booking-action-note {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            width: 100%;
            max-width: 300px;
            padding: 8px 10px;
            border: 1px solid;
            border-radius: 9px;
            font-size: 11px;
            line-height: 1.45;
        }
        .refund-note-icon {
            display: grid;
            flex: 0 0 17px;
            width: 17px;
            height: 17px;
            place-items: center;
            border-radius: 50%;
            font-size: 11px;
            font-weight: 800;
        }
        .refund-note-copy { min-width:0; }
        .refund-note-copy strong { display:block; margin-bottom:1px; font-size:11px; }
        html:not(.dark-mode) .booking-action-note { border-color: #efd1ca; background: #fff4f1; color: #8f3a2d; }
        html:not(.dark-mode) .refund-note-icon { background: #f9dfda; color: #9b4033; }
        html.dark-mode .booking-action-note { border-color: rgba(255, 145, 130, .25); background: rgba(126, 43, 34, .19); color: #ffd2cb; }
        html.dark-mode .refund-note-icon { background: rgba(239, 128, 119, .18); color: #ffc0b7; }
    </style>
</head>
<body class="bookings-page">
    <div class="admin-shell">
        @include('admin.partials.sidebar')

        <main class="admin-main">
            <header class="admin-topbar">
                <div>
                    <p class="admin-kicker">RESERVATION MANAGEMENT</p>
                    <h1>Bookings</h1>
                    <p class="admin-subtitle">Confirm requests, manage arrivals, and keep room inventory accurate.</p>
                </div>
            </header>

            @if(session('success'))
                <div class="admin-flash">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="admin-flash" style="background:#fbe3e0;color:#a84336">{{ $errors->first() }}</div>
            @endif

            <section class="booking-stat-grid">
                <article class="occupied"><button class="room-stat-trigger" type="button" data-room-stat-dialog-open="room-stat-occupied" aria-haspopup="dialog" aria-controls="room-stat-occupied"><small>Rooms occupied</small><strong>{{ $occupiedRooms }}</strong><em>View room details</em></button></article>
                <article class="reserved"><button class="room-stat-trigger" type="button" data-room-stat-dialog-open="room-stat-upcoming" aria-haspopup="dialog" aria-controls="room-stat-upcoming"><small>Upcoming</small><strong>{{ $reservedRooms }}</strong><em>View room details</em></button></article>
                <article class="available"><button class="room-stat-trigger" type="button" data-room-stat-dialog-open="room-stat-available" aria-haspopup="dialog" aria-controls="room-stat-available"><small>Ready to book</small><strong>{{ $availableRooms }}</strong><em>View room details</em></button></article>
                <article class="maintenance"><button class="room-stat-trigger" type="button" data-room-stat-dialog-open="room-stat-unavailable" aria-haspopup="dialog" aria-controls="room-stat-unavailable"><small>Unavailable</small><strong>{{ $unavailableRooms }}</strong><em>View room details</em></button></article>
            </section>

            <dialog class="dashboard-detail-dialog" id="room-stat-occupied" aria-labelledby="room-stat-occupied-title">
                <div class="dashboard-detail-shell">
                    <header class="dashboard-detail-header"><div><p class="admin-kicker">ROOM STATUS</p><h2 id="room-stat-occupied-title">Rooms occupied · {{ $occupiedRoomDetails->count() }}</h2><p>Current stays occupying rooms.</p></div><button class="dashboard-detail-close" type="button" data-room-stat-dialog-close aria-label="Close occupied rooms">×</button></header>
                    <div class="dashboard-detail-body"><section class="dashboard-detail-section">
                        @if($occupiedRoomDetails->isNotEmpty())<div class="dashboard-detail-table-wrap" role="region" aria-label="Occupied rooms" tabindex="0"><table class="dashboard-detail-table"><thead><tr><th>Room</th><th>Guest</th><th>Check-in</th><th>Check-out</th></tr></thead><tbody>
                            @foreach($occupiedRoomDetails as $room)@php($stay = $room->display_booking)<tr><td>{{ $room->name }}</td><td>{{ $stay?->guest_name ?? $stay?->user?->name ?? 'Guest' }}</td><td>{{ $stay?->check_in_at?->format('M j, Y g:i A') ?? $stay?->check_in?->format('M j, Y') ?? '—' }}</td><td>{{ $stay?->check_out_at?->format('M j, Y g:i A') ?? $stay?->check_out?->format('M j, Y') ?? '—' }}</td></tr>@endforeach
                        </tbody></table></div>@else<p class="dashboard-detail-empty">No rooms are occupied right now.</p>@endif
                    </section></div>
                </div>
            </dialog>

            <dialog class="dashboard-detail-dialog" id="room-stat-upcoming" aria-labelledby="room-stat-upcoming-title">
                <div class="dashboard-detail-shell">
                    <header class="dashboard-detail-header"><div><p class="admin-kicker">ROOM STATUS</p><h2 id="room-stat-upcoming-title">Upcoming reservations · {{ $reservedRoomDetails->count() }}</h2><p>Rooms with a future reservation and the next guest arrival.</p></div><button class="dashboard-detail-close" type="button" data-room-stat-dialog-close aria-label="Close upcoming rooms">×</button></header>
                    <div class="dashboard-detail-body"><section class="dashboard-detail-section">
                        @if($reservedRoomDetails->isNotEmpty())<div class="dashboard-detail-table-wrap" role="region" aria-label="Upcoming room reservations" tabindex="0"><table class="dashboard-detail-table"><thead><tr><th>Room</th><th>Guest</th><th>Check-in</th><th>Check-out</th></tr></thead><tbody>
                            @foreach($reservedRoomDetails as $room)@php($stay = $room->display_booking)<tr><td>{{ $room->name }}</td><td>{{ $stay?->guest_name ?? $stay?->user?->name ?? 'Guest' }}</td><td>{{ $stay?->check_in_at?->format('M j, Y g:i A') ?? $stay?->check_in?->format('M j, Y') ?? '—' }}</td><td>{{ $stay?->check_out_at?->format('M j, Y g:i A') ?? $stay?->check_out?->format('M j, Y') ?? '—' }}</td></tr>@endforeach
                        </tbody></table></div>@else<p class="dashboard-detail-empty">No upcoming room reservations.</p>@endif
                    </section></div>
                </div>
            </dialog>

            <dialog class="dashboard-detail-dialog" id="room-stat-available" aria-labelledby="room-stat-available-title">
                <div class="dashboard-detail-shell">
                    <header class="dashboard-detail-header"><div><p class="admin-kicker">ROOM STATUS</p><h2 id="room-stat-available-title">Ready to book · {{ $availableRoomDetails->count() }}</h2><p>Rooms without a current or upcoming reservation or active work block.</p></div><button class="dashboard-detail-close" type="button" data-room-stat-dialog-close aria-label="Close available rooms">×</button></header>
                    <div class="dashboard-detail-body"><section class="dashboard-detail-section">
                        @if($availableRoomDetails->isNotEmpty())<div class="dashboard-detail-table-wrap" role="region" aria-label="Rooms ready to book" tabindex="0"><table class="dashboard-detail-table"><thead><tr><th>Room</th><th>Type</th><th>Capacity</th><th>Beds</th><th>Rate</th></tr></thead><tbody>
                            @foreach($availableRoomDetails as $room)<tr><td>{{ $room->name }}</td><td>{{ $room->room_type ? ucfirst($room->room_type) : 'Room' }}</td><td>{{ $room->guests }} guests</td><td>{{ $room->beds ?: '—' }}</td><td>₱{{ number_format((float) $room->price_per_night, 2) }} {{ $room->rate_label }}</td></tr>@endforeach
                        </tbody></table></div>@else<p class="dashboard-detail-empty">No rooms are ready to book.</p>@endif
                    </section></div>
                </div>
            </dialog>

            <dialog class="dashboard-detail-dialog" id="room-stat-unavailable" aria-labelledby="room-stat-unavailable-title">
                <div class="dashboard-detail-shell">
                    <header class="dashboard-detail-header"><div><p class="admin-kicker">ROOM STATUS</p><h2 id="room-stat-unavailable-title">Unavailable · {{ $unavailableRoomDetails->count() }}</h2><p>Rooms currently under cleaning or maintenance.</p></div><button class="dashboard-detail-close" type="button" data-room-stat-dialog-close aria-label="Close unavailable rooms">×</button></header>
                    <div class="dashboard-detail-body"><section class="dashboard-detail-section">
                        @if($unavailableRoomDetails->isNotEmpty())<div class="dashboard-detail-table-wrap" role="region" aria-label="Unavailable rooms" tabindex="0"><table class="dashboard-detail-table"><thead><tr><th>Room</th><th>Status</th><th>Unavailable until</th><th>Notes</th></tr></thead><tbody>
                            @foreach($unavailableRoomDetails as $room)@php($block = $room->display_block)<tr><td>{{ $room->name }}</td><td>{{ ucfirst($room->display_status) }}</td><td>{{ $block?->ends_at?->format('M j, Y g:i A') ?? $room->operational_until?->format('M j, Y g:i A') ?? 'Until cleared by staff' }}</td><td>{{ $block?->notes ?: '—' }}</td></tr>@endforeach
                        </tbody></table></div>@else<p class="dashboard-detail-empty">No rooms are currently unavailable.</p>@endif
                    </section></div>
                </div>
            </dialog>

            <section class="panel bookings-reservations" style="margin-top:20px">
                <div class="panel-heading">
                    <div>
                        <p class="admin-kicker">ALL RESERVATIONS</p>
                        <h2>Review requests</h2>
                        <p class="booking-note">A verified downpayment or full payment automatically confirms the booking after an availability check. Unpaid requests still need staff review.</p>
                    </div>
                    <div class="booking-heading-actions"><a class="status-select" href="{{ route('admin.bookings.export', request()->query()) }}">Export CSV</a><span class="pill">{{ $individualBookings->total() }} total</span></div>
                </div>

                <form class="booking-filters" method="GET" aria-label="Filter bookings">
                    <input name="search" value="{{ request('search') }}" placeholder="Guest name, email, or reference">
                    <select name="status">
                        <option value="">All statuses</option>
                        @foreach(['pending', 'confirmed', 'cancelled'] as $filterStatus)
                            <option value="{{ $filterStatus }}" @selected(request('status') === $filterStatus)>{{ $filterStatus === 'pending' ? 'Pending staff confirmation' : ucfirst($filterStatus) }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="arrival" value="{{ request('arrival') }}" aria-label="Arrival or departure date">
                    <select name="per_page" class="booking-page-size" aria-label="Bookings per page" onchange="this.form.requestSubmit()">
                        @foreach([5, 15, 25, 50, 100] as $pageSize)
                            <option value="{{ $pageSize }}" @selected($individualBookings->perPage() === $pageSize)>{{ $pageSize }} per page</option>
                        @endforeach
                    </select>
                    <button type="submit">Filter</button>
                    @if(request()->hasAny(['search', 'status', 'arrival']))<a href="{{ route('admin.bookings') }}">Clear</a>@endif
                </form>

                <div class="booking-results-summary" id="booking-results" role="status" aria-live="polite">
                    <span>Showing {{ $individualBookings->firstItem() ?? 0 }}-{{ $individualBookings->lastItem() ?? 0 }} of {{ $individualBookings->total() }} bookings</span>
                    <span>Page {{ $individualBookings->currentPage() }} of {{ $individualBookings->lastPage() }}</span>
                </div>

                @include('admin.partials.booking-pagination')

                <div class="admin-table-wrap">
                    <table class="booking-table">
                        <colgroup>
                            <col class="booking-column-guest">
                            <col class="booking-column-reference">
                            <col class="booking-column-stay">
                            <col class="booking-column-total">
                            <col class="booking-column-status">
                            <col class="booking-column-actions">
                        </colgroup>
                        <thead>
                            <tr><th>Guest</th><th>Reference</th><th>Room & stay</th><th>Total</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            @forelse($individualBookings as $booking)
                                <tr>
                                    <td data-label="Guest">
                                        <b>{{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }}</b>
                                        <small>{{ $booking->guest_email ?? $booking->user?->email ?? 'No email' }}</small>
                                        @if($booking->guest_phone)
                                            <a class="admin-phone-link" href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $booking->guest_phone) }}" aria-label="Call {{ $booking->guest_name ?? $booking->user?->name ?? 'guest' }} at {{ $booking->guest_phone }}">{{ $booking->guest_phone }}</a>
                                        @else
                                            <small>No phone</small>
                                        @endif
                                        @if($booking->children_count || $booking->pets_count)
                                            <small>{{ $booking->children_count ? $booking->children_count . ' child' . ($booking->children_count > 1 ? 'ren' : '') : 'No children' }} · {{ $booking->pets_count ? $booking->pets_count . ' pet' . ($booking->pets_count > 1 ? 's' : '') : 'No pets' }}</small>
                                        @endif
                                    </td>
                                    <td class="booking-reference-cell" data-label="Reference"><code>{{ $booking->reference }}</code></td>
                                    <td class="booking-stay-cell" data-label="Room &amp; stay">
                                        <b>{{ $booking->room?->name ?? 'Room removed' }}</b>
                                        <small>{{ $booking->check_in_at?->format('M j, g A') ?? $booking->check_in->format('M j') }} to {{ $booking->check_out_at?->format('M j, g A') ?? $booking->check_out->format('M j, Y') }}</small>
                                        <small>{{ $booking->booking_type === 'hourly' ? ($booking->hours === 720 ? '1 month' : ($booking->hours > 720 && isset($booking->add_ons['extra_hours']) ? '1 month + '.(int) $booking->add_ons['extra_hours'].' hour'.((int) $booking->add_ons['extra_hours'] === 1 ? '' : 's') : $booking->hours.' hours')) : $booking->nights.' night'.($booking->nights === 1 ? '' : 's') }}{{ $booking->cooling_type ? ' · '.($booking->cooling_type === 'fan' ? 'Fan' : 'Air-conditioned') : '' }} · {{ $booking->guests }} guest{{ $booking->guests === 1 ? '' : 's' }}</small>
                                    </td>
                                    <td class="booking-total-cell" data-label="Total">₱{{ number_format($booking->total_amount, 2) }}</td>
                                    <td data-label="Status">
                                        @php($payMongoPayments = $booking->payments->filter(fn ($payment) => $payment->method === 'gcash' && str_starts_with((string) $payment->reference, 'paymongo:'))->sortByDesc('id'))
                                        @php($payMongoPayment = $payMongoPayments->firstWhere('status', 'pending') ?? $payMongoPayments->first())
                                        @php($hasPayMongoPayment = $payMongoPayments->contains(fn ($payment) => in_array($payment->status, ['pending', 'paid', 'refunded'], true)))
                                        @php($hasPaidPayMongoPayment = $payMongoPayments->contains(fn ($payment) => $payment->status === 'paid'))
                                        @php($expiredCashHold = $booking->isExpiredHoldRecord() || (! $hasPayMongoPayment && ($booking->payment_method === 'cash' || $booking->payment_method === null) && $booking->hasExpiredHold()))
                                        @php($timeAdjustment = $booking->timeAdjustments->sortByDesc('id')->first())
                                        @php($paymentStatus = $booking->payment_status ?? 'pending')
                                        @php($paymentStatusClass = match ($paymentStatus) { 'paid' => 'confirmed', 'refunded', 'failed' => 'cancelled', default => '' })
                                        @php($paymentMethodLabel = $hasPayMongoPayment ? 'PayMongo · GCash' : match ($paymentStatus) { 'failed' => 'GCash checkout failed', 'paid', 'partial', 'refunded' => strtoupper($booking->payment_method ?? 'Payment recorded'), default => $booking->payment_method ? strtoupper($booking->payment_method) : 'Not paid yet' })
                                        <div class="booking-status-stack">
                                            <span class="mini-status {{ $expiredCashHold ? 'hold-expired' : $booking->status }}" aria-label="Booking status: {{ $expiredCashHold ? 'Hold expired' : ($booking->status === 'pending' ? 'Pending staff confirmation' : ucfirst($booking->status)) }}">{{ $expiredCashHold ? 'Hold expired' : ($booking->status === 'pending' ? 'Pending staff confirmation' : ucfirst($booking->status)) }}</span>
                                            <span class="mini-status booking-payment-status {{ $paymentStatus }} {{ $paymentStatusClass }}" aria-label="Payment status: {{ ucfirst($paymentStatus) }}">Payment {{ ucfirst($paymentStatus) }}</span>
                                        </div>
                                        <small class="booking-payment-method">{{ $paymentMethodLabel }}</small>
                                        @if((float) $booking->deposit_amount > 0)
                                            <small class="booking-payment-method">Downpayment ₱{{ number_format((float) $booking->deposit_amount, 2) }} · {{ $booking->deposit_status === 'verified' || $booking->deposit_verified_at ? 'Verified' : 'Awaiting payment' }}</small>
                                        @endif
                                        @if($booking->early_checkout_status === 'requested' && ! $booking->checked_out_at)
                                            <span class="hold-expiry" role="status">Early check-out requested · {{ $booking->early_checkout_requested_at?->format('M j, g:i A') }}</span>
                                        @endif
                                        @if($timeAdjustment?->status === 'needs_review')
                                            <span class="hold-expiry" role="status">Extra-time payment received · staff follow-up needed</span>
                                        @elseif($timeAdjustment?->status === 'pending' && $timeAdjustment->expires_at?->lte(now()))
                                            <span class="hold-expiry" role="status">Extra-time payment expired · original booking time kept</span>
                                        @elseif($timeAdjustment?->status === 'pending')
                                            <span class="hold-expiry" role="status">Extra-time payment pending · held until {{ $timeAdjustment->expires_at?->format('g:i A') }}</span>
                                        @endif
                                        @if($expiredCashHold)
                                            <span class="hold-expiry" role="status">Expired {{ $booking->holdDeadline()?->format('M j, Y · g:i A') }} · Room time is no longer reserved</span>
                                        @elseif($booking->status === 'pending')
                                            <span class="hold-expiry">Availability is rechecked before confirmation</span>
                                        @endif
                                    </td>
                                    <td class="booking-actions-cell" data-label="Actions">
                                        <div class="booking-actions">
                                            @php($refundableAmount = max(0, (float) $booking->payments->where('status', 'paid')->sum('amount') - (float) $booking->payments->where('status', 'refunded')->sum('amount')))
                                            @php($refundPolicyEligible = $booking->isRefundEligible())
                                            @if($hasPaidPayMongoPayment && ! $expiredCashHold)
                                                <div class="booking-payment-proof" role="status">
                                                    <span class="booking-payment-proof-icon" aria-hidden="true">✓</span>
                                                    <span>Verified through PayMongo</span>
                                                </div>
                                            @endif
                                                <div class="booking-action-buttons">
                                                    @if($expiredCashHold)
                                                        <form method="POST" action="{{ route('admin.bookings.update', $booking) }}"
                                                            data-admin-confirm
                                                            data-confirm-kicker="EXPIRED BOOKING HOLD"
                                                            data-confirm-icon="↻"
                                                            data-confirm-title="Check availability and restore?"
                                                            data-confirm-message="The original hold has expired. This checks the room again and confirms the booking only if it is still available. No payment will be recorded."
                                                            data-confirm-action="Check and restore">
                                                            @csrf @method('PATCH')
                                                            <input type="hidden" name="status" value="confirmed">
                                                            <button class="status-select confirmed restore-booking-button" type="submit">Check availability &amp; restore</button>
                                                        </form>
                                                    @elseif(! $hasPaidPayMongoPayment && $payMongoPayment?->status === 'pending')
                                                        <form method="POST" action="{{ route('admin.bookings.payment.recheck-paymongo', $booking) }}" onsubmit="return confirm('Check this GCash checkout directly with PayMongo?')">
                                                            @csrf
                                                            <button class="status-select confirmed" type="submit">Verify with PayMongo</button>
                                                        </form>
                                                    @elseif($booking->status !== 'cancelled')
                                                        <a class="status-select" href="{{ route('admin.bookings.show', $booking) }}">Payment details</a>
                                                    @endif
                                                    @if($booking->status === 'pending' && ! $expiredCashHold)
                                                        <form method="POST" action="{{ route('admin.bookings.update', $booking) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="confirmed">
                                                            @if((float) $booking->deposit_amount <= 0 || $booking->deposit_status === 'verified' || $booking->deposit_verified_at)
                                                                <button class="status-select confirmed" type="submit">Confirm</button>
                                                            @else
                                                                <a class="status-select" href="{{ route('admin.bookings.show', $booking) }}">Verify downpayment first</a>
                                                            @endif
                                                        </form>
                                                    @endif

                                                    @if($booking->status === 'confirmed' && ! $booking->checked_in_at && (! $booking->check_in_at || $booking->check_in_at->lte($propertyNow)))
                                                        <form method="POST" action="{{ route('admin.bookings.check-in', $booking) }}" data-admin-confirm data-confirm-title="Check in this guest?" data-confirm-message="Check in {{ $booking->guest_name ?? $booking->user?->name ?? 'this guest' }} now? This marks the reservation as checked in." data-confirm-action="Check in guest">
                                                            @csrf
                                                            <input type="hidden" name="action" value="check_in">
                                                            <button class="status-select confirmed" type="submit">Check in</button>
                                                        </form>
                                                    @endif

                                                    @if($booking->early_checkout_status === 'requested' && $booking->checked_in_at && ! $booking->checked_out_at)
                                                        <form method="POST" action="{{ route('admin.bookings.early-checkout.resolve', $booking) }}" data-admin-confirm data-confirm-title="Confirm early check-out?" data-confirm-message="This records the guest’s departure and starts a 2-hour cleaning period. The booking total stays the same; refunds are handled separately." data-confirm-action="Confirm check-out">
                                                            @csrf<input type="hidden" name="decision" value="confirm"><button class="status-select confirmed" type="submit">Confirm check-out</button>
                                                        </form>
                                                        <form method="POST" action="{{ route('admin.bookings.early-checkout.resolve', $booking) }}" data-admin-confirm data-confirm-title="Decline this request?" data-confirm-message="The guest will remain checked in. Contact them to coordinate the departure." data-confirm-action="Decline request" data-confirm-style="cancellation">
                                                            @csrf<input type="hidden" name="decision" value="decline"><button class="status-select cancelled" type="submit">Decline request</button>
                                                        </form>
                                                    @elseif($booking->checked_in_at && ! $booking->checked_out_at)
                                                        <form method="POST" action="{{ route('admin.bookings.check-out', $booking) }}" data-admin-confirm data-confirm-title="Check out now?" data-confirm-message="This records the guest’s actual checkout and starts room cleaning. It does not automatically refund unused time." data-confirm-action="Check out now">
                                                            @csrf
                                                            <input type="hidden" name="action" value="check_out">
                                                            <button class="status-select" type="submit">Check out now</button>
                                                        </form>
                                                    @endif

                                                    @if($booking->status !== 'cancelled' && ! ($booking->checked_in_at && ! $booking->checked_out_at))
                                                        <form method="POST" action="{{ route('admin.bookings.update', $booking) }}"
                                                            data-admin-confirm
                                                            data-confirm-kicker="BOOKING CANCELLATION"
                                                            data-confirm-icon="!"
                                                            data-confirm-title="Cancel this booking?"
                                                            data-confirm-message="Cancel booking {{ $booking->reference }} for {{ $booking->guest_name ?? $booking->user?->name ?? 'this guest' }}? This releases the room time. Refunds must be handled separately."
                                                            data-confirm-action="Cancel booking"
                                                            data-confirm-style="cancellation"
                                                            data-confirm-dismiss="Keep booking">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="cancelled">
                                                            <button class="status-select cancelled" type="submit">Cancel</button>
                                                        </form>
                                                    @endif
                                            </div>

                                            @if($booking->status === 'cancelled' && $refundableAmount > 0 && $refundPolicyEligible)
                                                <details class="refund-panel">
                                                    <summary>Record refund · ₱{{ number_format($refundableAmount, 2) }} available</summary>
                                                    <form class="refund-form" method="POST" action="{{ route('admin.bookings.refunds.store', $booking) }}" onsubmit="return confirm('Record this refund? This only records the refund; complete any cash or GCash transfer separately.')">
                                                        @csrf
                                                        <label>Amount
                                                            <input name="amount" type="number" min="0.01" max="{{ number_format($refundableAmount, 2, '.', '') }}" step="0.01" value="{{ number_format($refundableAmount, 2, '.', '') }}" required>
                                                        </label>
                                                        <label>Reason
                                                            <input name="reason" maxlength="500" placeholder="e.g. cancelled stay" required>
                                                        </label>
                                                        <button type="submit">Record refund</button>
                                                    </form>
                                                </details>
                                            @elseif($refundableAmount > 0)
                                                <div class="booking-action-note refund-ineligible" role="note">
                                                    <span class="refund-note-icon" aria-hidden="true">!</span>
                                                    <span class="refund-note-copy"><strong>{{ $booking->status === 'cancelled' ? 'Outside the refund window' : 'Refund requires cancellation' }}</strong>{{ $booking->status === 'cancelled' ? 'Refunds aren’t available when cancellation is less than 3 days before check-in.' : 'A full refund is eligible after cancellation at least 3 days before check-in.' }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="booking-empty-row"><td colspan="6" class="no-bookings">No reservations yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('admin.partials.booking-pagination')
            </section>

        </main>
    </div>
    <script>
        (() => {
            document.querySelectorAll('[data-room-stat-dialog-open]').forEach((trigger) => {
                const dialog = document.getElementById(trigger.dataset.roomStatDialogOpen);
                if (!dialog || typeof dialog.showModal !== 'function') return;

                trigger.addEventListener('click', () => dialog.showModal());
                dialog.querySelector('[data-room-stat-dialog-close]')?.addEventListener('click', () => dialog.close());
                dialog.addEventListener('click', (event) => {
                    if (event.target === dialog) dialog.close();
                });
                dialog.addEventListener('close', () => trigger.focus());
            });
        })();
    </script>
    @include('admin.partials.confirmation-dialog')
</body>
</html>
