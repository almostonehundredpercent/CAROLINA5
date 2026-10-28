<h1>Thank you for booking with Carolina</h1>
<p><a href="{{ \App\Http\Controllers\StayController::link($booking) }}">Open your private arrival page</a> to share travel updates and see preparation updates from reception. This link expires in seven days; booking lookup can provide a fresh link.</p>
<p>Hello {{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }},</p>
<p>We received your reservation request. Your booking reference is <strong>{{ $booking->reference }}</strong>.</p>
<p><strong>{{ $booking->room->name }}</strong><br>{{ $booking->check_in->format('M j, Y') }} to {{ $booking->check_out->format('M j, Y') }} ({{ $booking->nights }} nights)<br>Total: ₱{{ number_format($booking->total_amount) }}@if($booking->discount_amount > 0)<br>Promo {{ $booking->promo_code }} saved ₱{{ number_format($booking->discount_amount) }}.@endif</p>
<p>Your unpaid room hold lasts {{ \App\Models\Booking::holdMinutes() }} minutes and ends {{ ($booking->holdDeadline() ?? now())->copy()->timezone('Asia/Manila')->format('M j, Y, g:i A') }} Philippine time. To resume payment or check your reservation, visit <a href="{{ route('bookings.lookup', ['reference' => $booking->reference]) }}">Carolina booking lookup</a>, then enter the email address and reference from this message. If payment is not recorded before the hold ends, the room is released.</p>
@if(config('services.paymongo.mode') === 'test')<p>Payments are in test mode for thesis testing only; no real money will be collected.</p>@endif
<p>Please keep your booking reference, {{ $booking->reference }}, for your records.</p>
