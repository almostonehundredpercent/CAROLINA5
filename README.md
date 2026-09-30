# Carolina Transient House Operations System

Carolina is a Laravel application for the guest booking, staff operations, and management reporting needs of Carolina Transient House in Tabaco City, Albay. It is deliberately sized for one property: it is not a marketplace or a payment gateway.

## What it includes

- Public room catalogue, availability checks, date and hourly stays, guest/account reservations, printable booking receipt, and booking lookup.
- Server-side overlap checks with transactions and row locking when a reservation is confirmed or a walk-in is created.
- Staff dashboard, reservation review, check-in/check-out, scheduled cleaning and maintenance blocks, walk-ins, role separation, reports, review moderation, payment-record status, and room catalog management.
- Decision-support indicators based only on saved records: room demand, cancellations, room availability, recorded payments, and repeated maintenance blocks.
- Audit records for booking, payment, room-operation, room-catalog, and staff-role changes.

## Roles

- **Super administrator:** all staff tools, plus granting or removing administrator access.
- **Administrator:** daily staff access, including rooms, catalog changes, and non-admin staff role changes.
- **Front desk:** booking review, check-in/out, walk-ins, payments, and review moderation.
- **Housekeeping:** cleaning and maintenance blocks only.
- **Viewer:** read-only dashboard and room-board access.
- **Guest:** public pages and their own account bookings.

Routes are protected on the server; hiding a navigation item is never relied upon for authorization.

## Staff sign-in and MFA

The existing Laravel web guard handles passwords and sessions. After password sign-in, **every staff role** must enroll or complete a time-based authenticator challenge before any `/admin/*` route is available. An authenticator app can scan the setup QR code; eight one-time recovery codes are shown only once. The TOTP secret is encrypted in the users table, and recovery codes are hashed and encrypted. Losing both the authenticator and recovery codes requires a verified identity check by an operator with server access.

Existing staff are prompted to enroll at their next sign-in. This is a one-time rollout step; warn them to save recovery codes before relying on the admin area. A regular administrator can manage non-admin staff roles, but only a super administrator can change administrator access. Existing administrators retain operational permissions without automatic elevation to super administrator.

For a controlled initial promotion, run `php artisan staff:promote-super-admin person@example.com` for an **existing administrator**. If identity and ownership are verified and a staff member loses both MFA methods, run `php artisan staff:reset-mfa person@example.com`; their active staff access is invalidated and they must enroll again. Both actions are server-side commands and are audited. Never expose these commands as web routes or share database credentials to perform them.

Login is limited per account/IP, per account across IPs, and per IP. The MFA challenge is throttled for the authenticated user. Password login and MFA completion regenerate the session; logout invalidates it. Production cookies are Secure, HttpOnly, and SameSite=Lax through the existing configuration. CSRF is still enabled on web forms.

## Local setup

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Set database and mail values in `.env`. Never commit `.env`, database dumps containing real guests, passwords, or API credentials.

## Tests

```powershell
php artisan test
```

The suite covers public pages, authentication/MFA, booking timing, overlap protection, staff workflow, check-in/out, maintenance conflicts, and review moderation. Run tests against the test database only; never run `migrate:fresh` against production.

## Payments and email

This project records staff-verified payment status (`pending`, `paid`, `failed`, or `refunded`) and cash/GCash method labels. It does **not** process a live online payment; do not mark a payment paid unless it has actually been verified.

Booking emails use Laravel mail configuration. Production email needs a real provider and valid sender domain. If a queue worker is added later, email can be moved to queued jobs; this Render setup does not claim a worker exists.

## Render deployment checklist

1. Set `APP_ENV=production`, `APP_DEBUG=false`, a private `APP_KEY`, production `APP_URL`, database credentials, and mail credentials in Render environment variables.
2. Deploy from `main`; run migrations once as a controlled deploy step.
3. Confirm HTTPS, `/health`, `/sitemap.xml`, and the booking flow after deployment.
4. The startup script creates the public storage link for uploaded room images.
5. Use the Render database backup/export tools or the database provider's scheduled backup facility. Store backups in an access-controlled location and test a restore into a non-production database before relying on it.

## Known operational limits

- Phone number, email address, actual cancellation policy, GCash merchant/API credentials, and final room facts must be supplied by Carolina management. The application intentionally does not invent them.
- Uploaded images are stored on the configured public disk. For durable production storage, configure a persistent disk or object storage before relying on staff uploads across instance replacement.
- Payment refunds are recorded as a status only; no refund is sent automatically because no live payment provider is integrated.
