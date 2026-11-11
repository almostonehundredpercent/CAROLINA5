# Booking Form Clarity and Loading Plan

> **Execution:** Inline implementation in this session; no separate approval gate requested.

**Goal:** Make booking dates easier to select, make guest contact fields clearly visible, and reduce avoidable page-load work without changing booking behavior.

**Architecture:** Keep the existing Blade booking flow and design tokens. Use a compact stay-type selector, larger date controls, a warm opaque guest-contact panel, and load the scroll-reveal helper only on surfaces that use it. Preserve the room photo and booking summary layout while using existing optimized image variants where available.

**Tech Stack:** Laravel Blade, CSS, JavaScript, Node test runner, Playwright, Vite.

**Spec:** User request in the current conversation.

## Global Constraints

- Preserve existing Carolina look, copy, form fields, booking validation, and reservation flow.
- Prioritize clear labels, contrast, and touch targets on mobile.
- Do not change site-wide layout or introduce external services/dependencies.
- Measure before/after page performance; call out host wake-up latency separately from controllable page work.

## Review Focus

- Narrow mobile widths must not clip dates or contact fields: verify 320px and 390px viewports.
- Date controls must remain comfortably tappable and calendars still open/close: retain existing date picker interaction tests.
- Contact panel must remain readable in light/dark themes and not render for signed-in users unnecessarily.
- Responsive image variants must be valid, smaller than originals, and work with graceful fallback.
- Reveal animation must continue on home/rooms pages while not being requested on unrelated pages.

---

### Task 1: Booking form layout and guest-contact visibility

**Files:**
- Modify: `resources/views/bookings/create.blade.php`
- Test: `tests/js/booking-create-responsive.test.mjs`

- [x] Add failing checks for larger date field height, a reduced stay-type selector footprint, an opaque guest-contact surface, and mobile no-overflow.
- [x] Run the focused test and confirm failure against current styles.
- [x] Add a guest-contact section wrapper and responsive surface styles; set date controls to a 54px touch target, reduce selector padding, and retain 2-column dates except at narrow widths.
- [x] Run booking responsive and date-picker tests; verify desktop/mobile layout constraints in Playwright at 320px, 390px, and desktop widths.

### Task 2: Reduce avoidable booking-page load work

**Files:**
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/bookings/create.blade.php` if a responsive room image source is applicable
- Test: `tests/js/performance-asset-delivery.test.mjs`

- [x] Add a failing assertion that the reveal helper is limited to home/room-card surfaces.
- [x] Run the focused test and confirm failure.
- [x] Limit the helper to pages that contain reveal targets; serve an optimized responsive booking summary image only if verified source variants exist.
- [x] Run performance asset checks and compare live request count, transfer, load timing, and TTFB with baseline.

### Task 3: Verification and deployment

**Files:**
- Review diff and checks; no additional source changes expected.

- [x] Run targeted Node tests, `npm run build`, PHP tests relevant to booking/image delivery, and `git diff --check`.
- [x] Check deployed booking flow at desktop and mobile and inspect browser errors; confirm 54px date fields, opaque guest-contact surface in both themes, no mobile overflow, working WebP delivery, and no browser exceptions.
- [x] Commit and push changes; confirm live booking pages return HTTP 200 and the mobile room image is served as a responsive WebP.

**Verification notes:** The focused booking/performance checks pass (12/12) and the Vite build passes. The full JavaScript suite has eight unrelated failures in existing homepage/rooms expectations and one carousel timeout; the focused tests for this change pass. One PHP feature assertion also still expects the Book now navigation to point at `/rooms`, while the current site points it at `/book`; room image viewer tests pass. The checked-out repository has no runnable local app configuration/database, so final rendered checks were done against the deployed site.
