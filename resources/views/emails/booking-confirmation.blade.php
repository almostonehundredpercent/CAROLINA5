<h1>Thank you for booking with Carolina</h1>
<p><a href="{{ \App\Http\Controllers\StayController::link($booking) }}">Open your private arrival page</a> to share travel updates and see preparation updates from reception. This link expires in seven days; booking lookup can provide a fresh link.</p>
<p>Hello {{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }},</p>
<p>We received your reservation request. Your booking reference is <strong>{{ $booking->reference }}</strong>.</p>
<p><strong>{{ $booking->room->name }}</strong><br>{{ $booking->check_in->format('M j, Y') }} to {{ $booking->check_out->format('M j, Y') }} ({{ $booking->nights }} nights)<br>Total: ₱{{ number_format($booking->total_amount) }}</p>
<p>No online payment is collected on this website. Carolina will contact you after reviewing availability. Please keep your booking reference, {{ $booking->reference }}, for your records.</p>
<p>To view your booking without an account, use your email and reference code on the Carolina booking lookup page.</p>
