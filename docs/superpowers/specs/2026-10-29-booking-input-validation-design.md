# Booking Input Validation Design

## Intent

Make booking form errors easy to understand and correct, especially for guest contact details and custom date/time selectors, while preserving the entered values and keeping Laravel validation authoritative.

## Goals and non-goals

- Show a focused error summary and field-level messages after invalid input or a server-side validation response.
- Validate guest name, email, and Philippine mobile number before submission with the same rules as the server.
- Cover required date/time and reservation terms controls, including custom hidden inputs.
- Do not change booking, pricing, or availability rules; do not extend the work to unrelated admin forms.

## Approaches

- Native browser-only validation: quick, but hidden custom selectors and server errors remain unclear.
- Server-only messages: reliable, but guests wait for a round trip to see simple mistakes.
- Chosen: a small booking-page validator plus an accessible server-rendered summary/inline errors. The browser improves guidance; server rules remain final.

## Constraints and calls

- Keep entered values through Laravel's existing `old()` flow.
- Use inline messages and focus the first invalid field; avoid browser alert dialogs.
- Normalize phone separators only for checking; let the existing server normalization handle the submitted value.
- Keep edits limited to the booking view, its page-scoped script, and tests.
