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
            margin-bottom: 5px;
        }
        .booking-status-stack .mini-status {
            display: inline-flex;
            align-items: center;
            min-height: 23px;
            padding: 4px 8px;
            line-height: 1.1;
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
        .booking-note { color: var(--muted); font-size: 12px; margin: 5px 0; }
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
                <article class="occupied"><small>Rooms occupied</small><strong>{{ $occupiedRooms }}</strong></article>
                <article class="reserved"><small>Upcoming</small><strong>{{ $reservedRooms }}</strong></article>
                <article class="available"><small>Ready to book</small><strong>{{ $availableRooms }}</strong></article>
                <article class="maintenance"><small>Unavailable</small><strong>{{ $maintenanceRooms }}</strong></article>
            </section>

            <section class="panel bookings-reservations" style="margin-top:20px">
                <div class="panel-heading">
                    <div>
                        <p class="admin-kicker">ALL RESERVATIONS</p>
                        <h2>Review requests</h2>
                        <p class="booking-note">Payments update automatically when PayMongo confirms them. Confirming a request checks room availability again.</p>
                    </div>
                    <div><a class="status-select" href="{{ route('admin.bookings.export', request()->query()) }}">Export CSV</a> <span class="pill">{{ $individualBookings->total() }} total</span></div>
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
                                        <small>{{ $booking->guest_phone ?? 'No phone' }}</small>
                                        @if($booking->children_count || $booking->pets_count)
                                            <small>{{ $booking->children_count ? $booking->children_count . ' child' . ($booking->children_count > 1 ? 'ren' : '') : 'No children' }} · {{ $booking->pets_count ? $booking->pets_count . ' pet' . ($booking->pets_count > 1 ? 's' : '') : 'No pets' }}</small>
                                        @endif
                                    </td>
                                    <td class="booking-reference-cell" data-label="Reference"><code>{{ $booking->reference }}</code></td>
                                    <td class="booking-stay-cell" data-label="Room &amp; stay">
                                        <b>{{ $booking->room?->name ?? 'Room removed' }}</b>
                                        <small>{{ $booking->check_in_at?->format('M j, g A') ?? $booking->check_in->format('M j') }} to {{ $booking->check_out_at?->format('M j, g A') ?? $booking->check_out->format('M j, Y') }}</small>
                                    </td>
                                    <td class="booking-total-cell" data-label="Total">₱{{ number_format($booking->total_amount, 2) }}</td>
                                    <td data-label="Status">
                                        @php($payMongoPayments = $booking->payments->filter(fn ($payment) => $payment->method === 'gcash' && str_starts_with((string) $payment->reference, 'paymongo:'))->sortByDesc('id'))
                                        @php($payMongoPayment = $payMongoPayments->first())
                                        @php($hasPayMongoPayment = $payMongoPayments->isNotEmpty())
                                        @php($hasPaidPayMongoPayment = $payMongoPayments->contains(fn ($payment) => $payment->status === 'paid'))
                                        @php($paymentStatus = $booking->payment_status === 'refunded' ? 'refunded' : ($hasPaidPayMongoPayment ? 'paid' : ($booking->payment_status ?? 'pending')))
                                        @php($paymentStatusClass = match ($paymentStatus) { 'paid' => 'confirmed', 'refunded', 'failed' => 'cancelled', default => '' })
                                        <div class="booking-status-stack">
                                            <span class="mini-status {{ $booking->status }}">{{ $booking->status === 'pending' ? 'Pending staff confirmation' : ucfirst($booking->status) }}</span>
                                            <span class="mini-status booking-payment-status {{ $paymentStatus }} {{ $paymentStatusClass }}">Payment {{ ucfirst($paymentStatus) }}</span>
                                        </div>
                                        <small class="booking-payment-method">{{ $hasPayMongoPayment ? 'PayMongo · GCash' : strtoupper($booking->payment_method ?? 'cash') }}</small>
                                        @if($booking->status === 'pending')
                                            <span class="hold-expiry">Availability is rechecked before confirmation</span>
                                        @endif
                                    </td>
                                    <td class="booking-actions-cell" data-label="Actions">
                                        <div class="booking-actions">
                                            @php($refundableAmount = max(0, (float) $booking->payments->where('status', 'paid')->sum('amount') - (float) $booking->payments->where('status', 'refunded')->sum('amount')))
                                            @php($refundPolicyEligible = $booking->isRefundEligible())
                                                <div class="booking-action-buttons">
                                                    @if($hasPaidPayMongoPayment)
                                                        <span class="booking-note">Verified through PayMongo</span>
                                                    @elseif($payMongoPayment?->status === 'pending')
                                                        <form method="POST" action="{{ route('admin.bookings.payment.recheck-paymongo', $booking) }}" onsubmit="return confirm('Check this GCash checkout directly with PayMongo?')">
                                                            @csrf
                                                            <button class="status-select confirmed" type="submit">Verify with PayMongo</button>
                                                        </form>
                                                    @elseif(! $hasPayMongoPayment && $booking->status !== 'cancelled')
                                                        <form method="POST" action="{{ route('admin.bookings.payment', $booking) }}"
                                                            data-admin-confirm
                                                            data-confirm-kicker="PAYMENT RECORD"
                                                            data-confirm-icon="₱"
                                                            data-confirm-title="Update payment record?"
                                                            data-confirm-message="{{ $booking->payment_status === 'paid' ? 'Mark this payment as unpaid? This changes only the payment record; it does not issue a refund.' : 'Mark this payment as paid? This records the payment manually and does not process a charge.' }}"
                                                            data-confirm-action="{{ $booking->payment_status === 'paid' ? 'Mark unpaid' : 'Mark paid' }}">
                                                            @csrf @method('PATCH')
                                                            <input type="hidden" name="payment_method" value="{{ in_array($booking->payment_method, ['cash', 'gcash']) ? $booking->payment_method : 'cash' }}">
                                                            <input type="hidden" name="payment_status" value="{{ $booking->payment_status === 'paid' ? 'pending' : 'paid' }}">
                                                            <button class="status-select {{ $booking->payment_status === 'paid' ? 'confirmed' : '' }}" type="submit">{{ $booking->payment_status === 'paid' ? 'Mark unpaid' : 'Mark paid' }}</button>
                                                        </form>
                                                    @endif
                                                    @if($booking->status === 'pending')
                                                        <form method="POST" action="{{ route('admin.bookings.update', $booking) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="confirmed">
                                                            <button class="status-select confirmed" type="submit">Confirm</button>
                                                        </form>
                                                    @endif

                                                    @if($booking->status === 'confirmed' && ! $booking->checked_in_at && (! $booking->check_in_at || $booking->check_in_at->lte(now())))
                                                        <form method="POST" action="{{ route('admin.bookings.check-in', $booking) }}" data-admin-confirm data-confirm-title="Check in this guest?" data-confirm-message="Check in {{ $booking->guest_name ?? $booking->user?->name ?? 'this guest' }} now? This marks the reservation as checked in." data-confirm-action="Check in guest">
                                                            @csrf
                                                            <input type="hidden" name="action" value="check_in">
                                                            <button class="status-select confirmed" type="submit">Check in</button>
                                                        </form>
                                                    @endif

                                                    @if($booking->checked_in_at && ! $booking->checked_out_at)
                                                        <form method="POST" action="{{ route('admin.bookings.check-out', $booking) }}" onsubmit="return confirm('Check this guest out and start cleaning?')">
                                                            @csrf
                                                            <input type="hidden" name="action" value="check_out">
                                                            <button class="status-select" type="submit">Check out</button>
                                                        </form>
                                                    @endif

                                                    @if($booking->status !== 'cancelled' && ! ($booking->checked_in_at && ! $booking->checked_out_at))
                                                        <form method="POST" action="{{ route('admin.bookings.update', $booking) }}" onsubmit="return confirm('Cancel this booking?')">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="cancelled">
                                                            <button class="status-select cancelled" type="submit">Cancel</button>
                                                        </form>
                                                    @endif
                                            </div>

                                            @if($refundableAmount > 0 && $refundPolicyEligible)
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
                                                    <span class="refund-note-copy"><strong>Outside the refund window</strong>Refunds aren’t available within 3 days of check-in.</span>
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

            <section class="panel" style="margin-top:20px">
                <div class="panel-heading">
                    <div><p class="admin-kicker">GUEST REVIEWS</p><h2>Review moderation</h2></div>
                    <span class="pill">{{ $pendingReviews->count() }} pending</span>
                </div>
                <div class="customer-list">
                    @forelse($pendingReviews as $review)
                        <div class="customer-item">
                            <div>
                                <b>{{ $review->guest_name }} · {{ $review->room?->name }}</b>
                                <small>{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }} · {{ $review->comment ?: 'No written comment' }}</small>
                            </div>
                            <form method="POST" action="{{ route('admin.reviews.update', $review) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="approved">
                                <button class="status-select confirmed" type="submit">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.reviews.update', $review) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="hidden">
                                <button class="status-select cancelled" type="submit">Hide</button>
                            </form>
                        </div>
                    @empty
                        <p class="empty-copy">No reviews waiting for moderation.</p>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
    @include('admin.partials.confirmation-dialog')
</body>
</html>
