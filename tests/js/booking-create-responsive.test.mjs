import assert from 'node:assert/strict';
import test from 'node:test';
import { after, before } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const [view, baseStyles, paletteStyles] = await Promise.all([
    readFile(new URL('../../resources/views/bookings/create.blade.php', import.meta.url), 'utf8'),
    readFile(new URL('../../public/css/site.css', import.meta.url), 'utf8'),
    readFile(new URL('../../public/css/palette.css', import.meta.url), 'utf8'),
]);
const bookingStyles = [...view.matchAll(/<style>([\s\S]*?)<\/style>/g)].map(match => match[1]).join('\n');
const estimateScript = view.match(/<script>\s*(document.addEventListener\('DOMContentLoaded', \(\) => \{\s*const total = document.getElementById\('booking-summary-total'[\s\S]*?)<\/script>/)?.[1];
const datePickerScript = view.match(/const ranges = @json\(\$blockedRanges\);\s*([\s\S]*?)\s*const updatePropertyDate =/)?.[1];
let browser;

before(async () => { browser = await chromium.launch({ headless: true }); });
after(async () => { await browser?.close(); });

async function render(viewport) {
    const page = await browser.newPage({ viewport });
    await page.setContent(`<html><head><style>${baseStyles}\n${paletteStyles}\n${bookingStyles}</style></head><body>
        <section class="booking-page">
            <header class="booking-page-heading"><span class="eyebrow">RESERVATION DETAILS</span><h1>Complete your booking.</h1></header>
            <main class="booking-form"><form><input type="hidden" name="booking_type" value="dates"><input type="hidden" name="check_in" value="2026-11-12"><input type="hidden" name="check_out" value="2026-11-14"><section class="booking-type-selector"><span class="booking-type-label">Choose your stay type</span><div class="booking-type-options"><a class="active"><strong>Book by dates</strong><small>Overnight or multi-day stays</small></a><a><strong>Rent by hours</strong><small>Short stays for 3, 12, or 24 hours</small></a></div></section><div class="stay-date-fields"><label>Check in<input readonly value="Nov 12, 2026"></label><label>Check out<input readonly value="Nov 14, 2026"></label></div><section class="availability-calendar"><div class="calendar-heading"><strong>Room availability</strong></div><div class="calendar-weekdays">${'<span>Sun</span>'.repeat(7)}</div><div class="calendar-days">${'<button class="calendar-day">12</button>'.repeat(14)}</div></section><section class="stay-party-details"><strong>Guest details</strong><div class="party-detail-fields"><label>Total guests</label><label>Children</label><label>Pets</label></div></section><section class="guest-contact-section"><h3>Guest contact information</h3><div class="booking-contact-fields"><label>Full name<input></label><label>Email address<input></label><label>Phone number<input></label></div></section><details class="promo-entry"><summary>Promo code</summary></details><button class="button" id="booking-submit" type="submit">Continue to review</button></form></main>
            <aside class="booking-summary"><div class="booking-summary-head">Your selected room</div><div class="booking-summary-image"><img alt="Room"></div><div class="booking-summary-content"><h3>Room 5</h3><p>Whole house · Up to 10 guests</p><div class="booking-summary-rate"><span>Room rate</span><strong>₱4,500 <small>per night</small></strong></div></div><div class="booking-summary-total"><span>Estimated total</span><strong id="booking-summary-total" data-night-rate="4500" data-hourly-rate="187.5" data-package-rate="" data-package-hours="">Choose dates</strong><small id="booking-summary-period">Choose your stay to see an estimate.</small></div></aside>
        </section><script>${estimateScript}</script></body></html>`);
    return page;
}

test('booking page source gives the room summary one estimate and keeps the promo code expandable', () => {
    assert.match(view, /class="booking-page-heading"/);
    assert.match(view, /class="booking-summary-total"/);
    assert.match(view, /<details[^>]*class="promo-entry"/);
});

test('date calendar starts closed and guest mode does not repeat the sign-in/guest choice', () => {
    const calendarTag = view.match(/<section class="availability-calendar"[^>]*>/)?.[0] ?? '';
    const checkInField = view.match(/<input id="check-in-display"[^>]*>/)?.[0] ?? '';
    const guestBlock = view.match(/@guest([\s\S]*?)@endguest/)?.[1] ?? '';

    assert.match(calendarTag, /\bhidden\b/, 'availability calendar should not be expanded on first load');
    assert.match(checkInField, /aria-expanded="false"/, 'check-in control should announce that its calendar starts closed');
    assert.match(guestBlock, /@if\(!\$isGuest\)[\s\S]*?<div class="checkout-choice"/, 'account and guest actions should only appear before guest mode is selected');
});

test('guest contact section does not render an orphan divider between booking details', () => {
    const guestContactTransition = view.match(/<section class="stay-party-details"[\s\S]*?<\/section>([\s\S]*?)<h3>Guest contact information<\/h3>/)?.[1] ?? '';

    assert.doesNotMatch(guestContactTransition, /<hr\b/i, 'the guest-details-to-contact transition should not include a standalone divider');
});

test('date fields open the hidden availability calendar and its close button dismisses it', async () => {
    const page = await browser.newPage({ viewport: { width: 390, height: 900 } });
    try {
        await page.clock.install({ time: new Date('2026-11-11T12:00:00+08:00') });
        await page.setContent(`<html><head><style>${baseStyles}\n${paletteStyles}\n${bookingStyles}</style></head><body>
            <label>Check in<input id="check-in-display" type="text" aria-controls="availability-calendar" aria-expanded="false"><input id="check-in" type="hidden"></label>
            <label>Check out<input id="check-out-display" type="text" aria-controls="availability-calendar" aria-expanded="false"><input id="check-out" type="hidden"></label>
            <section class="availability-calendar" id="availability-calendar" hidden><div class="calendar-heading"><strong>Room availability</strong><div class="calendar-controls"><button id="previous-month">‹</button><b id="calendar-month"></b><button id="next-month">›</button><button id="close-availability-calendar" class="calendar-close">×</button></div></div><div id="calendar-days" class="calendar-days"></div><p id="calendar-note"></p></section>
        </body></html>`);
        await page.addScriptTag({ content: `const ranges = [];${datePickerScript}\nrefreshInputs(); render();` });

        assert.equal(await page.locator('#availability-calendar').isHidden(), true, 'calendar should start collapsed');
        await page.locator('#check-in-display').click();
        assert.equal(await page.locator('#availability-calendar').isVisible(), true, 'check-in should open the calendar');
        assert.equal(await page.locator('#check-in-display').getAttribute('aria-expanded'), 'true');
        await page.locator('#close-availability-calendar').click();
        assert.equal(await page.locator('#availability-calendar').isHidden(), true, 'close control should collapse the calendar');
        await page.locator('#check-out-display').click();
        assert.equal(await page.locator('#availability-calendar').isVisible(), true, 'check-out should also open the calendar');
        await page.locator('#calendar-days .calendar-day:not([disabled])').first().click();
        const nextDate = await page.locator('#calendar-days').evaluate(days => {
            const checkIn = document.getElementById('check-in').value;
            return [...days.querySelectorAll('.calendar-day:not([disabled])')].find(day => day.dataset.date > checkIn)?.dataset.date;
        });
        await page.locator(`#calendar-days .calendar-day[data-date="${nextDate}"]`).click();
        assert.equal(await page.locator('#availability-calendar').isHidden(), true, 'calendar should collapse after completing the date range');
        assert.equal(await page.locator('#check-out-display').getAttribute('aria-expanded'), 'false');
    } finally {
        await page.close();
    }
});

test('booking form and room summary align on desktop and stack without horizontal overflow on mobile', async () => {
    for (const width of [1440, 390, 320]) {
        const page = await render({ width, height: 900 });
        try {
            const layout = await page.evaluate(() => {
                const rect = selector => {
                    const { x, y, width, height, right, bottom } = document.querySelector(selector).getBoundingClientRect();
                    return { x, y, width, height, right, bottom };
                };
                return {
                    viewport: document.documentElement.clientWidth,
                    heading: rect('.booking-page-heading'),
                    form: rect('.booking-form'),
                    summary: rect('.booking-summary'),
                    calendar: rect('.availability-calendar'),
                    bodyWidth: document.documentElement.scrollWidth,
                };
            });

            assert.ok(layout.bodyWidth <= layout.viewport, `booking page should not overflow horizontally at ${width}px: ${JSON.stringify(layout)}`);
            assert.ok(layout.heading.width > 0 && layout.form.width > 0 && layout.summary.width > 0);
            if (width > 800) {
                assert.ok(Math.abs(layout.heading.y - layout.summary.y) < 20, `room summary should align with the booking heading on desktop: ${JSON.stringify(layout)}`);
                assert.ok(layout.summary.x > layout.form.x, 'room summary should sit beside the form on desktop');
            } else {
                assert.ok(layout.heading.bottom <= layout.summary.y, 'mobile order should start with the booking title');
                assert.ok(layout.summary.bottom <= layout.form.y, 'mobile room summary should appear before the long form');
                assert.ok(layout.calendar.width <= layout.form.width, `mobile calendar should fit inside the form column: ${JSON.stringify(layout)}`);
            }

        } finally {
            await page.close();
        }
    }
});

test('booking form starts near the heading while keeping a clear breathing gap', async () => {
    for (const width of [1440]) {
        const page = await browser.newPage({ viewport: { width, height: 900 } });
        try {
            await page.setContent(`<html><head><style>${baseStyles}\n${paletteStyles}\n${bookingStyles}</style></head><body>
                <section class="booking-page"><header class="booking-page-heading"><span class="eyebrow">RESERVATION DETAILS</span><h1>Complete your booking.</h1><p>Choose your stay details and guest information.</p></header><main class="booking-form"><form><section class="booking-review-panel"><h2>Review your reservation</h2><p>This is a summary only.</p></section></form></main><aside class="booking-summary">Room summary</aside></section>
            </body></html>`);
            const gap = await page.evaluate(() => {
                const heading = document.querySelector('.booking-page-heading').getBoundingClientRect();
                const form = document.querySelector('.booking-review-panel').getBoundingClientRect();
                return form.top - heading.bottom;
            });

            assert.ok(gap >= 28 && gap <= 48, `booking form should sit close with a comfortable gap at ${width}px; got ${gap}px`);
        } finally {
            await page.close();
        }
    }
});

test('date selectors are easy to tap and guest contact fields sit on a visible surface', async () => {
    assert.match(view, /<section class="guest-contact-section"[\s\S]*?Guest contact information[\s\S]*?booking-contact-fields/);

    for (const width of [1440, 390, 320]) {
        const page = await render({ width, height: 900 });
        try {
            const result = await page.evaluate(() => {
                const dateField = document.querySelector('.stay-date-fields input').getBoundingClientRect();
                const stayType = document.querySelector('.booking-type-selector').getBoundingClientRect();
                const contact = document.querySelector('.guest-contact-section');
                const contactStyle = getComputedStyle(contact);
                return {
                    dateHeight: dateField.height,
                    stayTypeHeight: stayType.height,
                    contactBackground: contactStyle.backgroundColor,
                    contactBorder: contactStyle.borderTopWidth,
                    contactWidth: contact.getBoundingClientRect().width,
                    viewport: document.documentElement.clientWidth,
                    bodyWidth: document.documentElement.scrollWidth,
                };
            });

            assert.ok(result.dateHeight >= 52, `date selector should provide a comfortable tap target at ${width}px: ${JSON.stringify(result)}`);
            assert.notEqual(result.contactBackground, 'rgba(0, 0, 0, 0)', `guest contact area should have an opaque surface at ${width}px`);
            assert.ok(Number.parseFloat(result.contactBorder) >= 1, 'guest contact area should have a visible outline');
            assert.ok(result.contactWidth > 0 && result.contactWidth <= result.viewport);
            assert.ok(result.bodyWidth <= result.viewport, `booking form should not overflow at ${width}px`);
            if (width === 1440) assert.ok(result.stayTypeHeight < 125, `stay type selector should remain compact on desktop: ${JSON.stringify(result)}`);
        } finally {
            await page.close();
        }
    }
});

test('reservation review stays compact, readable, and keeps every booking value in both themes', async () => {
    const fields = [
        ['Room', 'Room 5'], ['Room type', 'Whole house'], ['Stay type', 'Overnight stay'],
        ['Check-in', 'Nov 12, 2026'], ['Check-out', 'Nov 13, 2026'], ['Length of stay', '1 night'],
        ['Guests', '1 guest'], ['Pets', 'None'], ['Booked for', 'Niccolo Paganini'],
        ['Email', 'zyroncanchela420@gmail.com'], ['Phone', '+639169907895'], ['Special request', 'None'],
    ];
    const markup = fields.map(([label, value]) => `<div class="booking-review-item"><dt>${label}</dt><dd>${value}</dd></div>`).join('');

    for (const mode of ['light', 'dark']) {
        for (const width of [1440, 390, 320]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            try {
                await page.setContent(`<html class="${mode === 'dark' ? 'dark-mode' : ''}"><head><style>${baseStyles}\n${paletteStyles}\n${bookingStyles}</style></head><body>
                    <main class="booking-form"><section class="booking-review-panel"><div class="booking-review-heading"><span class="booking-review-kicker">CHECK BEFORE YOU CONTINUE</span><h2>Review your reservation</h2><p>This is a summary only. Use Previous if you want to change any details.</p></div><dl class="booking-review-grid">${markup}</dl><p class="booking-review-note">This is not a confirmed booking. Your request will be submitted when you choose Proceed to payment. The final amount and payment choices will appear on the next page.</p></section><div class="booking-step-actions"><button class="button button-outline" id="booking-previous" type="button">Previous</button><button class="button" id="booking-submit" type="button">Proceed to payment</button></div></main>
                </body></html>`);

                const result = await page.evaluate(() => {
                    const panel = document.querySelector('.booking-review-panel');
                    const heading = document.querySelector('.booking-review-heading');
                    const item = document.querySelector('.booking-review-item');
                    const previous = document.querySelector('#booking-previous');
                    const submit = document.querySelector('#booking-submit');
                    return {
                        width: document.documentElement.clientWidth,
                        scrollWidth: document.documentElement.scrollWidth,
                        panelPadding: Number.parseFloat(getComputedStyle(panel).paddingTop),
                        headingGap: Number.parseFloat(getComputedStyle(heading).marginBottom),
                        itemPadding: Number.parseFloat(getComputedStyle(item).paddingTop),
                        values: [...document.querySelectorAll('.booking-review-item dd')].map(value => value.textContent),
                        previousBg: getComputedStyle(previous).backgroundColor,
                        previousText: getComputedStyle(previous).color,
                        previousBorder: getComputedStyle(previous).borderTopWidth,
                        submitBg: getComputedStyle(submit).backgroundColor,
                        previousHeight: previous.getBoundingClientRect().height,
                    };
                });

                assert.equal(result.values.length, fields.length, `all reservation details should remain visible at ${width}px in ${mode} mode`);
                assert.deepEqual(result.values, fields.map(([, value]) => value));
                assert.ok(result.scrollWidth <= result.width, `review should fit the viewport at ${width}px in ${mode} mode`);
                assert.ok(result.panelPadding <= 20, `review panel should use compact padding at ${width}px in ${mode} mode`);
                assert.ok(result.headingGap <= 14, `review heading spacing should be compact at ${width}px in ${mode} mode`);
                assert.ok(result.itemPadding <= 9, `review detail rows should be compact at ${width}px in ${mode} mode`);
                assert.notEqual(result.previousBg, 'rgba(0, 0, 0, 0)', `Previous needs a visible muted surface at ${width}px in ${mode} mode`);
                assert.notEqual(result.previousBg, result.submitBg, `Previous should not compete with the primary action at ${width}px in ${mode} mode`);
                assert.ok(Number.parseFloat(result.previousBorder) >= 1, 'Previous should retain a visible boundary');
                assert.ok(result.previousHeight >= 44, 'Previous should remain a comfortable touch target');
                assert.notEqual(result.previousText, 'rgba(0, 0, 0, 0)', 'Previous text should remain visible');
            } finally {
                await page.close();
            }
        }
    }
});

test('booking primary actions use dirty-white labels in light and dark themes', async () => {
    for (const mode of ['light', 'dark']) {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
        try {
            await page.setContent(`<html class="${mode === 'dark' ? 'dark-mode' : ''}"><head><style>${baseStyles}\n${paletteStyles}\n${bookingStyles}</style></head><body>
                <main class="booking-form"><form><button class="button" id="booking-submit" type="button">Continue to review</button><div class="booking-step-actions"><button class="button button-outline" id="booking-previous" type="button">Previous</button></div></form></main>
            </body></html>`);

            const result = await page.evaluate(() => {
                const submit = document.querySelector('#booking-submit');
                const continueColor = getComputedStyle(submit).color;
                submit.textContent = 'Proceed to payment';
                return {
                    continueColor,
                    paymentColor: getComputedStyle(submit).color,
                    previousColor: getComputedStyle(document.querySelector('#booking-previous')).color,
                };
            });

            assert.equal(result.continueColor, 'rgb(245, 245, 240)', `Continue to review should use dirty-white text in ${mode} mode`);
            assert.equal(result.paymentColor, 'rgb(245, 245, 240)', `Proceed to payment should use dirty-white text in ${mode} mode`);
            assert.notEqual(result.previousColor, result.continueColor, 'The muted Previous button should retain its separate text treatment');
        } finally {
            await page.close();
        }
    }
});

test('hourly booking cards keep consistent spacing inside the entry fields wrapper', async () => {
    for (const width of [1440, 390, 320]) {
        const page = await browser.newPage({ viewport: { width, height: 900 } });
        try {
            await page.setContent(`<html><head><style>${baseStyles}\n${paletteStyles}\n${bookingStyles}</style></head><body>
                <main class="booking-form"><form><div id="booking-entry-fields">
                    <section class="booking-type-selector"><span class="booking-type-label">Choose your stay type</span><div class="booking-type-options"><a>Book by dates</a><a class="active">Rent by hours</a></div></section>
                    <div class="hourly-booked-summary"><strong>Booked times</strong><ul><li>12 AM – 7 PM</li></ul></div>
                    <div class="hourly-rental"><p>Short stay rental</p><label>Duration</label><label>Check-in date</label><label>Check-in time</label><p id="hourly-total">Estimated total</p></div>
                    <section class="stay-party-details"><strong>Guest details</strong><div class="party-detail-fields"><label>Total guests</label><label>Children</label><label>Pets</label></div></section>
                </div></form></main></body></html>`);

            const gaps = await page.evaluate(() => {
                const selectors = ['.booking-type-selector', '.hourly-booked-summary', '.hourly-rental', '.stay-party-details'];
                const elements = selectors.map(selector => document.querySelector(selector).getBoundingClientRect());
                return elements.slice(1).map((element, index) => Math.round(element.top - elements[index].bottom));
            });

            assert.ok(gaps.every(gap => gap >= 12), `hourly booking cards should have at least 12px between them at ${width}px; got ${gaps.join(', ')}px`);
        } finally {
            await page.close();
        }
    }
});

test('room estimate follows the selected date range', async () => {
    const page = await render({ width: 390, height: 900 });
    try {
        await page.waitForFunction(() => document.getElementById('booking-summary-total')?.textContent.includes('9,000'));
        assert.match(await page.locator('#booking-summary-period').textContent(), /2 nights/);
    } finally {
        await page.close();
    }
});
