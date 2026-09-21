<h1>New reservation request</h1>
<p>A guest has submitted a reservation that needs staff review.</p>
<p>
    <strong>Reference:</strong> {{ $booking->reference }}<br>
    <strong>Guest:</strong> {{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }}<br>
    <strong>Email:</strong> {{ $booking->guest_email ?? $booking->user?->email ?? 'Not provided' }}<br>
    <strong>Phone:</strong> {{ $booking->guest_phone ?? 'Not provided' }}
</p>
<p>
    <strong>Room:</strong> {{ $booking->room->name }}<br>
    <strong>Stay:</strong> {{ $booking->check_in_at?->format('M j, Y g:i A') }} – {{ $booking->check_out_at?->format('M j, Y g:i A') }}<br>
    <strong>Guests:</strong> {{ $booking->guests }}<br>
    <strong>Total:</strong> ₱{{ number_format($booking->total_amount, 2) }}
</p>
<p>Open the Carolina admin bookings page to confirm, cancel, or contact the guest.</p>
