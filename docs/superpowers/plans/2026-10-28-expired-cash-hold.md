# Expired Cash Hold Recovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Guide staff through safe recovery of expired unpaid cash bookings without a generic payment error.

**Architecture:** Keep the existing booking and payment routes. The booking page derives an expired-cash-hold state from `Booking::hasExpiredHold()`. The confirmation route rechecks availability under its room lock; the payment route reports an expired hold as a validation error for the HTML form. No payment is recorded during restoration.

**Tech Stack:** Laravel 12, Blade, Pest, SQLite test database.

**Spec:** `docs/superpowers/specs/2026-10-28-expired-cash-hold-design.md`

## Global Constraints

- No new dependencies or database schema changes.
- Preserve PayMongo verification and refund behavior.
- Never record payment or change booking status when restoration conflicts with another booking or room closure.
- Keep current staff authorization and use a transaction with room locking.

## Review Focus

- A stale Mark paid form submitted after expiry must redirect with an error and create no payment ledger.
- A competing confirmed booking must prevent restoration and leave the expired booking pending.
- A cleaning/maintenance block or inactive room must prevent restoration.
- A successful restoration must not mark the cash payment paid.
- Pending PayMongo checkout must retain its provider-verification action rather than showing the cash recovery action.

---

### Task 1: Safe server-side recovery and payment rejection

**Files:**
- Modify: `app/Http/Controllers/AdminController.php`
- Test: `tests/Feature/BookingHoldTest.php`

**Interfaces:**
- Consumes: `Booking::hasExpiredHold()`, existing `admin.bookings.update` and `admin.bookings.payment` routes.
- Produces: confirmation that rechecks room readiness and an HTML validation redirect for expired paid attempts.

- [ ] Write failing feature tests for expired cash Mark paid redirect/no ledger, conflict restoration/no ledger, unavailable room restoration rejection, successful restoration/payment still pending.
- [ ] Run `php artisan test tests/Feature/BookingHoldTest.php` and observe expected failures.
- [ ] Add the smallest controller guards and validation messages under the existing room lock.
- [ ] Re-run that test file and confirm green.
- [ ] Commit the server-side change and tests.

### Task 2: Guided admin booking state and action

**Files:**
- Modify: `resources/views/admin/bookings.blade.php`
- Test: `tests/Feature/BookingHoldTest.php`

**Interfaces:**
- Consumes: `Booking::hasExpiredHold()` and Task 1 confirmation behavior.
- Produces: expired-hold badge, expiry time, recovery form, and no Mark paid on expired cash requests.

- [ ] Write a failing page test for expired cash display/action and active cash + PayMongo unaffected.
- [ ] Run the focused test and observe expected failure.
- [ ] Add the expired-hold branch and readable light/dark styling, retaining the existing top-of-page error notice.
- [ ] Run targeted, full PHP, browser tests, build, and diff checks.
- [ ] Commit, push `main` as the user's standing deployment preference, and verify Render reports the commit live.
