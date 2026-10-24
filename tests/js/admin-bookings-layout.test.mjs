import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const stylesheet = await readFile(new URL('../../public/css/admin.css', import.meta.url), 'utf8');
const pageTemplate = await readFile(new URL('../../resources/views/admin/bookings.blade.php', import.meta.url), 'utf8');
let browser;

function luminance(color) {
    const channels = color.match(/[\d.]+/g)?.slice(0, 3).map(Number);
    assert.equal(channels?.length, 3, `Expected an RGB color, received ${color}`);
    const linear = channels.map(value => {
        const channel = value / 255;
        return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    });
    return linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722;
}

function contrast(foreground, background) {
    const values = [luminance(foreground), luminance(background)].sort((a, b) => b - a);
    return (values[0] + 0.05) / (values[1] + 0.05);
}

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

test('bookings page uses the scoped layout and explicit table columns covered here', () => {
    assert.match(pageTemplate, /<body class="bookings-page">/);
    assert.match(pageTemplate, /<section class="panel bookings-reservations"/);
    assert.match(pageTemplate, /<table class="booking-table">/);
    for (const column of ['guest', 'reference', 'stay', 'total', 'status', 'actions']) {
        assert.match(pageTemplate, new RegExp(`booking-column-${column}`));
    }
});

async function render(width, dark) {
    const page = await browser.newPage({ viewport: { width, height: 900 } });
    await page.setContent(`
        <html class="${dark ? 'dark-mode' : ''}"><head><style>${stylesheet}</style></head>
        <body class="bookings-page"><div class="admin-shell"><aside class="admin-sidebar"></aside>
            <main class="admin-main">
                <section class="panel bookings-reservations">
                    <div class="panel-heading"><div><p class="admin-kicker">ALL RESERVATIONS</p><h2>Review requests</h2><p class="booking-note">Payments update automatically when PayMongo confirms them. Confirming a request checks room availability again.</p></div><div class="booking-heading-actions"><a class="status-select" href="#">Export CSV</a><span class="pill">9 total</span></div></div>
                    <form class="booking-filters"><input placeholder="Guest name, email, or reference"><select><option>All statuses</option></select><input type="date"><select class="booking-page-size"><option>5 per page</option></select><button>Filter</button></form>
                    <div class="booking-results-summary"><span>Showing 1-5 of 9 bookings</span><span>Page 1 of 2</span></div>
                    <nav class="booking-pagination" aria-label="Booking page navigation"><div class="booking-pagination-buttons"><span class="booking-page-first is-disabled">First</span><span class="booking-page-previous is-disabled">Previous</span><span class="booking-page-number is-current">1</span><a class="booking-page-number" href="#">2</a><a class="booking-page-next" href="#">Next</a><a class="booking-page-last" href="#">Last</a></div></nav>
                    <div class="admin-table-wrap"><table class="booking-table"><colgroup>
                        <col class="booking-column-guest"><col class="booking-column-reference"><col class="booking-column-stay"><col class="booking-column-total"><col class="booking-column-status"><col class="booking-column-actions">
                    </colgroup><thead><tr><th>Guest</th><th>Reference</th><th>Room &amp; stay</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody><tr><td data-label="Guest"><b>A guest</b><small>guest@example.test</small><small>No phone</small></td>
                        <td class="booking-reference-cell" data-label="Reference"><code>CAR-123456</code></td>
                        <td class="booking-stay-cell" data-label="Room &amp; stay"><b>Room 1</b><small>Oct 24, 6 AM to Oct 26, 6 AM</small></td><td class="booking-total-cell" data-label="Total">₱900.00</td>
                        <td data-label="Status"><div class="booking-status-stack"><span class="mini-status confirmed">Confirmed</span><span class="mini-status booking-payment-status paid">Payment Paid</span></div><small class="booking-payment-method">PayMongo · GCash</small></td>
                        <td class="booking-actions-cell" data-label="Actions"><div class="booking-actions"><div class="booking-action-buttons"><button class="status-select">Mark unpaid</button><button class="status-select cancelled">Cancel</button></div><div class="booking-action-note"><span>Refunds aren’t available within 3 days of check-in.</span></div>
                            <details class="booking-time-change" open><summary><span class="booking-time-change-title">Add checkout time</span><span class="booking-time-change-chevron" aria-hidden="true">⌄</span></summary>
                                <p class="booking-time-change-intro">Choose extra time and payment.</p><form><label>Extra time<select><option>1 hour · ₱75</option><option>2 hours · ₱150</option></select></label><label>Payment<select><option>Pay by GCash</option><option>Cash received</option></select></label><button class="status-select confirmed" type="submit"><span>Continue</span><span aria-hidden="true">→</span></button></form>
                            </details></div></td>
                    </tr></tbody></table></div>
                    <nav class="booking-pagination" aria-label="Booking page navigation"><div class="booking-pagination-buttons"><span class="booking-page-first is-disabled">First</span><span class="booking-page-previous is-disabled">Previous</span><span class="booking-page-number is-current">1</span><a class="booking-page-number" href="#">2</a><a class="booking-page-next" href="#">Next</a><a class="booking-page-last" href="#">Last</a></div></nav>
                </section>
            </main></div></body></html>`);
    return page;
}

for (const [width, dark] of [[1600, false], [1600, true], [768, false], [768, true], [390, false], [390, true], [320, false], [320, true]]) {
    test(`${dark ? 'dark' : 'light'} bookings layout is compact and readable at ${width}px`, async () => {
        const page = await render(width, dark);
        try {
            const metrics = await page.evaluate(() => {
                const px = (selector, property) => Number.parseFloat(getComputedStyle(document.querySelector(selector))[property]);
                const main = document.querySelector('.admin-main');
                const tableWrap = document.querySelector('.admin-table-wrap');
                return {
                    mainGutter: px('.admin-main', 'paddingLeft'),
                    bodyText: px('.booking-table td', 'fontSize'),
                    secondaryText: px('.booking-table td small', 'fontSize'),
                    headerText: px('.booking-table th', 'fontSize'),
                    rowPadding: px('.booking-table td', 'paddingTop'),
                    buttonText: px('.booking-action-buttons button', 'fontSize'),
                    buttonHeight: document.querySelector('.booking-action-buttons button').getBoundingClientRect().height,
                    timeChangeRight: document.querySelector('.booking-time-change').getBoundingClientRect().right,
                    timeChangeButtonHeight: document.querySelector('.booking-time-change form > button').getBoundingClientRect().height,
                    timeChangeButtonWidth: document.querySelector('.booking-time-change form > button').getBoundingClientRect().width,
                    timeChangeButtonText: getComputedStyle(document.querySelector('.booking-time-change form > button')).color,
                    timeChangeButtonStops: getComputedStyle(document.querySelector('.booking-time-change form > button')).backgroundImage
                        .match(/rgba?\([^)]+\)/g) ?? [],
                    timeChangeSelectsFit: [...document.querySelectorAll('.booking-time-change select')]
                        .every(control => control.getBoundingClientRect().right <= document.querySelector('.booking-time-change').getBoundingClientRect().right),
                    timeChangePanelWidth: document.querySelector('.booking-time-change').getBoundingClientRect().width,
                    filterText: px('.booking-filters input', 'fontSize'),
                    filterHeight: document.querySelector('.booking-filters input').getBoundingClientRect().height,
                    actionWidth: document.querySelector('.booking-column-actions').getBoundingClientRect().width,
                    tableMinWidth: px('.booking-table', 'minWidth'),
                    horizontalScroll: tableWrap.scrollWidth > tableWrap.clientWidth,
                    mobileViewport: innerWidth <= 900,
                    mainWidth: main.getBoundingClientRect().width,
                    rowDisplay: getComputedStyle(document.querySelector('.booking-table tbody tr')).display,
                    guestLabel: getComputedStyle(document.querySelector('.booking-table td'), '::before').content,
                    pageOverflow: document.documentElement.scrollWidth > innerWidth,
                    actionRight: document.querySelector('.booking-action-buttons button').getBoundingClientRect().right,
                    navigationHeight: document.querySelector('.booking-page-next').getBoundingClientRect().height,
                    referenceWrap: getComputedStyle(document.querySelector('.booking-reference-cell')).whiteSpace,
                    filtersFit: [...document.querySelectorAll('.booking-filters input, .booking-filters select, .booking-filters button')]
                        .every(control => control.getBoundingClientRect().right <= innerWidth),
                    headingIsStacked: document.querySelector('.booking-heading-actions').getBoundingClientRect().top >=
                        document.querySelector('.panel-heading .booking-note').getBoundingClientRect().bottom,
                    exportIsSingleLine: getComputedStyle(document.querySelector('.booking-heading-actions a')).whiteSpace === 'nowrap',
                    exportHeight: document.querySelector('.booking-heading-actions a').getBoundingClientRect().height,
                    filterAfterHeading: document.querySelector('.booking-filters').getBoundingClientRect().top >=
                        document.querySelector('.booking-heading-actions').getBoundingClientRect().bottom,
                    visibleNavButtons: [...document.querySelectorAll('.booking-pagination:first-of-type .booking-pagination-buttons > *')]
                        .filter(control => getComputedStyle(control).display !== 'none').map(control => control.textContent.trim()),
                    navButtonTops: [...document.querySelectorAll('.booking-pagination:first-of-type .booking-pagination-buttons > *')]
                        .filter(control => getComputedStyle(control).display !== 'none').map(control => control.getBoundingClientRect().top),
                    visiblePaginationCount: [...document.querySelectorAll('.booking-pagination')]
                        .filter(nav => getComputedStyle(nav).display !== 'none').length,
                };
            });

            assert.ok(metrics.mainGutter <= (width <= 700 ? 16 : 32), `page gutter is ${metrics.mainGutter}px`);
            assert.ok(metrics.bodyText >= 14, `table text is ${metrics.bodyText}px`);
            assert.ok(metrics.secondaryText >= 13, `secondary table text is ${metrics.secondaryText}px`);
            assert.ok(metrics.headerText >= 12, `table header text is ${metrics.headerText}px`);
            assert.ok(metrics.rowPadding <= 12, `table row padding is ${metrics.rowPadding}px`);
            assert.ok(metrics.buttonText >= 13, `action text is ${metrics.buttonText}px`);
            assert.ok(metrics.buttonHeight >= 42, `action target is ${metrics.buttonHeight}px high`);
            assert.ok(metrics.timeChangeRight <= width, `time-change panel overflows viewport at ${metrics.timeChangeRight}px`);
            assert.ok(metrics.timeChangeButtonHeight >= 46, `time-change action is only ${metrics.timeChangeButtonHeight}px high`);
            assert.ok(metrics.timeChangeButtonWidth >= metrics.timeChangePanelWidth - 32,
                `continue action is only ${metrics.timeChangeButtonWidth}px wide in a ${metrics.timeChangePanelWidth}px panel`);
            assert.ok(metrics.timeChangeSelectsFit, 'time-change fields should stay within their panel');
            for (const stop of metrics.timeChangeButtonStops) {
                assert.ok(contrast(metrics.timeChangeButtonText, stop) >= 4.5,
                    `continue action contrast is ${contrast(metrics.timeChangeButtonText, stop).toFixed(2)}:1 at ${stop}`);
            }
            assert.ok(metrics.filterText >= 14, `filter text is ${metrics.filterText}px`);
            assert.ok(metrics.filterHeight >= 44, `filter target is ${metrics.filterHeight}px high`);
            if (!metrics.mobileViewport) {
                assert.ok(metrics.actionWidth >= 280, `actions column is only ${metrics.actionWidth}px wide`);
                assert.equal(metrics.visiblePaginationCount, 1, 'desktop should show one pagination bar');
            }
            if (metrics.mobileViewport) {
                assert.equal(metrics.visiblePaginationCount, 2, 'long mobile cards should keep both page controls');
                assert.equal(metrics.rowDisplay, 'grid', 'mobile bookings should be shown as cards');
                assert.match(metrics.guestLabel, /Guest/, 'mobile cards should label each field');
                assert.ok(!metrics.horizontalScroll, 'mobile bookings should not need horizontal scrolling');
                assert.ok(!metrics.pageOverflow, 'mobile page should fit the viewport');
                assert.ok(metrics.actionRight <= width, 'booking actions should fit on screen');
                assert.ok(metrics.buttonHeight >= 44, 'booking actions should be easy to tap');
                assert.ok(metrics.navigationHeight >= 44, 'page navigation should be easy to tap');
                assert.equal(metrics.referenceWrap, 'normal', 'references should wrap on narrow cards');
                assert.ok(metrics.filtersFit, 'booking filters should fit on screen');
                assert.ok(metrics.headingIsStacked, 'export controls should have their own row below the heading');
                assert.ok(metrics.exportIsSingleLine, 'Export CSV should stay on one line');
                assert.ok(metrics.exportHeight >= 44, 'export should be easy to tap');
                assert.ok(metrics.filterAfterHeading, 'filters should not collide with the export controls');
                assert.deepEqual(metrics.visibleNavButtons, ['Previous', 'Next'], 'mobile pagination should show only the essential controls');
                assert.equal(new Set(metrics.navButtonTops).size, 1, 'mobile pagination should fit on one row');
            }
        } finally {
            await page.close();
        }
    });
}
