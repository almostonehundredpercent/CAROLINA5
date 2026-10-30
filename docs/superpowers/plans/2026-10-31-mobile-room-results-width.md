# Mobile Room Results Width Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give room results more usable width on phones without changing desktop spacing.

**Architecture:** Adjust existing mobile-only gutters in `rooms-premium.css`; verify rendered bounds with Playwright.

**Tech Stack:** CSS, Node.js, Playwright.

**Spec:** `docs/superpowers/specs/2026-10-31-mobile-room-results-width-design.md`

## Global Constraints

- Keep the existing 650px breakpoint.
- Preserve the 16px section inset and all desktop styles.
- Use 8px inner gutters for mobile filters, promotion, count, and room carousel; use 24px hero side padding to align all content edges.

## Review Focus

- At 320px, room cards and controls fit without horizontal overflow.
- At 390px, room cards gain the expected width.
- Visible previous/next pagination controls never cover room titles or booked-date controls.
- At desktop width, computed gutters stay at their existing 48px/desktop values.

---

### Task 1: Widen mobile room results

**Files:**
- Modify: `public/css/rooms-premium.css`
- Test: `tests/js/rooms-mobile-layout.test.mjs`

**Interfaces:**
- Consumes: Existing room-results markup and CSS classes.
- Produces: Wider mobile room cards and aligned search/promo content; unchanged desktop layout.

- [x] Add a Playwright regression test asserting mobile content gutters are <=24px, room card width is at least viewport minus 48px, and visible pagination arrows do not cover room titles or booked-date controls at 320px and 390px; assert desktop gutter values remain unchanged at 1440px.
- [x] Run the test and confirm it fails against current mobile spacing.
- [x] Reduce the listed margins/padding only in the existing <=650px CSS rule and move mobile pagination arrows beside the room-results status.
- [x] Run the focused test, full JavaScript test suite, and production build.
- [ ] Capture and inspect a live mobile-width screenshot; verify desktop computed gutters remain unchanged.
