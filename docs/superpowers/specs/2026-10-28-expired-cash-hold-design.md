# Expired Cash Hold Recovery

## Intent

Make cash-booking payment actions understandable and safe for staff when an unpaid request's 30-minute hold has expired. Staff must not accidentally record cash against inventory that may have been taken by another guest.

## Scope and behavior

- An expired, unpaid cash request remains visible, but is labeled **Hold expired** with its expiry time. It no longer offers **Mark paid** or an ambiguous **Confirm** action.
- It offers **Check availability & restore**. This uses the existing staff confirmation endpoint, which rechecks conflicts while holding the room lock. If the room is inactive, operationally closed, blocked, or booked by another guest, restoration fails without changing the booking or recording payment.
- After successful restoration, staff may use the existing **Mark paid** action only when cash was actually received. Restoration alone never records payment.
- If staff submits an outdated Mark paid form for an expired hold, show a clear inline error on the bookings page instead of a generic 422 page. JSON callers still receive a 422 validation response.
- PayMongo verification and refund workflows remain unchanged. Do not silently extend holds or create a new booking.

## Approaches

- **Chosen: guided two-step recovery.** Reuse the confirmation endpoint and existing payment ledger; add a visible expired state and specific action. This keeps availability and cash receipt separate.
- **Rejected: hide Mark paid only.** Prevents the error but gives staff no route to recover a free room.
- **Rejected: one-click restore and mark paid.** Fewer taps, but conflates room availability with physical cash receipt and increases the risk of false payment records.

## Constraints and risks

Use current Laravel/Pest/Blade patterns and no new dependency. Staff roles must retain current authorization. Never revive a conflicting booking. A new reservation could arrive after an availability check, so the restore action must recheck under the existing transaction and room lock.
