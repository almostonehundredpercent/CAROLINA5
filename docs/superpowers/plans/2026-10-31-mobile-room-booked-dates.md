# Mobile Room Booked-Dates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the booked-dates accordion easier to use on phones without changing desktop styling or behavior.

**Architecture:** Reuse the existing room-card availability calendar. Add mobile-only exclusive-open behavior in its current page script and mobile-only CSS overrides. Keep the existing availability fetch and red unavailable-day styling.

**Tech Stack:** Laravel Blade, vanilla JavaScript, CSS, Node.js tests with Playwright.

**Spec:** `docs/superpowers/specs/2026-10-31-mobile-room-booked-dates-design.md`

## Global Constraints

- Mobile breakpoint: 650px and below.
- Mobile toggle minimum height: 56px.
- Desktop markup, styles, and independent calendar behavior remain unchanged.
- Unavailable dates remain red and the calendar stays inline in its room card.

## Review Focus

- Opening a second phone calendar closes the first; its disclosure state resets.
- Desktop still allows two room calendars open at once.
- At 320px, labels and disclosure control do not overflow.
- Dark mode remains readable when the mobile calendar is expanded.
- Calendar loading/errors and room-specific date markings remain unchanged.

---

### Task 1: Mobile room availability accordion

**Files:**
- Modify: `resources/views/rooms/index.blade.php`
- Modify: `public/css/rooms-premium.css`
- Test: `tests/js/rooms-booked-dates.test.mjs`

**Interfaces:**
- Consumes: Existing `.room-card-availability`, `.room-availability-toggle`, and `.room-availability-panel` markup and calendar loader.
- Produces: Mobile-only exclusive calendar opening and a 56px minimum mobile disclosure target.

- [x] **Step 1: Add regression test** `mobile calendars are exclusive while desktop calendars remain independent`; at 320px and 390px expect opening room 2 to close room 1; at 1440px expect both to remain open. Assert the mobile toggle is at least 56px high and desktop padding remains `12px 14px`.
- [x] **Step 2: Run `node --test tests/js/rooms-booked-dates.test.mjs` and confirm the new mobile assertion fails before implementation.** Expected failure observed: the original mobile toggle was below 56px.
- [x] **Step 3: Add only a mobile media-query rule in `public/css/rooms-premium.css` for the disclosure sizing; do not change desktop rules.**
- [x] **Step 4: Update the existing calendar toggle handler in `resources/views/rooms/index.blade.php` to close other open room calendars only when `matchMedia('(max-width: 650px)')` matches.**
- [x] **Step 5: Run `node --test tests/js/rooms-booked-dates.test.mjs`, the full JavaScript suite, and the production asset build; all passed (85 tests).**
- [x] **Step 6: Verify the room card at 320px, 390px, and 1440px; confirm no mobile horizontal overflow, dark-mode legibility, inline expansion, and unchanged desktop styles/behavior.**
