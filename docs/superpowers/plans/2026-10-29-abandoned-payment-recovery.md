# Abandoned Booking Payment Recovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let guests resume an unpaid booking during its configured hold and automatically expire abandoned booking and PayMongo checkout records without misclassifying a paid or processing payment.

**Architecture:** Reuse a verified active PayMongo Checkout Session and add an expiry service/Artisan command that tries provider expiry before cancelling a still-unpaid booking. Register the command in Laravel's minute scheduler and run the scheduler beside the existing web and mail workers. Expose the same receipt as the recovery path in guest surfaces and show an authoritative-server-time countdown.

**Tech Stack:** Laravel 12, PHP, Eloquent, Artisan scheduler, PayMongo v1 Checkout Sessions, Blade, Pest, Docker shell entrypoint.

**Spec:** `docs/superpowers/specs/2026-10-29-abandoned-payment-recovery-design.md`

## Global Constraints

- Keep `BOOKING_HOLD_MINUTES` configurable, with the existing default of 30 minutes.
- The persisted `hold_expires_at` is authoritative; client countdowns never alter payment or reservation state.
- The existing PayMongo webhook/reconciler remains the payment authority; browser redirects do not prove payment.
- Do not introduce new dependencies, change PayMongo API versions or credentials, or change cash/staff policies beyond expiring public unpaid holds.
- Keep deployment within the existing Render web container and current project checkout.

## Review Focus

- PayMongo reports a paid session while expiry is running: reconcile the exact matching payment and never mark it failed.
- PayMongo reports processing or cannot be reached: leave booking/session pending for a later retry; never create a replacement session.
- Several historical checkout rows exist: expire every pending provider session before cancelling the booking.
- A scheduler run races with a webhook: lock/recheck the booking and payment before any local final state change.
- A guest reaches recovery after expiry or without booking authorization: show the expired/released state and never expose a provider checkout URL.

---

### Task 1: Reuse and expire hosted checkout sessions

**Files:**
- Modify: `app/Services/PayMongoCheckout.php`
- Modify: `app/Http/Controllers/BookingController.php`
- Test: `tests/Feature/PayMongoCheckoutTest.php`

**Interfaces:**
- `PayMongoCheckout::retrieve(string $checkoutId): array` returns PayMongo's full checkout response.
- Add `PayMongoCheckout::expire(string $checkoutId): array`, posting to `/v1/checkout_sessions/{id}/expire` and returning the full provider response; failed requests throw without changing local payment state.
- `BookingController::startPayMongoCheckout(...)` reuses the latest locally pending GCash checkout when PayMongo says it remains active; it only creates a new session after the previous session is verified expired/failed.

- [x] **Step 1: Write the failing tests** named `starting payment reuses an active PayMongo checkout session`, `starting payment creates a new session only after the previous session expired`, and `starting payment does not create a duplicate when PayMongo cannot be reached`. Assert the returned URL, provider request path/count, and ledger states.
- [x] **Step 2: Run `php artisan test --compact tests/Feature/PayMongoCheckoutTest.php`** and confirm the new tests fail because the controller creates a new checkout without retrieving the prior one.
- [x] **Step 3: Implement provider expiry and active-session reuse**; treat unknown/provider-error states as pending and return the existing visible retry message rather than creating another session.
- [x] **Step 4: Re-run the targeted test file** and confirm all checkout tests pass.

### Task 2: Expire unpaid holds safely and run the scheduler

**Files:**
- Create: `app/Services/BookingHoldExpiryService.php`
- Create: `app/Console/Commands/ExpireUnpaidBookingHolds.php`
- Modify: `bootstrap/app.php`
- Modify: `docker/start.sh`
- Test: `tests/Feature/BookingHoldTest.php`

**Interfaces:**
- Add `BookingHoldExpiryService::expireExpiredHolds(): int`; it returns the number of bookings finalized in the run.
- Register `bookings:expire-holds` to call that service.
- Schedule `bookings:expire-holds` every minute with overlap protection; launch `php artisan schedule:work --no-interaction` in the existing Docker startup script and stop it with the other workers.
- For each expired pending booking, expire every pending `paymongo:` session first. A paid matching session goes through `PayMongoPaymentReconciler`; processing/provider errors remain pending. Only after all sessions are expired/failed (or none exist) may the still-unpaid booking be cancelled with `Unpaid reservation hold expired.`.

- [x] **Step 1: Write failing behavior tests** named `scheduled hold expiry cancels an unpaid request with no checkout`, `minute scheduler includes automatic unpaid hold expiry`, `scheduled hold expiry closes PayMongo before cancelling`, `scheduled hold expiry reconciles a paid provider session`, and `scheduled hold expiry leaves processing or unreachable sessions pending`. Assert actual booking/payment rows, blocking inventory, and provider calls.
- [x] **Step 2: Run `php artisan test --compact tests/Feature/BookingHoldTest.php`** and confirm the new expiry-command cases fail because no such command/service is registered.
- [x] **Step 3: Implement the expiry service and command** with per-booking/payment transactions and locked state rechecks; keep provider calls outside database locks and retry on the next scheduled run after transient failures.
- [x] **Step 4: Register and launch the minute scheduler** in Laravel and `docker/start.sh`, preserving existing web/queue process shutdown behavior.
- [x] **Step 5: Re-run the hold test file** and confirm existing hold-boundary, restore, and late-payment protections still pass.

### Task 3: Guest recovery, clear expiry messaging, and staff restoration

**Files:**
- Modify: `app/Models/Booking.php`
- Modify: `app/Http/Controllers/AdminController.php`
- Modify: `app/Http/Controllers/BookingController.php`
- Modify: `resources/views/bookings/receipt.blade.php`
- Modify: `resources/views/bookings/index.blade.php`
- Modify: `resources/views/bookings/lookup.blade.php`
- Modify: `resources/views/bookings/lookup-result.blade.php`
- Modify: `resources/views/emails/booking-confirmation.blade.php`
- Test: `tests/Feature/BookingHoldTest.php`
- Test: `tests/Feature/GuestBookingsPageTest.php`

**Interfaces:**
- Add `Booking::isExpiredHoldRecord(): bool`, true only for a cancelled booking whose cancellation reason exactly identifies automatic unpaid-hold expiry; retain `hasExpiredHold()` for an unprocessed pending hold.
- Active unpaid bookings expose the authorized receipt/resume path in My Bookings, lookup results, and email/reference lookup; expired bookings show the room-release message and no payment action.
- The receipt countdown uses rendered server time plus `holdDeadline()` and reloads the receipt at zero so the server determines the resulting state.
- Staff can use the existing availability-check-and-restore action for a system-expired hold only, and only after the existing locked availability checks pass; manually cancelled bookings remain non-restorable through this path.

- [x] **Step 1: Write failing feature tests** named `receipt shows a server-authoritative live countdown for an active hold`, `booking confirmation email explains the hold and links to secure booking lookup`, `signed-in guest can resume payment for an active unpaid booking`, `email and reference lookup can resume an unpaid booking`, `expired hold recovery shows the released room state without payment controls`, and `staff can restore a system-expired hold after the availability check`. Assert authorization, rendered actions, and room conflicts.
- [x] **Step 2: Run the two targeted test files** and confirm the new recovery assertions fail because those links/statuses are not present and system-expired cancellation is not recognized.
- [x] **Step 3: Implement the record predicate, safe staff restore rule, receipt countdown, guest links, and accurate confirmation email copy**. Prefill only the public reference field from a query string; still require email submission and keep payment URLs behind booking authorization.
- [x] **Step 4: Re-run both targeted test files** and confirm recovery, authorization, and restore behavior passes.

### Task 4: Full verification, independent review, and deployment

**Files:**
- Review all changed files and generated tests.

- [x] **Step 1: Run `php artisan test --compact`** and confirm the full PHP suite passes.
- [x] **Step 2: Run `node --test --test-concurrency=1 tests/js/*.test.mjs` and `git diff --check`**; confirm both complete without errors.
- [x] **Step 3: Create a local review commit and request a read-only code review** of the committed diff, address Critical/Important findings with a failing test first, then rerun the affected and full suites.
- [x] **Step 4: Push to `origin main`** without force-pushing; integrate any new upstream commit safely before push.
- [x] **Step 5: Confirm the push result** and report the deployment trigger accurately; do not claim Render deployment completed unless its status is verified.
