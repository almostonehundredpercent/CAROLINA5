# Check-in date dropdown

The room search check-in date control should open its existing calendar as a dropdown directly below the date field, consistent with the stay, guest, time, and price selectors. It must overlay lower search controls without changing the form's height, stay above room cards, fit within mobile viewports, and keep existing selection, dismissal, and accessibility behavior. Desktop layout and the date-selection rules remain unchanged.

## Design decisions

- Keep the current custom calendar and date-selection logic.
- Give the date trigger and calendar a shared positioned wrapper so the calendar anchors below it in CSS rather than relying on viewport-specific JavaScript measurements.
- Raise the date wrapper only while open; keep the search form's existing stacking above the results.
- Preserve outside-click, Escape, keyboard focus, and selection-close behavior.

## Acceptance checks

- At desktop and phone widths, opening the calendar places its top just below the check-in field and does not expand the search form.
- At a narrow 320px viewport, the dropdown stays inside the viewport without horizontal scrolling.
- The calendar remains above overlapping controls/cards and still sets the chosen date and closes.
- Escape and outside click still dismiss it, and opening another selector closes it.
