<h1>Thank you for booking with Carolina</h1>
<p>Hello {{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }},</p>
<p>We received your reservation request. Your booking reference is <strong>{{ $booking->reference }}</strong>.</p>
<p><strong>{{ $booking->room->name }}</strong><br>{{ $booking->check_in->format('M j, Y') }} to {{ $booking->check_out->format('M j, Y') }} ({{ $booking->nights }} nights)<br>Total: ₱{{ number_format($booking->total_amount) }}</p>
<p>Payment method: {{ strtoupper($booking->payment_method) }}. Payment instructions will follow when your reservation is confirmed.</p>
<p>To view your booking without an account, use your email and reference code on the Carolina booking lookup page.</p>
