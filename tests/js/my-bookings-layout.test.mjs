import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const siteStyles = await readFile(new URL('../../public/css/site.css', import.meta.url), 'utf8');
const responsiveStyles = await readFile(new URL('../../public/css/responsive.css', import.meta.url), 'utf8');
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

async function render(width) {
    const page = await browser.newPage({ viewport: { width, height: 900 } });
    await page.setContent(`<html class="dark-mode"><head><style>${siteStyles}\n${responsiveStyles}</style></head><body>
        <main><section class="section compact"><div class="booking-list"><article class="booking-row">
            <img src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt="Room 11">
            <div class="booking-details"><span class="status confirmed">Confirmed</span><h3>Room 11</h3>
                <p>Oct 21, 2026 – Oct 23, 2026 · 2 nights · 1 guest</p><small>Reference: CAR-6DWZLBNU</small></div>
            <div class="booking-actions"><strong>₱900</strong><a class="text-link" href="#details">View details</a>
                <a class="button button-outline booking-receipt-link" href="#receipt">View receipt</a></div>
            <div class="booking-review-area"><details class="review-form" open><summary>Leave a review</summary>
                <form><label>Overall rating<select><option>Choose rating</option><option>5 stars</option></select></label>
                    <div class="review-score-grid"><label>Cleanliness<select><option>Optional</option></select></label>
                        <label>Comfort<select><option>Optional</option></select></label><label>Value<select><option>Optional</option></select></label></div>
                    <label>Comment<textarea></textarea></label><button class="button small" type="button">Submit review</button>
                </form></details></div>
        </article></div></section></main></body></html>`);
    return page;
}

test('a checked-out booking keeps its price and actions in a distinct desktop column', async () => {
    const page = await render(1280);
    try {
        const layout = await page.evaluate(() => {
            const actions = document.querySelector('.booking-actions');
            const price = actions.querySelector('strong').getBoundingClientRect();
            const details = actions.querySelector('.text-link').getBoundingClientRect();
            const review = document.querySelector('.booking-review-area');
            return {
                display: getComputedStyle(actions).display,
                priceBottom: price.bottom,
                detailsTop: details.top,
                reviewDisplay: getComputedStyle(review).display,
                reviewAlignment: getComputedStyle(review).textAlign,
            };
        });
        assert.equal(layout.display, 'grid', 'booking actions should remain grouped even when review follows them');
        assert.ok(layout.detailsTop >= layout.priceBottom - 1, 'price and details link should not share a crowded line');
        assert.equal(layout.reviewDisplay, 'block', 'the review panel should not inherit the action-column grid');
        assert.equal(layout.reviewAlignment, 'left', 'review content should align naturally with the booking details');
    } finally {
        await page.close();
    }
});

test('a checked-out booking stacks its actions across the mobile card width', async () => {
    const page = await render(390);
    try {
        const layout = await page.evaluate(() => {
            const row = document.querySelector('.booking-row').getBoundingClientRect();
            const actions = document.querySelector('.booking-actions').getBoundingClientRect();
            return {
                rowWidth: row.width,
                actionsWidth: actions.width,
                documentWidth: document.documentElement.scrollWidth,
                viewportWidth: window.innerWidth,
            };
        });
        assert.ok(layout.actionsWidth >= layout.rowWidth - 40,
            `mobile action area is only ${layout.actionsWidth}px wide inside a ${layout.rowWidth}px booking card`);
        assert.ok(layout.documentWidth <= layout.viewportWidth,
            `mobile page overflows horizontally to ${layout.documentWidth}px`);
    } finally {
        await page.close();
    }
});
