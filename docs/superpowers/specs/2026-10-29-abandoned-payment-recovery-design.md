# Abandoned Booking Payment Recovery

## Intent

Help guests finish a booking if they leave before paying, while ensuring unpaid requests and provider checkout sessions cannot hold inventory indefinitely.

## Current behavior

- New unpaid bookings receive a configurable 30-minute hold. Availability queries stop treating the room as blocked when that deadline passes.
- Hold cleanup exists as `Booking::releaseExpiredHolds()` but is not scheduled by the deployed service.
- PayMongo Checkout Sessions do not expire automatically. A session can remain payable after Carolina's room hold ends.
- The receipt contains the only payment action. Signed-in guests' My Bookings and guest booking lookup do not offer a clear route back to payment, and the confirmation email incorrectly says that online payment is not collected.
- PayMongo payment webhooks already reconcile payment outcomes and protect against restoring a conflicting reservation.

## Goals

- Make the 30-minute payment deadline visible and easy to understand.
- Let guests resume the same active PayMongo Checkout Session from the receipt, My Bookings, guest booking lookup, or the booking email.
- Automatically close abandoned Checkout Sessions and expire the corresponding unpaid request.
- Keep the room available after the hold deadline, whether or not the guest returns to Carolina.
- Preserve webhook-authoritative payment confirmation and never treat a browser redirect as payment proof.
- Handle PayMongo's paid/processing responses safely: reconcile a paid session; defer local expiry while a payment is still processing; retry when the provider cannot be reached.

## Non-goals

- Prevent guests from closing the browser or force them to pay.
- Change the hold duration (still controlled by `BOOKING_HOLD_MINUTES`, default 30).
- Change cash bookings or staff confirmation policies beyond expiring unpaid public holds.
- Switch PayMongo API versions or change test/live credentials.

## Approaches considered

1. **Scheduled expiry with resumable checkout (selected).** Keep the existing temporary hold, show its countdown, provide recovery links, and run a minute-based Laravel task that expires abandoned PayMongo sessions before marking bookings expired. This fits the current hold model and prevents an old hosted checkout URL from remaining usable indefinitely.
2. **Only expire inventory logically.** Availability already ignores expired holds, so no scheduled booking update is needed. Rejected because the provider Checkout Session remains active indefinitely and the guest/admin records stay confusingly pending.
3. **Require payment before creating any booking.** Avoids unpaid booking records, but a payment could arrive after inventory changed and require a separate reconciliation flow; it is not a fit for the existing request-and-hold design.

## Design decisions

- The booking's stored `hold_expires_at` is the deadline source of truth; client countdowns are display-only.
- The Laravel scheduler runs every minute in the existing Render web container.
- A cleanup run first attempts to expire each active PayMongo Checkout Session associated with an expired unpaid hold. Successful expiry is recorded locally. If PayMongo reports a paid session, use the existing reconciler. If it reports processing or cannot be reached, leave the payment pending and retry; do not create another session or claim the reservation is paid.
- After all checkout sessions are expired or no session exists, mark the still-unpaid booking cancelled with a distinct hold-expiry reason. Availability already releases at the exact timestamp, independent of scheduler timing.
- Restarting payment reuses an active existing Checkout Session. A new session may be created only after the previous one is confirmed expired or failed.
- Guests can reach the receipt through My Bookings or email/reference booking lookup. The email continues to require the guest's email plus reference to retrieve the booking; it is not an unrestricted secret payment link.
- The receipt shows a countdown using server-rendered deadline and server time. At zero, it asks the server to refresh the receipt state instead of trusting browser time to decide payment status.
- A payment confirmed after inventory is released remains subject to the existing reconciliation policy; it must not silently reclaim a room that another guest booked.

## Acceptance criteria

- An unpaid receipt displays the actual deadline and a live remaining-time indicator.
- An expired hold cannot start or resume checkout, and the receipt/lookup shows that the room was released.
- Signed-in guests and email/reference lookup can resume an active checkout; repeated resume clicks reuse the same provider session.
- Expired unpaid holds are processed by the running scheduler, including closing eligible PayMongo sessions.
- Paid/processing/network-error provider outcomes do not get incorrectly marked failed or cancelled as if final; paid outcomes still go through webhook/reconciliation checks.
- Existing inventory-expiry and late-payment protections remain green.
- Emails accurately explain the payment deadline and where to resume; test mode is clearly identified as test-only.
