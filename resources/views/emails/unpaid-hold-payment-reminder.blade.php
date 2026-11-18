<h1>Your Carolina payment window is closing</h1>
<p>Hello {{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }},</p>
<p>Your unpaid room hold for <strong>{{ $booking->reference }}</strong> is due to expire at {{ ($booking->holdDeadline() ?? now())->copy()->timezone('Asia/Manila')->format('M j, Y, g:i A') }} Philippine time.</p>
<p>Complete your selected payment before then to keep the reservation. <a href="{{ route('bookings.lookup', ['reference' => $booking->reference]) }}">Open your booking and finish payment</a>.</p>
<p>If you have already paid, please ignore this reminder. The room will be released if payment is not recorded before the hold expires.</p>
