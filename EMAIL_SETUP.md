# Production email setup

The application already sends guest booking updates and password-reset links. It can also email staff when a new reservation is submitted.

## Recommended provider: Resend SMTP

1. Create and verify a sending domain in Resend. Use an address at that domain, for example `bookings@yourdomain.com`.
2. In the Render service **Environment** page, add the following values. Never commit these values to Git.

```text
MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=resend
MAIL_PASSWORD=<your Resend API key>
MAIL_FROM_ADDRESS=bookings@yourdomain.com
MAIL_FROM_NAME="Carolina Transient & Airbnb"
MAIL_ADMIN_ADDRESS=<the Carolina staff inbox>
```

3. Deploy, then submit one controlled booking using an email address you can read. The guest and `MAIL_ADMIN_ADDRESS` should both receive an email.

Until those values are configured, the app uses Laravel's log mailer. Booking and password-reset pages remain functional; delivery is simply not attempted through a real email provider.

## Included messages

- Reservation request received (guest)
- New reservation request (staff inbox)
- Booking confirmed, cancelled, extended, checked in, or checked out (guest)
- Password reset (account holder)

Use a verified domain before sending real guest emails. Do not use a personal mailbox as the From address for a production reservation system.
