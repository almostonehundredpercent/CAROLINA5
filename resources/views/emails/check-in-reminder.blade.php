<h1>Your check-in is coming up</h1>
<p>Hello {{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }},</p>
<p>This is a reminder that your check-in is coming up.</p>
<p><strong>{{ $booking->room?->name ?? 'Your room' }}</strong><br>
    {{ $booking->check_in_at?->format('M j, Y, g:i A') ?? $booking->check_in?->format('M j, Y') }} Philippine time</p>
<p>Booking reference: <strong>{{ $booking->reference }}</strong></p>
<p>We look forward to welcoming you.</p>
