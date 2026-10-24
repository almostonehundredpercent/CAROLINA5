# Simple Stay Time Adjustments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add simple, availability-safe early check-in, early check-out, and +1/+2-hour checkout actions with verified incremental payments.

**Architecture:** A `BookingTimeAdjustment` record captures each requested schedule change, a temporary `RoomBlock` protects its added interval, and the payment ledger/PayMongo reconciler applies the change only after verified payment. Existing early checkout remains an immediate check-out and housekeeping action.

**Tech Stack:** Laravel 12, PHP 8.2, Blade, SQLite/Pest tests, PayMongo hosted GCash checkout and webhook.

**Spec:** `docs/superpowers/specs/2026-10-24-simple-stay-time-adjustments-design.md`

## Global Constraints

- Use the existing hourly-rate formula `price_per_night / (rental_hours ?: 24)` and round the final incremental amount to two decimal places.
- Do not modify booking times before payment is verified, except for cash adjustments where the staff explicitly confirms cash was received.
- Do not automatically refund early checkouts.
- Keep booking overlap validation, room-block validation, activity logging, and payment verification server-side.
- Keep admin wording short and nontechnical; no separate request inbox.

## Review Focus

- A competing booking or room block in the added interval must reject the time change without changing the booking or creating a payable adjustment.
- A cancelled, failed, expired, or late PayMongo checkout must not lengthen the booking; any verified late payment must be surfaced for staff resolution.
- Repeated webhook/return callbacks must apply a time adjustment and increment the booking total only once.
- Paying an extension must not overwrite the original booking’s payment state or base payment method.
- An early checkout must start cleaning without changing the scheduled booking total or issuing a refund.

---

### Task 1: Model a pending time adjustment and protect its interval

**Files:**
- Create: `database/migrations/<timestamp>_create_booking_time_adjustments_table.php`
- Create: `app/Models/BookingTimeAdjustment.php`
- Modify: `app/Models/RoomBlock.php`
- Modify: `app/Models/Booking.php`
- Test: `tests/Feature/BookingTimeAdjustmentTest.php`

**Interfaces:**
- Produces `BookingTimeAdjustment` status/type fields, payment and room-block relationships, and hold-expiry behavior for later tasks.
- Produces `BookingTimeAdjustment::quoteAmount(Booking $booking, int $hours): float` or an equivalent testable service interface using the exact existing rate formula.

- [ ] **Step 1: Write failing tests** for quote amounts, blocking overlap, and an expired adjustment hold no longer blocking a new booking.
- [ ] **Step 2: Run** `php artisan test tests/Feature/BookingTimeAdjustmentTest.php`; expect failures for the missing model/fields/expiry behavior.
- [ ] **Step 3: Implement** the migration and model relationships, add an expiry field to temporary room blocks, and make expired holds stop blocking availability.
- [ ] **Step 4: Run the focused test**; expect all new adjustment-model tests to pass.

### Task 2: Create and apply adjustments through verified incremental payments

**Files:**
- Modify: `app/Services/PayMongoCheckout.php`
- Modify: `app/Services/PayMongoPaymentReconciler.php`
- Modify: `app/Models/Payment.php`
- Modify: `app/Http/Controllers/PayMongoWebhookController.php`
- Modify: `app/Http/Controllers/BookingController.php`
- Create or modify: `app/Services/BookingTimeAdjustmentService.php`
- Test: `tests/Feature/BookingTimeAdjustmentTest.php`, `tests/Feature/PayMongoCheckoutTest.php`, `tests/Feature/ExampleTest.php`

**Interfaces:**
- Consumes the adjustment/model outputs from Task 1.
- Produces one shared workflow for staff early-check-in/late-checkout and the existing guest extension, including atomic availability checks, PayMongo checkout creation, cash-received recording for staff, and idempotent application after payment verification.

- [ ] **Step 1: Write failing tests** for successful early-check-in and late-checkout adjustment payments, failed/cancelled checkout, expired holds, conflicts, duplicate webhooks, payment amounts, and the guest extension not applying before verified payment.
- [ ] **Step 2: Run the focused tests**; expect failures because incremental adjustments and their reconciliation are not yet implemented.
- [ ] **Step 3: Implement** shared quote/hold/apply behavior, incremental PayMongo checkout, matching-payment verification against the `Payment` amount, late-payment resolution state, and the guest extension conversion.
- [ ] **Step 4: Run the focused tests**; expect all adjustment and existing PayMongo behavior tests to pass.

### Task 3: Add plain booking-card actions for staff

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/AdminController.php`
- Modify: `resources/views/admin/bookings.blade.php`
- Modify: `resources/views/admin/partials/confirmation-dialog.blade.php` only if the existing dialog needs a new message hook.
- Modify: `public/css/admin.css` only for compact responsive time-action controls.
- Test: `tests/Feature/AdminDayWorkflowTest.php`, `tests/Feature/BookingTimeAdjustmentTest.php`

**Interfaces:**
- Consumes Task 2's quote/create/verify/apply service.
- Produces booking-card actions labeled **Check in early**, **Check out now**, and **Add checkout time** with +1/+2-hour choices, clear prices, GCash/cash choices, and plain-language conflict/status messages.

- [ ] **Step 1: Write failing tests** for action visibility, early check-in restrictions, in-house extension options, PayMongo redirects, cash-received completion, and check-out housekeeping behavior.
- [ ] **Step 2: Run the focused tests**; expect failures because the new routes and controls are not present.
- [ ] **Step 3: Implement** authorized routes, availability and status rechecks, confirmation copy, and compact controls on the existing bookings page.
- [ ] **Step 4: Run the focused tests**; expect all new staff-action tests to pass.

### Task 4: Final verification and deploy

**Files:**
- Test: complete project suite.

- [ ] Run `php artisan test` and report both new results and any pre-existing baseline failures.
- [ ] Run `git diff --check` and review the final diff for accidental changes.

- [ ] Commit the completed feature and push to `main` so Render auto-deploys; verify the deployed application health and the updated admin assets.
