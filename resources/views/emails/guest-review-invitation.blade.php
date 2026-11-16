<h1>Thank you for staying with Carolina</h1>
<p>Hello {{ $booking->guest_name ?: $booking->user?->name ?: 'Guest' }},</p>
<p>We hope you enjoyed your stay in {{ $booking->room?->name ?? 'your room' }}. If you have a moment, please share feedback about your experience.</p>
<p><a href="{{ $reviewUrl }}">Share feedback</a></p>
<p>This private link expires 30 days after checkout. You can submit one review for this stay. Your review is sent as written and will only appear publicly if Carolina publishes it without changes. No account is needed.</p>
<p>Carolina Transient &amp; Airbnb</p>
