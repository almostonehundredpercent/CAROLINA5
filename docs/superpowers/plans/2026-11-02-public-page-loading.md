# Public Page Loading Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reduce Rooms page layout shift by loading its existing page styles before content, without changing live booking-data caching.

**Architecture:** Use the existing Blade head style stack instead of the late-style stack for the Rooms view. Add a focused regression test that checks emitted document order, then run full tests/build and compare mobile Lighthouse results.

**Tech Stack:** Laravel Blade, PHPUnit, Playwright (Node test suite), Lighthouse.

**Spec:** `docs/superpowers/specs/2026-11-02-public-page-loading-design.md`

## Global Constraints

- Dynamic availability, bookings, payments, prices, and promotion responses remain uncached.
- Preserve existing CSS order and visual behavior.
- No infrastructure/CDN/load-balancer/database-pool changes.

## Review Focus

- Rooms CSS must be in `<head>` before the first paint; regression test inspects rendered HTML order.
- Availability must still be `no-store`; existing feature tests and route behavior remain unchanged.
- Mobile and desktop layouts must remain usable; browser tests cover viewports and overflow.
- Styles must retain their current cascade; `navigation-glass.css` remains after page styles.
- The deployment must serve new HTML and CSS and Lighthouse metrics must be reported without implying unsupported gains.

---

### Task 1: Load Rooms styles before its content

**Files:**
- Modify: `resources/views/rooms/index.blade.php`
- Keep: `resources/views/layouts/app.blade.php` late-style stack because room-detail and guest-arrival views still use it
- Test: `tests/Feature/HomePerformanceTest.php`

**Interfaces:**
- Uses the existing Blade `@push('styles')` and `@stack('styles')`.
- Keeps the current stylesheet order: `rooms-premium.css`, then `promo-campaign.css`, then shared navigation CSS.

- [ ] Add a feature test asserting `rooms-premium.css` and `promo-campaign.css` occur before `<body>` in the Rooms response.
- [ ] Run the focused test and confirm it fails because those styles are currently emitted after the main content.
- [ ] Change the Rooms view from `@push('late-styles')` to `@push('styles')`; preserve the shared late-style stack because room-detail and guest-arrival views still use it.
- [ ] Run the focused test and verify it passes.
- [ ] Run `php artisan test`, `node --test tests/js/*.test.mjs`, and `npm run build`.
- [ ] Run Playwright on live `/rooms` at 390px and 1440px, then mobile Lighthouse on `/rooms`; record status, errors, overflow, CLS, LCP, and TTFB.
- [ ] Confirm live availability responses remain `Cache-Control: private, no-store`.
- [ ] Commit and push to `main`; verify the deployed route reflects the new stylesheet ordering.
