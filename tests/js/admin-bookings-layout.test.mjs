import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const stylesheet = await readFile(new URL('../../public/css/admin.css', import.meta.url), 'utf8');
const pageTemplate = await readFile(new URL('../../resources/views/admin/bookings.blade.php', import.meta.url), 'utf8');
let browser;

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
                    <form class="booking-filters"><input placeholder="Guest name, email, or reference"><select><option>All statuses</option></select><input type="date"><button>Filter</button></form>
                    <div class="admin-table-wrap"><table class="booking-table"><colgroup>
                        <col class="booking-column-guest"><col class="booking-column-reference"><col class="booking-column-stay"><col class="booking-column-total"><col class="booking-column-status"><col class="booking-column-actions">
                    </colgroup><thead><tr><th>Guest</th><th>Reference</th><th>Room &amp; stay</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody><tr><td><b>A guest</b><small>guest@example.test</small><small>No phone</small></td>
                        <td class="booking-reference-cell"><code>CAR-123456</code></td>
                        <td><b>Room 1</b><small>Oct 24, 6 AM to Oct 26, 6 AM</small></td><td class="booking-total-cell">₱900.00</td>
                        <td><div class="booking-status-stack"><span class="mini-status confirmed">Confirmed</span><span class="mini-status booking-payment-status paid">Payment Paid</span></div><small class="booking-payment-method">PayMongo · GCash</small></td>
                        <td class="booking-actions-cell"><div class="booking-actions"><div class="booking-action-buttons"><button class="status-select">Mark unpaid</button><button class="status-select cancelled">Cancel</button></div><div class="booking-action-note"><span>Refunds aren’t available within 3 days of check-in.</span></div></div></td>
                    </tr></tbody></table></div>
                </section>
            </main></div></body></html>`);
    return page;
}

for (const [width, dark] of [[1600, false], [1600, true], [390, false], [390, true]]) {
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
                    filterText: px('.booking-filters input', 'fontSize'),
                    filterHeight: document.querySelector('.booking-filters input').getBoundingClientRect().height,
                    actionWidth: document.querySelector('.booking-column-actions').getBoundingClientRect().width,
                    tableMinWidth: px('.booking-table', 'minWidth'),
                    horizontalScroll: tableWrap.scrollWidth > tableWrap.clientWidth,
                    mobileViewport: innerWidth <= 700,
                    mainWidth: main.getBoundingClientRect().width,
                };
            });

            assert.ok(metrics.mainGutter <= (width <= 700 ? 16 : 32), `page gutter is ${metrics.mainGutter}px`);
            assert.ok(metrics.bodyText >= 14, `table text is ${metrics.bodyText}px`);
            assert.ok(metrics.secondaryText >= 13, `secondary table text is ${metrics.secondaryText}px`);
            assert.ok(metrics.headerText >= 12, `table header text is ${metrics.headerText}px`);
            assert.ok(metrics.rowPadding <= 12, `table row padding is ${metrics.rowPadding}px`);
            assert.ok(metrics.buttonText >= 13, `action text is ${metrics.buttonText}px`);
            assert.ok(metrics.buttonHeight >= 42, `action target is ${metrics.buttonHeight}px high`);
            assert.ok(metrics.filterText >= 14, `filter text is ${metrics.filterText}px`);
            assert.ok(metrics.filterHeight >= 44, `filter target is ${metrics.filterHeight}px high`);
            if (!metrics.mobileViewport) assert.ok(metrics.actionWidth >= 280, `actions column is only ${metrics.actionWidth}px wide`);
            if (metrics.mobileViewport) assert.ok(metrics.horizontalScroll, 'mobile table should remain horizontally scrollable');
        } finally {
            await page.close();
        }
    });
}
