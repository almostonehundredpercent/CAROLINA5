# Carolina Transient & Airbnb

A Laravel 12 booking website based on the supplied Carolina designs. Guests can browse real room records, search available dates, place reservations, and manage their bookings. Administrators can review and confirm bookings.

## Fastest start (Docker)

1. Copy `.env.example` to `.env` and set a unique `APP_KEY` for production.
2. Run `docker compose up --build`.
3. Open `http://localhost:8000`.

Docker creates the MySQL database named **`carolina5`**, migrates it, and inserts demo rooms and accounts.

## Local PHP/MySQL start

1. Create a MySQL database: `CREATE DATABASE carolina5;`
2. Copy `.env.example` to `.env`; set `DB_DATABASE=carolina5` and your MySQL credentials.
3. Run `composer install`, `php artisan key:generate`, `php artisan migrate --seed`, and `php artisan serve`.

## Demo accounts

- Admin: `admin@carolina.local` / `admin123456`
- Guest: `user@carolina.local` / `user123456`

Change or remove these seed credentials before public deployment.

## Production notes

Set `APP_ENV=production`, `APP_DEBUG=false`, a strong `APP_KEY`, and use unique database passwords. The included payments are a reservation preference only; connect a PCI-compliant payment provider before collecting cards or charging customers.
