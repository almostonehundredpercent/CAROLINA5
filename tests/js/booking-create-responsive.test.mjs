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
let browser;

before(async () => { browser = await chromium.launch({ headless: true }); });
after(async () => { await browser?.close(); });

async function render(viewport) {
    const page = await browser.newPage({ viewport });
    await page.setContent(`<html><head><style>${baseStyles}\n${paletteStyles}\n${bookingStyles}</style></head><body>
        <section class="booking-page">
            <header class="booking-page-heading"><span class="eyebrow">RESERVATION DETAILS</span><h1>Complete your booking.</h1></header>
            <main class="booking-form"><form><input type="hidden" name="booking_type" value="dates"><input type="hidden" name="check_in" value="2026-11-12"><input type="hidden" name="check_out" value="2026-11-14"><section class="booking-type-selector"><span class="booking-type-label">Choose your stay type</span><div class="booking-type-options"><a class="active"><strong>Book by dates</strong><small>Overnight or multi-day stays</small></a><a><strong>Rent by hours</strong><small>Short stays for 3, 12, or 24 hours</small></a></div></section><div class="stay-date-fields"><label>Check in<input value="Nov 12, 2026"></label><label>Check out<input value="Nov 14, 2026"></label></div><section class="availability-calendar"><div class="calendar-heading"><strong>Room availability</strong></div><div class="calendar-weekdays">${'<span>Sun</span>'.repeat(7)}</div><div class="calendar-days">${'<button class="calendar-day">12</button>'.repeat(14)}</div></section><section class="stay-party-details"><strong>Guest details</strong><div class="party-detail-fields"><label>Total guests</label><label>Children</label><label>Pets</label></div></section><h3>Guest contact information</h3><div class="booking-contact-fields"><label>Full name<input></label><label>Email address<input></label><label>Phone number<input></label></div><details class="promo-entry"><summary>Promo code</summary></details><button class="button" id="booking-submit" type="submit">Continue to review</button></form></main>
            <aside class="booking-summary"><div class="booking-summary-head">Your selected room</div><div class="booking-summary-image"><img alt="Room"></div><div class="booking-summary-content"><h3>Room 5</h3><p>Whole house · Up to 10 guests</p><div class="booking-summary-rate"><span>Room rate</span><strong>₱4,500 <small>per night</small></strong></div></div><div class="booking-summary-total"><span>Estimated total</span><strong id="booking-summary-total" data-night-rate="4500" data-hourly-rate="187.5" data-package-rate="" data-package-hours="">Choose dates</strong><small id="booking-summary-period">Choose your stay to see an estimate.</small></div></aside>
        </section><script>${estimateScript}</script></body></html>`);
    return page;
}

test('booking page source gives the room summary one estimate and keeps the promo code expandable', () => {
    assert.match(view, /class="booking-page-heading"/);
    assert.match(view, /class="booking-summary-total"/);
    assert.match(view, /<details[^>]*class="promo-entry"/);
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

            assert.ok(layout.bodyWidth <= layout.viewport, `booking page should not overflow horizontally at ${width}px`);
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

test('room estimate follows the selected date range', async () => {
    const page = await render({ width: 390, height: 900 });
    try {
        await page.waitForFunction(() => document.getElementById('booking-summary-total')?.textContent.includes('9,000'));
        assert.match(await page.locator('#booking-summary-period').textContent(), /2 nights/);
    } finally {
        await page.close();
    }
});
