# Brevo email setup

The `brevo` mailer uses the Symfony Brevo HTTPS API transport, not SMTP.
Set these in Render Environment after deploying the integration:

```dotenv
MAIL_MAILER=brevo
BREVO_API_KEY=<enter the replacement API key privately>
MAIL_FROM_ADDRESS=carolinatest190@gmail.com
MAIL_FROM_NAME=Carolina
```

The sender must be verified and approved by Brevo. A verified custom domain is
recommended for production. Never commit the API key or paste it into chat.
Save and redeploy so the cached configuration and mail worker use the new values.
Existing Gmail SMTP values are ignored while MAIL_MAILER is brevo.

Acceptance: request a fresh verification message and password reset using a
controlled account, confirm Brevo's transactional logs show delivery, then check
the inbox/spam folder and use each link. API acceptance alone is not proof of
inbox delivery. Test booking mail with a clearly identified test booking.
Do not blindly retry all failed jobs: verification/reset links may be expired,
and booking status may have changed. Request fresh messages instead.

If setup fails, inspect the provider rejection (sender approval, API key, quota)
and failed queue jobs. Do not use a log mailer as a fake delivery fallback.
