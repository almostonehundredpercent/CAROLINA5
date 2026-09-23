# Arrival experience

Reception and administrators use **Arrivals** in the staff navigation. It shows active reservations arriving within seven days, including overdue departures. Guest arrival updates never change booking dates or cancel reservations.

Confirm a reservation before setting its preparation status, estimated ready time, bag-drop availability and arrival instructions. Readiness is a staff update, not a guaranteed early check-in. Ready status is suppressed when a room is occupied or blocked. Updates older than twelve hours request a fresh reception update.

Guests reach their private page through the receipt, booking lookup and new reservation email. Signed links last seven days. Booking lookup issues a fresh link. Arrival submissions have their own one-hour signature, CSRF protection and rate limit.

Weather, water, power and travel notices can target all rooms or one room. Staff enter verified guidance, start and expiration times and can resolve a notice early. Notices appear only while active. No weather feed, SMS or automatic notice emails are configured; guests refresh the page to see updates.

Deployment adds nullable/defaulted booking fields and a stay_notices table. Existing rooms and reservations are not seeded or overwritten. The Render startup migration must be enabled. Roll back application code first if required; retain the additive schema to preserve staff updates. The migration down method deletes the new feature data and is not needed for a code rollback.

Automated checks cover signed/expired links, arrival updates, completed-stay rejection, role permissions, room readiness guards and notice resolution/expiry. Production email delivery and staff/mobile acceptance still require a live check.
