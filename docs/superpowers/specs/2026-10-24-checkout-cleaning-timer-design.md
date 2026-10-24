# Checkout Cleaning Timer Design

**Intent:** When staff checks out a guest, the room should automatically enter its existing cleaning-block state for 20 minutes, with a clear live countdown visible in the room operations screens.

**Goals:** Reuse room blocks as the source of truth; keep the room unavailable for the cleaning window; show remaining time on both the housekeeping board and the admin room detail card, styled to fit Carolina's existing light/dark admin design.

**Non-goals:** Do not change manually scheduled cleaning or maintenance durations, add a new timer table, or automate the physical inspection after the timer ends. Existing room-block expiry and inventory refresh remain authoritative.

**Approaches:** (1) Reuse the checkout-created `RoomBlock` and add a countdown to its existing end time; this avoids schema changes and preserves availability behavior. (2) Add a dedicated timer record/service; this duplicates the existing block and risks disagreement with inventory. Chosen: approach 1 because room blocks already protect reservations and expire automatically.

**Builder calls:** Checkout starts the block at the recorded checkout time and ends it exactly 20 minutes later. Active cleaning blocks display `MM:SS` remaining on both staff room views; other scheduled tasks keep their current date/time display. At expiry, existing request-time inventory refresh completes the block and the room UI reflects availability on its next load.
