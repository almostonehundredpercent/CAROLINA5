@extends('layouts.app')

@section('content')
@php
    $expiredHold = $booking->hasExpiredHold() || $booking->isExpiredHoldRecord();
    $guestBooking = $booking->user_id === null;
    $canResumePayment = ! $expiredHold && $booking->status === 'pending' && ! $booking->hasProtectedInventory();
    $showCancellation = $guestBooking && $booking->status !== 'cancelled' && ! $expiredHold && ! $booking->checked_in_at && ! $booking->checked_out_at;
    $canCancel = $showCancellation && $booking->canCancelOnline();
    $canReschedule = $showCancellation && $booking->canRescheduleOnline();
    $showExtension = $booking->checked_in_at && ! $booking->checked_out_at && $booking->status !== 'cancelled';
    $extensionHours = max(1, min(168, (int) old('hours', 1)));
    $scheduledCheckoutAt = $booking->check_out_at ?? $booking->check_out?->copy()->endOfDay();
    $earlyCheckoutBalance = \App\Support\FinancialSummary::forBooking($booking)['outstanding'];
    $earlyCheckoutWindowOpen = $showExtension && $booking->status === 'confirmed' && $scheduledCheckoutAt?->gt(\App\Support\PropertyTime::now()) && ! in_array($booking->early_checkout_status, ['requested', 'declined', 'confirmed'], true);
    $showEarlyCheckout = $earlyCheckoutWindowOpen && $earlyCheckoutBalance <= 0.001;
    $earlyCheckoutBlockedByBalance = $earlyCheckoutWindowOpen && $earlyCheckoutBalance > 0.001;
    $hasPortalAside = $expiredHold || $canResumePayment || $showCancellation || $showExtension || $booking->checked_out_at || $booking->status === 'cancelled' || ! $guestBooking;
    $portalStatus = $booking->checked_out_at ? 'Checked out' : ($expiredHold ? 'Payment hold expired' : ucfirst($booking->status));
    $portalStatusClass = $booking->checked_out_at ? 'checked-out' : ($expiredHold ? 'expired' : strtolower($booking->status));
@endphp

<style>
    .guest-portal{--portal-surface:#fffaf3;--portal-border:#e6d8c8;--portal-subtle:#f5eee5;width:min(1040px,calc(100% - 40px));margin:clamp(32px,6vh,68px) auto 72px}
    .guest-portal-header{display:flex;align-items:flex-end;justify-content:space-between;gap:28px;margin-bottom:28px;padding-bottom:24px;border-bottom:1px solid var(--line)}
    .guest-portal-context{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:26px;padding:0}
    .guest-portal-back{display:inline-flex;align-items:center;gap:8px;min-height:40px;color:var(--ink);font-size:.84rem;font-weight:750;text-decoration:none}
    .guest-portal-back .site-back-button-icon{color:var(--gold)}
    .guest-portal-back:hover{color:var(--gold)}
    .guest-portal-identifiers{display:flex;align-items:stretch;min-width:0}
    .guest-portal-identifiers>div{display:grid;align-content:center;gap:3px;min-width:130px;padding:2px 13px}
    .guest-portal-identifiers>div+div{border-left:1px solid var(--line)}
    .guest-portal-identifiers small{color:var(--muted);font-size:.65rem;font-weight:800;letter-spacing:.09em;text-transform:uppercase}
    .guest-portal-identifiers strong{color:var(--ink);font-size:.86rem;line-height:1.35;overflow-wrap:anywhere}
    .guest-portal-heading{min-width:0}
    .guest-portal .eyebrow{margin:0 0 9px;color:var(--gold)}
    .guest-portal-header h1{margin:0 0 9px;font-size:clamp(2.5rem,5vw,3.8rem);line-height:1.03}
    .guest-portal-reference{margin:0;color:var(--muted);font-size:.98rem;line-height:1.5}
    .guest-portal-reference strong{color:var(--ink);font-weight:750;letter-spacing:.035em}
    .guest-arrival-link{flex:0 0 auto;min-height:48px;justify-content:center;gap:11px;padding:13px 18px;border-radius:11px;box-shadow:0 8px 20px rgba(128,77,12,.16)}
    .guest-arrival-link svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
    .guest-portal-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(280px,.8fr);align-items:stretch;gap:18px}
    .guest-portal-grid--single{grid-template-columns:minmax(0,1fr)}
    .portal-surface{min-width:0;padding:24px;border:1px solid var(--portal-border);border-radius:18px;background:var(--portal-surface);box-shadow:0 14px 38px rgba(48,34,19,.08)}
    .portal-summary-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:21px}
    .portal-kicker{display:block;margin-bottom:6px;color:var(--gold);font-size:.68rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
    .portal-summary-heading h2,.portal-action-card h2{margin:0;color:var(--ink);font:700 1.45rem/1.2 'DM Sans',sans-serif}
    .portal-status{display:inline-flex;align-items:center;gap:7px;flex:0 0 auto;padding:8px 11px;border:1px solid transparent;border-radius:999px;font-size:.76rem;font-weight:800}
    .portal-status:before{width:7px;height:7px;border-radius:50%;background:currentColor;content:''}
    .portal-status.confirmed{border-color:#b9dfc4;background:#e9f7ed;color:#21653a}
    .portal-status.pending{border-color:#efd9a7;background:#fff4da;color:#805100}
    .portal-status.checked-out{border-color:#cdd8e8;background:#eef3fa;color:#3b5879}
    .portal-status.cancelled,.portal-status.expired{border-color:#efc4bc;background:#fff0ed;color:#9a3829}
    .portal-facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .portal-fact{display:grid;align-content:start;gap:6px;min-height:88px;padding:14px 15px;border:1px solid var(--portal-border);border-radius:12px;background:var(--portal-subtle)}
    .portal-fact small{color:var(--muted);font-size:.78rem}
    .portal-fact strong{color:var(--ink);font-size:.98rem;line-height:1.4;overflow-wrap:anywhere}
    .portal-action-column{display:grid;align-content:start;gap:14px;min-width:0}
    .portal-action-card{display:flex;flex-direction:column;align-items:flex-start;min-height:100%;padding:24px;border:1px solid var(--portal-border);border-radius:18px;background:var(--portal-surface);box-shadow:0 14px 38px rgba(48,34,19,.08)}
    .portal-action-card h2{margin:0 0 10px}
    .portal-action-card p{margin:0 0 18px;color:var(--muted);font-size:.93rem;line-height:1.55}
    .portal-action-card .button{width:100%;min-height:46px;justify-content:center;margin-top:auto;border-radius:10px}
    .portal-action-card .button.danger{border:1px solid #e2b6aa;background:#fff1ed;color:#963c2c}
    .portal-action-card .button.danger:hover{background:#ffe5de}
    .portal-action-card .button.secondary{border:1px solid var(--portal-border);background:transparent;color:var(--ink)}
    .portal-action-card .button.secondary:hover{background:var(--portal-subtle)}
    .portal-window-note{display:flex;gap:10px;margin:0 0 18px;padding:12px;border:1px solid var(--portal-border);border-radius:11px;background:var(--portal-subtle);color:var(--muted);font-size:.85rem;line-height:1.45}
    .portal-window-note strong{color:var(--ink)}
    .portal-cancel-form{width:100%;margin-top:auto}
    .portal-cancel-form .button{margin-top:0}
    .portal-cancel-form p{margin:0 0 14px;font-size:.78rem}
    .portal-active-stay-card{border-color:#e7c18e;background:linear-gradient(145deg,#fff9ef,#fffaf4)}
    .portal-stay-intro{margin-bottom:16px!important}
    .portal-hour-picker{display:grid;width:100%;gap:8px;margin:0 0 12px}
    .portal-hour-picker>label{color:var(--ink);font-size:.78rem;font-weight:800}
    .portal-hour-stepper{display:grid;grid-template-columns:44px minmax(0,1fr) 44px;gap:7px}
    .portal-hour-stepper button{width:44px;height:44px;border:1px solid var(--portal-border);border-radius:10px;background:var(--portal-subtle);color:var(--ink);font-size:1.25rem;font-weight:800;cursor:pointer}
    .portal-hour-stepper input{width:100%;min-width:0;height:44px;border:1px solid var(--portal-border);border-radius:10px;background:var(--portal-surface);color:var(--ink);text-align:center;font:700 1rem 'DM Sans',sans-serif}
    .portal-hour-picker small{color:var(--muted);font-size:.75rem;line-height:1.4}
    .portal-extension-total{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;margin:2px 0 16px;padding:12px 14px;border:1px solid #e7d0ae;border-radius:12px;background:#fffdf8}
    .portal-extension-total span{color:var(--muted);font-size:.8rem}
    .portal-extension-total strong{color:#8d4b0b;font-size:1.08rem;font-weight:850}
    .portal-extension-error{width:100%;margin:0 0 12px!important;color:#a33932!important;font-size:.82rem!important;font-weight:700}
    .portal-early-card{border-color:#d8cfc4;background:linear-gradient(145deg,#fbf8f3,#f7f1e8)}
    .portal-early-policy{width:100%;margin:0 0 16px;padding:11px 12px;border:1px solid var(--portal-border);border-radius:11px;background:var(--portal-subtle);color:var(--muted);font-size:.78rem;line-height:1.5}
    .portal-early-policy strong{color:var(--ink)}
    .portal-early-button{border:1px solid #c9b9a6!important;background:#f6efe5!important;color:#513c2b!important}
    .portal-early-button:hover{background:#eee2d3!important}
    .portal-action-dialog{width:min(480px,calc(100% - 28px));max-width:none;max-height:min(90dvh,700px);margin:auto;padding:26px;border:1px solid #dfcbb1;border-radius:20px;background:var(--portal-surface);color:var(--ink);box-shadow:0 28px 90px rgba(35,24,14,.35)}
    .portal-action-dialog::backdrop{background:rgba(24,18,13,.62);backdrop-filter:blur(4px)}
    .portal-dialog-top{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:10px}
    .portal-dialog-top .portal-kicker{margin:0}
    .portal-dialog-close{display:grid;place-items:center;width:36px;height:36px;border:1px solid var(--portal-border);border-radius:50%;background:transparent;color:var(--muted);font-size:1.25rem;cursor:pointer}
    .portal-action-dialog h2{margin:0 0 10px;color:var(--ink);font:750 1.55rem/1.15 'DM Sans',sans-serif}
    .portal-action-dialog p{margin:0 0 18px;color:var(--muted);font-size:.92rem;line-height:1.55}
    .portal-dialog-total{display:flex;justify-content:space-between;gap:12px;margin:0 0 20px;padding:14px;border:1px solid #e7d0ae;border-radius:12px;background:var(--portal-subtle)}
    .portal-dialog-total span{color:var(--muted);font-size:.83rem}
    .portal-dialog-total strong{color:#8d4b0b;font-size:1.05rem}
    .portal-dialog-actions{display:grid;grid-template-columns:1fr 1fr;gap:9px}
    .portal-dialog-actions button{min-height:46px;padding:10px;border:1px solid var(--portal-border);border-radius:10px;background:transparent;color:var(--ink);font:700 .85rem 'DM Sans',sans-serif;cursor:pointer}
    .portal-dialog-actions button[data-dialog-confirm]{border-color:#a95308;background:#a95308;color:#fff}
    .portal-early-dialog .portal-dialog-actions button[data-dialog-confirm]{border-color:#a23e30;background:#a23e30}
    .dark-mode .portal-active-stay-card{border-color:#79582e;background:linear-gradient(145deg,#342719,#251d17)}
    .dark-mode .portal-extension-total,.dark-mode .portal-dialog-total{border-color:#665034;background:#2a2119}
    .dark-mode .portal-extension-total strong,.dark-mode .portal-dialog-total strong{color:#ffc16b}
    .dark-mode .portal-early-card{border-color:#514638;background:linear-gradient(145deg,#2b241d,#211a15)}
    .dark-mode .portal-early-policy{border-color:#514638;background:#29211b}
    .dark-mode .portal-early-policy strong{color:var(--ink)}
    .dark-mode .portal-early-button{border-color:#655641!important;background:#30271e!important;color:#f6e9d8!important}
    .dark-mode .portal-dialog-actions button[data-dialog-confirm]{color:#fff}
    .dark-mode .portal-early-dialog .portal-dialog-actions button[data-dialog-confirm]{background:#963c2c}
    .dark-mode .portal-status.checked-out{border-color:#48586c;background:#252f3a;color:#c4d6ef}
    .portal-reschedule-form{display:grid;width:100%;gap:10px;margin:0 0 20px;padding:14px;border:1px solid var(--portal-border);border-radius:11px;background:var(--portal-subtle)}
    .portal-reschedule-form label{display:grid;gap:5px;color:var(--muted);font-size:.8rem;font-weight:700}
    .portal-reschedule-form input{width:100%;min-width:0;padding:10px;border:1px solid var(--portal-border);border-radius:8px;background:var(--portal-surface);color:var(--ink);font:inherit}
    .portal-reschedule-form small{color:var(--muted);font-size:.76rem;line-height:1.4}
    .portal-reschedule-form .button{margin-top:0}
    .portal-reschedule-error{color:#a33932!important}
    .portal-help-link{margin-top:14px;color:var(--gold);font-size:.86rem;font-weight:750;text-decoration:underline;text-underline-offset:3px}
    .portal-status-message{margin:0;color:var(--muted);font-size:.94rem;line-height:1.55}
    .dark-mode .guest-portal{--portal-surface:#211a15;--portal-border:#514032;--portal-subtle:#29211b}
    .dark-mode .portal-surface,.dark-mode .portal-action-card{box-shadow:0 16px 38px rgba(0,0,0,.22)}
    .dark-mode .portal-status.confirmed{border-color:#315c3a;background:#1d3323;color:#b9edc3}
    .dark-mode .portal-status.pending{border-color:#74572a;background:#392b18;color:#f2d293}
    .dark-mode .portal-status.cancelled,.dark-mode .portal-status.expired{border-color:#73463d;background:#3a211d;color:#f3b3a8}
    .dark-mode .portal-action-card .button.danger{border-color:#77483e;background:#3a211d;color:#ffc0b5}
    .dark-mode .portal-action-card .button.danger:hover{background:#4a2923}
    .dark-mode .portal-action-card .button.secondary{border-color:var(--portal-border);color:var(--ink)}
    .guest-portal :focus-visible{outline:3px solid #d99223;outline-offset:3px}
    @media(max-width:760px){.guest-portal{width:min(100% - 32px,620px);margin:32px auto 48px}.guest-portal-context{align-items:flex-start;flex-direction:column;gap:10px;margin-bottom:20px}.guest-portal-identifiers{width:100%}.guest-portal-identifiers>div{min-width:0;flex:1;padding:2px 10px}.guest-portal-identifiers>div:first-child{padding-left:0}.guest-portal-header{align-items:stretch;flex-direction:column;gap:18px;margin-bottom:18px;padding-bottom:20px}.guest-arrival-link{width:100%}.guest-portal-grid{grid-template-columns:1fr;gap:14px}.portal-surface,.portal-action-card{padding:20px}}
    @media(max-width:480px){.guest-portal{width:calc(100% - 24px);margin-top:24px}.guest-portal-header h1{font-size:2.55rem}.portal-summary-heading{align-items:flex-start;flex-direction:column;gap:12px}.portal-facts{grid-template-columns:1fr}.portal-fact{min-height:72px;padding:12px 14px}.portal-surface,.portal-action-card{padding:18px;border-radius:15px}.portal-action-dialog{padding:21px;border-radius:17px}.portal-dialog-actions{grid-template-columns:1fr}.portal-hour-stepper{grid-template-columns:44px minmax(0,1fr) 44px}}
</style>

<section class="guest-portal" aria-labelledby="guest-portal-title">
    <nav class="guest-portal-context" aria-label="Booking context">
        <x-site-back-link :href="route('bookings.lookup')" label="Back to Find booking" class="guest-portal-back" />
        <div class="guest-portal-identifiers" role="group" aria-label="Room and booking reference">
            <div><small>Room</small><strong>{{ $booking->room?->name ?? 'Room unavailable' }}</strong></div>
            <div><small>Booking reference</small><strong>{{ $booking->reference }}</strong></div>
        </div>
    </nav>
    <header class="guest-portal-header">
        <div class="guest-portal-heading">
            <span class="eyebrow">GUEST PORTAL</span>
            <h1 id="guest-portal-title">Your booking</h1>
            <p class="guest-portal-reference">Manage your reservation and stay details.</p>
        </div>
        <a class="button guest-arrival-link" href="{{ \App\Http\Controllers\StayController::link($booking) }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h16M13 5l7 7-7 7"/></svg>
            <span>Arrival updates and room readiness</span>
        </a>
    </header>

    <div class="guest-portal-grid {{ $hasPortalAside ? '' : 'guest-portal-grid--single' }}">
        <section class="portal-surface portal-summary" aria-label="Booking summary">
            <div class="portal-summary-heading">
                <div><span class="portal-kicker">YOUR STAY</span><h2>Booking summary</h2></div>
                <span class="portal-status {{ $portalStatusClass }}">{{ $portalStatus }}</span>
            </div>
            <div class="portal-facts">
                <div class="portal-fact"><small>Stay</small><strong>{{ $booking->check_in_at ? $booking->check_in_at->format('M j, g A') . ' – ' . $booking->check_out_at->format('M j, g A') : $booking->check_in->format('M j') . ' – ' . $booking->check_out->format('M j, Y') }}</strong></div>
                <div class="portal-fact"><small>Total</small><strong>₱{{ number_format($booking->total_amount, 2) }}</strong></div>
                <div class="portal-fact"><small>Stay status</small><strong>{{ $booking->checked_out_at ? 'Checked out · '.$booking->checked_out_at->format('M j, g:i A') : ($booking->checked_in_at ? 'Checked in' : 'Awaiting arrival') }}</strong></div>
                <div class="portal-fact"><small>Room</small><strong>{{ $booking->room->name }}</strong></div>
            </div>
        </section>

        @if($hasPortalAside)
        <aside class="portal-action-column" aria-label="Booking actions">
            @if($showExtension)
                <section class="portal-action-card portal-active-stay-card">
                    <span class="portal-kicker">ACTIVE STAY · EXTRA TIME</span>
                    <h2>Stay a little longer</h2>
                    <p class="portal-stay-intro">Choose how many extra hours you need. Each hour is ₱100, and we’ll check that the room is available.</p>
                    @if($errors->has('extension') || $errors->has('hours'))<p class="portal-extension-error" role="alert">{{ $errors->first('extension') ?: $errors->first('hours') }}</p>@endif
                    <form id="portal-extension-form" class="portal-cancel-form" method="POST" action="{{ route('bookings.lookup.extend', $booking) }}">
                        @csrf
                        <div class="portal-hour-picker">
                            <label for="portal-extension-hours">Extra hours</label>
                            <div class="portal-hour-stepper">
                                <button type="button" data-hours-step="-1" aria-label="Remove one hour">−</button>
                                <input id="portal-extension-hours" name="hours" type="number" min="1" max="168" step="1" inputmode="numeric" value="{{ $extensionHours }}" data-rate="{{ \App\Support\StayPricing::EXTRA_HOUR_RATE }}" required aria-describedby="portal-hours-help">
                                <button type="button" data-hours-step="1" aria-label="Add one hour">+</button>
                            </div>
                            <small id="portal-hours-help">1–168 hours · ₱{{ number_format(\App\Support\StayPricing::EXTRA_HOUR_RATE, 0) }} per hour. Longer stays can be arranged with reception.</small>
                        </div>
                        <div class="portal-extension-total"><span>Additional charge</span><strong id="portal-extension-total" aria-live="polite">₱{{ number_format($extensionHours * \App\Support\StayPricing::EXTRA_HOUR_RATE, 0) }}</strong></div>
                        <button class="button" type="submit">Review stay extension</button>
                    </form>
                </section>
            @endif

            @if($booking->early_checkout_status === 'requested')
                <section class="portal-action-card portal-early-card">
                    <span class="portal-kicker">REQUEST SENT</span>
                    <h2>Waiting for reception</h2>
                    <p>Your early check-out request is waiting for staff confirmation. You remain checked in until reception records your departure.</p>
                    @if($earlyCheckoutBalance > 0.001)
                        <p role="status">Pay the remaining balance of ₱{{ number_format($earlyCheckoutBalance, 2) }} before reception can confirm early check-out.</p>
                    @endif
                </section>
            @elseif($booking->early_checkout_status === 'declined')
                <section class="portal-action-card portal-early-card">
                    <span class="portal-kicker">REQUEST UPDATE</span>
                    <h2>Please contact reception</h2>
                    <p>Reception could not confirm your request. Your stay remains active until staff records your departure.</p>
                    <a class="portal-help-link" href="{{ route('contact') }}">Contact Carolina</a>
                </section>
            @elseif($showEarlyCheckout)
                <section class="portal-action-card portal-early-card">
                    <span class="portal-kicker">LEAVING AHEAD OF SCHEDULE?</span>
                    <h2>Check out early</h2>
                    <p>Ask reception to confirm your departure. You remain checked in until staff approves the request.</p>
                    <div class="portal-early-policy"><strong>Booking total stays the same.</strong> Refunds are handled separately.</div>
                    <form id="portal-early-checkout-form" class="portal-cancel-form" method="POST" action="{{ route('bookings.lookup.early-checkout', $booking) }}">
                        @csrf
                        <button class="button portal-early-button" type="submit">Request early check-out</button>
                    </form>
                </section>
            @elseif($earlyCheckoutBlockedByBalance)
                <section class="portal-action-card portal-early-card">
                    <span class="portal-kicker">BALANCE DUE</span>
                    <h2>Settle your balance first</h2>
                    <p>Pay the remaining balance of ₱{{ number_format($earlyCheckoutBalance, 2) }} to reception before requesting an early check-out.</p>
                </section>
            @endif

            @if($expiredHold)
                <section class="portal-action-card">
                    <span class="portal-kicker">PAYMENT WINDOW</span>
                    <h2>Payment hold expired</h2>
                    <p>This reservation no longer holds the room for these dates. You can look for another available stay.</p>
                    <a class="button" href="{{ route('rooms.index') }}">Find another room</a>
                    <a class="button secondary" href="{{ \App\Http\Controllers\StayController::link($booking) }}">View payment status</a>
                </section>
            @elseif($canResumePayment)
                <section class="portal-action-card">
                    <span class="portal-kicker">NEXT STEP</span>
                    <h2>Finish your reservation</h2>
                    <p>Your room is temporarily held. Continue to secure checkout before the hold expires.</p>
                    <a class="button" href="{{ route('bookings.receipt', $booking) }}">Continue payment</a>
                </section>
            @endif

            @if($showCancellation)
                <section class="portal-action-card">
                    <span class="portal-kicker">RESERVATION OPTIONS</span>
                    <h2>Manage booking</h2>
                    @if($booking->cancellation_request_status === 'requested')
                        <p role="status">Your cancellation request was sent. The booking remains active until reception confirms it.</p>
                    @elseif($booking->cancellation_request_status === 'declined')
                        <p role="status">Reception could not approve your cancellation request. Your booking remains active.</p>
                        <a class="portal-help-link" href="{{ route('contact') }}">Contact Carolina</a>
                    @else
                        @if($canReschedule)
                            <p>Choose a new check-in at least 3 days ahead. Your room, stay length, and price stay the same. {{ $booking->reschedulesRemaining() }} of 2 reschedules remaining.</p>
                            <form class="portal-reschedule-form" method="POST" action="{{ route('bookings.lookup.reschedule', $booking) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="email" value="{{ $lookupEmail }}">
                                <input type="hidden" name="reference" value="{{ $booking->reference }}">
                                @if($booking->booking_type === 'hourly')
                                    <label>New check-in time<input type="datetime-local" name="new_check_in_at" min="{{ \App\Support\PropertyTime::now()->addDays(3)->format('Y-m-d\\TH:i') }}" required></label>
                                @else
                                    <label>New check-in date<input type="date" name="new_check_in" min="{{ \App\Support\PropertyTime::now()->addDays(3)->toDateString() }}" required></label>
                                @endif
                                @error('reschedule')<small class="portal-reschedule-error">{{ $message }}</small>@enderror
                                <button class="button secondary" type="submit">Save new time</button>
                            </form>
                        @elseif($booking->reschedulesRemaining() === 0)
                            <p>Online rescheduling limit reached (2). Contact Carolina if you need to change your stay.</p>
                        @endif
                        @if($canCancel)
                            <p>Your booking remains active until reception confirms the cancellation.</p>
                            <form class="portal-cancel-form" method="POST" action="{{ route('bookings.lookup.cancel', $booking) }}" data-booking-cancel-confirm>
                                @csrf @method('PATCH')
                                <input type="hidden" name="email" value="{{ $lookupEmail }}">
                                <input type="hidden" name="reference" value="{{ $booking->reference }}">
                                <button class="button danger" type="submit">Request cancellation</button>
                            </form>
                        @else
                            <p>Online cancellation closes at the scheduled check-in time. Contact Carolina if you need help with this reservation.</p>
                            <a class="portal-help-link" href="https://www.facebook.com/profile.php?id=61556306344437" target="_blank" rel="noopener noreferrer">Message Carolina</a>
                        @endif
                    @endif
                </section>
            @elseif($booking->checked_out_at)
                <section class="portal-action-card">
                    <span class="portal-kicker">STAY UPDATE</span>
                    <h2>Check-out recorded</h2>
                    <p class="portal-status-message">Your check-out was recorded for {{ $booking->checked_out_at->format('M j, Y · g:i A') }}. Your original booking total remains unchanged; any outstanding balance is still due.</p>
                </section>
            @elseif($booking->status === 'cancelled')
                <section class="portal-action-card">
                    <span class="portal-kicker">RESERVATION UPDATE</span>
                    <h2>Booking cancelled</h2>
                    <p class="portal-status-message">This booking has been cancelled and the room dates are available again.</p>
                </section>
            @elseif(! $guestBooking)
                <section class="portal-action-card">
                    <span class="portal-kicker">YOUR ACCOUNT</span>
                    <h2>Manage your bookings</h2>
                    <p>Sign in to see all reservations connected to your account.</p>
                    <a class="button" href="{{ auth()->id() === $booking->user_id ? route('bookings.index') : route('login') }}">{{ auth()->id() === $booking->user_id ? 'View bookings' : 'Sign in' }}</a>
                </section>
            @endif
        </aside>
        @endif
    </div>
</section>
@if($showExtension)
    <dialog class="portal-action-dialog" id="portal-extension-dialog" aria-labelledby="portal-extension-dialog-title" aria-describedby="portal-extension-dialog-copy">
        <div class="portal-dialog-top"><span class="portal-kicker">CONFIRM EXTRA TIME</span><button class="portal-dialog-close" type="button" data-dialog-close aria-label="Close confirmation">×</button></div>
        <h2 id="portal-extension-dialog-title">Extend your stay?</h2>
        <p id="portal-extension-dialog-copy">We’ll confirm availability before changing your checkout time. The added charge is ₱{{ number_format(\App\Support\StayPricing::EXTRA_HOUR_RATE, 0) }} per extra hour.</p>
        <div class="portal-dialog-total"><span><strong id="portal-confirm-hours">{{ $extensionHours }} extra hour{{ $extensionHours === 1 ? '' : 's' }}</strong></span><strong id="portal-confirm-total">₱{{ number_format($extensionHours * \App\Support\StayPricing::EXTRA_HOUR_RATE, 0) }}</strong></div>
        <div class="portal-dialog-actions"><button type="button" data-dialog-close>Keep current checkout</button><button type="button" data-dialog-confirm>Confirm extension</button></div>
    </dialog>
@endif
@if($showEarlyCheckout)
    <dialog class="portal-action-dialog portal-early-dialog" id="portal-early-checkout-dialog" aria-labelledby="portal-early-dialog-title" aria-describedby="portal-early-dialog-copy">
        <div class="portal-dialog-top"><span class="portal-kicker">EARLY CHECK-OUT REQUEST</span><button class="portal-dialog-close" type="button" data-dialog-close aria-label="Close confirmation">×</button></div>
        <h2 id="portal-early-dialog-title">Send this request?</h2>
        <p id="portal-early-dialog-copy">Reception must confirm your departure. Your stay remains active until staff records check-out. Your booking total stays the same; refunds are handled separately.</p>
        <div class="portal-dialog-actions"><button type="button" data-dialog-close>Go back</button><button type="button" data-dialog-confirm>Send request</button></div>
    </dialog>
@endif
<script>
    (() => {
        const extensionForm = document.getElementById('portal-extension-form');
        if (extensionForm) {
            const hoursInput = document.getElementById('portal-extension-hours');
            const total = document.getElementById('portal-extension-total');
            const dialog = document.getElementById('portal-extension-dialog');
            const confirmHours = document.getElementById('portal-confirm-hours');
            const confirmTotal = document.getElementById('portal-confirm-total');
            const rate = Number(hoursInput.dataset.rate || 100);
            const formatMoney = amount => '₱' + new Intl.NumberFormat('en-PH', { maximumFractionDigits: 0 }).format(amount);
            const updateExtensionTotal = () => {
                const hours = Math.max(1, Math.min(168, Number.parseInt(hoursInput.value, 10) || 1));
                const amount = hours * rate;
                total.textContent = formatMoney(amount);
                confirmHours.textContent = `${hours} extra hour${hours === 1 ? '' : 's'}`;
                confirmTotal.textContent = formatMoney(amount);
            };

            hoursInput.addEventListener('input', updateExtensionTotal);
            extensionForm.querySelectorAll('[data-hours-step]').forEach(control => control.addEventListener('click', () => {
                const current = Number.parseInt(hoursInput.value, 10) || 1;
                hoursInput.value = String(Math.max(1, Math.min(168, current + Number(control.dataset.hoursStep))));
                updateExtensionTotal();
            }));
            updateExtensionTotal();
            extensionForm.addEventListener('submit', event => {
                if (extensionForm.dataset.confirmed === 'true') {
                    delete extensionForm.dataset.confirmed;
                    return;
                }
                event.preventDefault();
                updateExtensionTotal();
                if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
                else if (window.confirm(`Add ${hoursInput.value} extra hour(s) for ${formatMoney(Number(hoursInput.value) * rate)}?`)) {
                    extensionForm.dataset.confirmed = 'true';
                    extensionForm.requestSubmit();
                }
            });
            dialog?.querySelectorAll('[data-dialog-close]').forEach(control => control.addEventListener('click', () => dialog.close()));
            dialog?.querySelector('[data-dialog-confirm]')?.addEventListener('click', () => {
                dialog.close();
                extensionForm.dataset.confirmed = 'true';
                extensionForm.requestSubmit();
            });
        }

        const checkoutForm = document.getElementById('portal-early-checkout-form');
        const checkoutDialog = document.getElementById('portal-early-checkout-dialog');
        if (checkoutForm && checkoutDialog) {
            checkoutForm.addEventListener('submit', event => {
                if (checkoutForm.dataset.confirmed === 'true') {
                    delete checkoutForm.dataset.confirmed;
                    return;
                }
                event.preventDefault();
                if (typeof checkoutDialog.showModal === 'function') checkoutDialog.showModal();
                else if (window.confirm('Send an early check-out request? Your stay remains active until reception confirms your departure.')) {
                    checkoutForm.dataset.confirmed = 'true';
                    checkoutForm.requestSubmit();
                }
            });
            checkoutDialog.querySelectorAll('[data-dialog-close]').forEach(control => control.addEventListener('click', () => checkoutDialog.close()));
            checkoutDialog.querySelector('[data-dialog-confirm]')?.addEventListener('click', () => {
                checkoutDialog.close();
                checkoutForm.dataset.confirmed = 'true';
                checkoutForm.requestSubmit();
            });
        }
    })();
</script>
@include('bookings.cancel-dialog')
@endsection
