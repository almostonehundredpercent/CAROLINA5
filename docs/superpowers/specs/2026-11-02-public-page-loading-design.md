# Public Page Loading Design

## Intent

Reduce visible layout movement on the public Rooms page without risking stale room availability, prices, promotions, or payment state. The current production mobile audit measured a 0.167 CLS on `/rooms`; Lighthouse attributes the largest shift to `.rooms-results`. The Rooms template currently emits its page styles after `</main>`, so the browser initially lays out the content before those styles arrive.

## Goals

- Load Rooms page CSS before its content is painted.
- Keep room availability and all booking/payment responses fresh (`no-store`).
- Preserve existing public layout and booking behavior.
- Rerun tests and mobile Lighthouse after deployment to verify the effect.

## Non-goals

- Do not cache full HTML, availability, booking, price, promotion, or payment responses.
- Do not add a load balancer, database pool, or provider CDN rules in application code.
- Do not add JavaScript chunks or debounce handlers that do not issue expensive work.

## Approaches

- Cache public pages or API payloads: rejected because availability and mutable offer/pricing data must not go stale.
- Move only the Rooms page styles into the shared head stack: selected because Lighthouse identifies late page styling as the main layout-shift source, and this is a small, reversible template change.
- Rework CSS delivery into a new build pipeline or change hosting: deferred because that broadens deployment and infrastructure risk beyond the measured issue.

## Constraints and choices

- Only CSS loading order changes; no data freshness policy changes.
- The existing shared `@stack('styles')` is the page-level insertion point.
- Keep the established visual cascade by leaving global and navigation styles in their current order.
- Preserve `@stack('late-styles')` because the room-detail and guest-arrival views still depend on it; only the Rooms listing moves to the head stack.
- Use the existing PHPUnit and Playwright suites; the browser plugin is unavailable, so Playwright is the rendered-test fallback.

## Acceptance

- Feature test confirms both Rooms page stylesheets occur in the document head before `<body>`.
- `php artisan test`, `node --test tests/js/*.test.mjs`, and `npm run build` pass.
- Production mobile Lighthouse is rerun on `/rooms`; report the measured score, LCP, CLS, and TTFB honestly, including any lack of improvement.
- Production availability stays uncached.
