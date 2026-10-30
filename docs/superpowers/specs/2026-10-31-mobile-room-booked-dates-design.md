# Mobile Room Booked-Dates Design

## Intent

Help phone users check a room's unavailable dates without making room cards feel crowded. The desktop room page must remain visually and behaviorally unchanged.

## Scope

- Keep each room's existing “View booked dates” control collapsed by default.
- On phones (650px viewport width and below), give the full control a comfortable tap area and expand its calendar inline inside that room card.
- Keep at most one room calendar expanded on phones to limit page clutter.
- Preserve the existing red unavailable dates, availability details, and legend.
- Do not change desktop styling or desktop's current ability to open multiple room calendars.

## Approaches

- Keep the current independent accordions everywhere: lowest effort, but phone users can accumulate several large open calendars.
- Use an inline, single-open accordion only on phones: fits the existing markup, avoids overlays, and keeps desktop behavior unchanged. **Chosen** because it matches the approved mobile mockup and the user's desktop constraint.
- Open a bottom sheet on phones: compact, but changes the interaction pattern and separates the calendar from its room card.

## Design Calls

- The existing button and calendar remain the same component; no inventory or booking logic changes.
- Mobile-only CSS increases the control's minimum height to 56px and enlarges its disclosure icon.
- JavaScript closes another open calendar only when the viewport matches the existing mobile breakpoint (650px).
- Tests cover phone exclusivity and touch size, plus desktop independent calendars and baseline spacing.
