@extends('layouts.app')

@section('content')
@php
    $expiredHold = $booking->hasExpiredHold() || $booking->isExpiredHoldRecord();
    $guestBooking = $booking->user_id === null;
    $canResumePayment = ! $expiredHold && $booking->status === 'pending' && ! $booking->hasProtectedInventory();
    $showCancellation = $guestBooking && $booking->status !== 'cancelled' && ! $expiredHold;
    $canCancel = $showCancellation && $booking->canCancelOnline();
    $hasPortalAside = $expiredHold || $canResumePayment || $showCancellation || $booking->status === 'cancelled' || ! $guestBooking;
    $portalStatus = $expiredHold ? 'Payment hold expired' : ucfirst($booking->status);
    $portalStatusClass = $expiredHold ? 'expired' : strtolower($booking->status);
@endphp

<style>
    .guest-portal{--portal-surface:#fffaf3;--portal-border:#e6d8c8;--portal-subtle:#f5eee5;width:min(1040px,calc(100% - 40px));margin:clamp(32px,6vh,68px) auto 72px}
    .guest-portal-header{display:flex;align-items:flex-end;justify-content:space-between;gap:28px;margin-bottom:28px;padding-bottom:24px;border-bottom:1px solid var(--line)}
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
    @media(max-width:760px){.guest-portal{width:min(100% - 32px,620px);margin:32px auto 48px}.guest-portal-header{align-items:stretch;flex-direction:column;gap:18px;margin-bottom:18px;padding-bottom:20px}.guest-arrival-link{width:100%}.guest-portal-grid{grid-template-columns:1fr;gap:14px}.portal-surface,.portal-action-card{padding:20px}}
    @media(max-width:480px){.guest-portal{width:calc(100% - 24px);margin-top:24px}.guest-portal-header h1{font-size:2.55rem}.portal-summary-heading{align-items:flex-start;flex-direction:column;gap:12px}.portal-facts{grid-template-columns:1fr}.portal-fact{min-height:72px;padding:12px 14px}.portal-surface,.portal-action-card{padding:18px;border-radius:15px}}
</style>

<section class="guest-portal" aria-labelledby="guest-portal-title">
    <header class="guest-portal-header">
        <div class="guest-portal-heading">
            <span class="eyebrow">GUEST PORTAL</span>
            <h1 id="guest-portal-title">{{ $booking->room->name }}</h1>
            <p class="guest-portal-reference">Manage your reservation · Reference <strong>{{ $booking->reference }}</strong></p>
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
                <div class="portal-fact"><small>Check-in</small><strong>{{ $booking->checked_in_at ? 'Checked in' : 'Awaiting arrival' }}</strong></div>
                <div class="portal-fact"><small>Room</small><strong>{{ $booking->room->name }}</strong></div>
            </div>
        </section>

        @if($hasPortalAside)
        <aside class="portal-action-column" aria-label="Booking actions">
            @if($expiredHold)
                <section class="portal-action-card">
                    <span class="portal-kicker">PAYMENT WINDOW</span>
                    <h2>Payment hold expired</h2>
                    <p>This reservation no longer holds the room for these dates. You can look for another available stay.</p>
                    <a class="button" href="{{ route('rooms.index') }}">Find another room</a>
                    <a class="button secondary" href="{{ route('bookings.receipt', $booking) }}">View payment status</a>
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
                    <h2>{{ $canCancel ? 'Cancel booking' : 'Cancellation window closed' }}</h2>
                    @if($canCancel)
                        <p>Cancel at least 3 days before check-in to qualify for a refund. The room will reopen for these dates after cancellation.</p>
                        <form class="portal-cancel-form" method="POST" action="{{ route('bookings.lookup.cancel', $booking) }}" onsubmit="return confirm('Cancel this booking? The room will become available again for these dates.');">
                            @csrf @method('PATCH')
                            <input type="hidden" name="email" value="{{ $lookupEmail }}">
                            <input type="hidden" name="reference" value="{{ $booking->reference }}">
                            <button class="button danger" type="submit">Cancel booking</button>
                        </form>
                    @else
                        <p>Online cancellation is closed within 3 days of check-in. Contact Carolina if you need help with this reservation.</p>
                        <a class="portal-help-link" href="https://www.facebook.com/profile.php?id=61556306344437" target="_blank" rel="noopener noreferrer">Message Carolina</a>
                    @endif
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
@endsection
