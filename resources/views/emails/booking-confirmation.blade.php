<h1>Thank you for booking with Carolina</h1>
<p>Hello {{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }},</p>
<p>We received your reservation request. Your booking reference is <strong>{{ $booking->reference }}</strong>.</p>
<p><strong>{{ $booking->room->name }}</strong><br>{{ $booking->check_in->format('M j, Y') }} to {{ $booking->check_out->format('M j, Y') }} ({{ $booking->nights }} nights)<br>Total: ₱{{ number_format($booking->total_amount) }}</p>
<p>A GCash reservation deposit of ₱{{ number_format($booking->deposit_amount, 2) }} is required before Carolina confirms your booking. Use your booking reference, {{ $booking->reference }}, when submitting your payment proof.</p>
<p>To view your booking without an account, use your email and reference code on the Carolina booking lookup page.</p>
