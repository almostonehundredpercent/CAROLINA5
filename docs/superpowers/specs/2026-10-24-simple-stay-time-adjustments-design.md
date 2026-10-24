# Simple Stay Time Adjustments — Design

## Intent

Give front-desk staff a few clear actions on a booking to check a guest in early, check them out early, or add one or two hours at checkout. Keep availability and any extra charge correct without making staff manage technical payment states.

## Goals

- Put time actions on the existing booking card; do not create a separate request inbox.
- Check-in early and late-checkout extensions are permitted only when the added time is available.
- Calculate hourly charges with the existing room rate: `price_per_night / (rental_hours ?: 24)`, multiplied by the extra whole hours and rounded to cents.
- Do not change the booking schedule until an extra GCash payment is verified by PayMongo, or staff records that cash was received.
- Early checkout records the actual departure, starts the existing cleaning block, and does not automatically refund the stay.
- Keep expired or cancelled payment attempts from extending the stay. Preserve a clear staff follow-up state if PayMongo reports a late payment after the time slot is no longer held.

## Non-goals

- Automatically refunding early departures.
- Changing the room’s pricing configuration or the guest’s original room rate.
- A guest-facing approval queue or a broad reservation-editing screen.
- Automatic PayMongo refunds.

## Approaches

- A separate adjustment inbox would give staff more workflow states to manage; rejected because the client needs a simple interface.
- Directly editing booked times would be compact, but could give away unpaid hours or create overlaps; rejected.
- Use a guided action on the existing booking card, backed by a temporary room-time hold and a payment-linked adjustment record; chosen because the system can enforce availability and payment rules behind a small set of familiar actions.

## Interaction and rules

- On the scheduled arrival date, staff can choose **Check in early** when the guest has not checked in and the scheduled start is still in the future. The system quotes whole early hours, checks that interval, and offers GCash through PayMongo or **Cash received**. The guest is checked in only after payment is settled.
- For an in-house guest, staff can choose **Add checkout time** and select **+1 hour** or **+2 hours**. The system checks the added interval against bookings and room blocks, quotes the fee, and then uses GCash or cash received. The scheduled checkout changes only when the additional payment is settled.
- **Check out now** records the actual departure and starts the existing one-hour cleaning block. Leaving early does not change the amount paid or promise a refund.
- Pending GCash adjustments temporarily hold only the added time. The hold uses the existing booking-hold duration. If the hold expires or the guest cancels checkout, the schedule remains unchanged. A subsequently confirmed payment that cannot safely be applied is recorded for staff resolution.
- The existing guest self-service extension must use the same paid adjustment path; it must not lengthen a booking without collecting the extension fee.

## Constraints and builder calls

- Reuse the existing Laravel booking, room-block, payment-ledger, PayMongo GCash, and activity-log conventions.
- Existing pricing is the source of the hourly rate; no new rates are invented.
- Additional charges are separate payment-ledger entries. Applying an extension must not falsely change the paid status of the original booking.
- If an active room booking or room block conflicts with the requested interval, show a plain-language error and leave the original schedule and payment records untouched.
