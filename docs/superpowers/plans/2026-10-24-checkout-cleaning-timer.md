# Checkout Cleaning Timer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Start a 20-minute room cleaning block when staff checks out a guest and show a live countdown in admin room views.

**Architecture:** Continue using `RoomBlock` as the authoritative operational interval. The checkout action creates a block ending 20 minutes after checkout; both room detail layouts render the remaining seconds and a small shared JavaScript timer updates the display each second.

**Tech Stack:** Laravel, Blade, PHP feature tests, JavaScript, Playwright/node:test.

**Spec:** `docs/superpowers/specs/2026-10-24-checkout-cleaning-timer-design.md`

## Global Constraints

- Checkout-created cleaning blocks last exactly 20 minutes from the staff checkout timestamp.
- Existing room blocks remain the source of truth for inventory blocking and expiry; add no migration or second timer record.
- Manually scheduled cleaning and maintenance behavior stays unchanged.
- Show the countdown only for active cleaning blocks, on both the admin room details and housekeeping room board.
- The countdown remains legible, accessible, and consistent with the admin UI in light and dark themes.

## Review Focus

- Future cleaning blocks and active maintenance must not be presented as an active checkout cleaning timer; cover in the room-view feature test.
- A timer already at zero must not display a negative duration; cover in the countdown browser test.
- Checkout retries must not create duplicate active cleaning blocks; cover in the checkout feature test if route behavior permits an already-checked-out retry.

---

### Task 1: Add checkout cleaning countdown

**Files:**
- Modify: `app/Http/Controllers/AdminController.php`
- Modify: `resources/views/admin/partials/room-detail-card.blade.php`
- Modify: `resources/views/admin/room-board.blade.php`
- Create: `resources/views/admin/partials/cleaning-countdown.blade.php`
- Modify: `public/css/admin.css`
- Create: `public/js/cleaning-countdown.js`
- Test: `tests/Feature/AdminDayWorkflowTest.php`
- Test: `tests/js/cleaning-countdown.test.mjs`

**Interfaces:**
- Consumes: Existing `RoomBlock` checkout creation, `room->display_block`, and shared admin stylesheet.
- Produces: Active cleaning timer markup with `data-cleaning-countdown` and `data-remaining-seconds`; JS updates its `data-countdown-value` time element and completion label.

- [x] **Step 1: Write the failing tests.** Add feature coverage that checks checkout creates one cleaning block from checkout time through exactly 20 minutes, a repeated checkout request cannot create another block, both room layouts render an active countdown from server-derived seconds, and future cleaning/maintenance blocks remain schedule text rather than countdowns. Add a Playwright test that checks countdown decrement and zero-state formatting.
- [x] **Step 2: Run the tests to verify they fail.** Run `php artisan test tests/Feature/AdminDayWorkflowTest.php --filter='checkout starts a twenty-minute cleaning timer|room views show a live cleaning countdown|scheduled room work does not show a live cleaning countdown'` and `node --test tests/js/cleaning-countdown.test.mjs`. Expected: failure because checkout still creates a 60-minute block and no countdown markup or timer script exists.
- [x] **Step 3: Implement the 20-minute checkout block and clear success message.** In `AdminController::updateBooking`, end the block at `$checkedOutAt->copy()->addMinutes(20)`, update the note/activity copy, and return a success message that says the 20-minute cleaning timer started. Keep the existing checkout guard and `RoomBlock` transaction.
- [x] **Step 4: Add the countdown to both staff room views.** Render current cleaning blocks with server-derived seconds and `MM:SS` through the shared `resources/views/admin/partials/cleaning-countdown.blade.php`; keep existing range text for upcoming blocks and maintenance. Load `public/js/cleaning-countdown.js` on both views.
- [x] **Step 5: Style the countdown.** Add compact warm-accent styling using existing admin theme variables, with tabular digits, adequate contrast in light/dark modes, and mobile-friendly wrapping.
- [x] **Step 6: Verify tests.** Run `php artisan test tests/Feature/AdminDayWorkflowTest.php tests/Feature/RoomAvailabilityTest.php`, `node --test tests/js/*.test.mjs`, `vendor/bin/pint --test app/Http/Controllers/AdminController.php tests/Feature/AdminDayWorkflowTest.php`, and `git diff --check`. Expected: all pass.
- [x] **Step 7: Commit the feature.** Commit only the plan/spec and implementation/test files with message `Add automatic checkout cleaning countdown`.
