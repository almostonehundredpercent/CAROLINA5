<h1>Carolina booking update</h1>
<p>Hello {{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }},</p>
<p>{{ $messageLine }}</p>
<p><strong>{{ $booking->room->name }}</strong><br>Reference: {{ $booking->reference }}<br>Total: ₱{{ number_format($booking->total_amount, 2) }}</p>
