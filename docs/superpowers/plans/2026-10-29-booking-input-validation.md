# Booking Input Validation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Give guests clear inline booking-form validation before and after submission.

**Architecture:** Laravel continues to validate and retain submitted values. The booking page adds an accessible server-error summary and loads a small page-scoped script that validates required/custom controls and guest contact formats before allowing submission.

**Tech Stack:** Laravel Blade, vanilla JavaScript, Pest, Node test runner, Playwright.

**Spec:** `docs/superpowers/specs/2026-10-29-booking-input-validation-design.md`

## Global Constraints

- Do not weaken or replace server-side validation.
- Keep the validation enhancement scoped to the guest booking form.
- Preserve submitted values on a validation redirect.

## Review Focus

- Invalid/missing guest name, email, and phone should show a specific correction inline.
- Hidden date/time choices and reservation terms should not bypass required checks.
- Valid phone separators should be tolerated consistently with server normalization.
- Correcting a field should remove its error and allow submission when all fields are valid.
- Server-side errors should be visible after redirect even if JavaScript is unavailable.

---

### Task 1: Server-rendered booking validation feedback

**Files:**
- Modify: `resources/views/bookings/create.blade.php`
- Test: `tests/Feature/GuestBookingFormTest.php`

**Interfaces:** Uses Laravel's flashed error bag and `old()` values.

- [x] Add a failing feature test that posts invalid guest details, follows the validation redirect, and expects a visible error summary, inline phone error, and preserved name.
- [x] Run the focused feature test and confirm it fails because the errors are not rendered.
- [x] Add the summary and inline error markup/ARIA hooks without changing validation rules.
- [x] Run the focused feature test and confirm it passes.

### Task 2: Client-side inline validation

**Files:**
- Create: `public/js/booking-validation.js`
- Modify: `resources/views/bookings/create.blade.php`
- Test: `tests/js/booking-field-validation.test.mjs`

**Interfaces:** A page-scoped script attaches to `.booking-form form`, validates required form fields and guest contact formats, renders inline errors and a summary, and prevents submission until valid.

- [x] Add a failing Playwright test against the actual page-scoped validator for invalid phone, required selections, and recovery after correction.
- [x] Run it and confirm invalid input still submits or lacks inline feedback.
- [x] Implement only the failing behavior and connect the script to the booking page.
- [x] Run the focused test and confirm invalid input is blocked and corrected input clears its message.

### Task 3: Regression verification and delivery

**Files:**
- Verify: `tests/Feature/GuestBookingFormTest.php`, `tests/js/booking-field-validation.test.mjs`, full existing test suites.

- [x] Run PHP and JavaScript test suites; record any unrelated existing failures. PHP: 175 passed. JavaScript: 78 passed with `--test-concurrency=1`; the default parallel runner intermittently fails the unrelated room-gallery navigation timing test (which passes in isolation).
- [ ] Run `git diff --check` and commit the verified change.
- [ ] Push `main` to trigger Render auto-deploy.
