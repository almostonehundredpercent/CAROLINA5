# Check-in Date Dropdown Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the check-in calendar behave and look like an anchored dropdown on desktop and mobile.

**Architecture:** Wrap the check-in trigger in a positioned container and render the existing calendar as its absolute popover. Retain the existing calendar JavaScript and add only the open-state class needed for stacking.

**Tech Stack:** Blade, CSS, vanilla JavaScript, Node test runner, Playwright.

**Spec:** `docs/superpowers/specs/2026-10-31-check-in-date-dropdown.md`

## Global Constraints

- Keep date selection and booking rules unchanged.
- Do not change the desktop search layout except for the requested dropdown behavior.
- Keep the mobile dropdown within the viewport and on top of page content.
- Preserve existing keyboard, Escape, outside-click, and filter-menu behavior.

## Review Focus

- Narrow phone viewport: dropdown must not create horizontal scrolling.
- Stacking: dropdown remains clickable when it overlaps lower controls or room cards.
- Selection and dismissal: date selection closes the dropdown; Escape/outside click still close it.

---

### Task 1: Anchor the check-in calendar as a dropdown

**Files:**
- Modify: `resources/views/rooms/index.blade.php`
- Modify: `public/css/rooms-premium.css`
- Test: `tests/js/rooms-search-layout.test.mjs`

- [ ] **Step 1: Write the failing browser tests** by replacing `opening the calendar expands the search panel instead of covering room results` with `check-in calendar opens as an anchored dropdown without expanding the search panel`, and adding `check-in date selection updates the filter and dismisses the dropdown`; assert date alignment, unchanged layout, viewport fit at 320px, overlay stacking, selection, and close behavior.
- [ ] **Step 2: Run the focused test** with `node --test --test-name-pattern="check-in calendar opens as an anchored dropdown" tests/js/rooms-search-layout.test.mjs`; expected: FAIL because the current calendar is in normal grid flow below all filters.
- [ ] **Step 3: Implement the wrapper and dropdown styles** in the Blade view and room stylesheet; update close/open state handling only as needed to preserve accessibility and stacking.
- [ ] **Step 4: Run the focused tests and the room search/date tests** with `node --test tests/js/rooms-search-layout.test.mjs tests/js/rooms-booked-dates.test.mjs`; expected: all pass, including date selection/dismissal behavior.
- [ ] **Step 5: Run the full JS test suite and production build** with `node --test tests/js/*.test.mjs` and `npm run build`; expected: all tests pass and Vite exits successfully.
- [ ] **Step 6: Commit** the verified change.
