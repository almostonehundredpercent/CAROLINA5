import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const stylesheet = await readFile(new URL('../../public/css/admin.css', import.meta.url), 'utf8');
const sidebarTemplate = await readFile(new URL('../../resources/views/admin/partials/sidebar.blade.php', import.meta.url), 'utf8');
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

function pageMarkup(dark = false) {
    return `<!doctype html><html class="${dark ? 'dark-mode' : ''}"><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${stylesheet}</style></head><body>
        <div class="admin-shell"><aside class="admin-sidebar">
            <a class="admin-brand" href="#">Carolina</a>
            <nav class="admin-nav" aria-label="Staff navigation">
                <a href="#"><i>▦</i>Overview</a><a href="#"><i>▤</i>Bookings</a>
                <a href="#"><i>⌂</i>Rooms</a><a href="#"><i>♙</i>Guests</a>
                <details class="admin-more"><summary>More <span>⌄</span></summary><div class="admin-more-links"><a href="#">Reports</a><a href="#">Activity log</a></div></details>
            </nav><button class="admin-theme-toggle">Dark mode</button>
            <a class="admin-home" href="#">Go to home page</a><form class="admin-logout"><button class="logout-button">Log out</button></form>
        </aside><main class="admin-main"><section class="panel"><div class="admin-table-wrap">
            <table><thead><tr><th>Guest</th><th>Stay</th><th>Actions</th></tr></thead><tbody>
                <tr><td data-label="Guest"><b>Carolina Guest</b><small>guest@example.test</small></td><td data-label="Stay">Oct 24, 2026 · Room 11</td><td data-label="Actions"><a class="status-select" href="#">Open profile</a></td></tr>
                <tr><td data-label="Guest"><b>Another Guest</b><small>another@example.test</small></td><td data-label="Stay">Oct 26, 2026 · Room 7</td><td data-label="Actions"><a class="status-select" href="#">Open profile</a></td></tr>
                <tr><td class="no-bookings" colspan="3">No matching records.</td></tr>
            </tbody></table>
        </div></section></main></div>
    </body></html>`;
}

async function enhanceAdminTables(page) {
    assert.match(sidebarTemplate, /admin-mobile-tables\.js/, 'the shared admin shell should load mobile table enhancement');
    const script = await readFile(new URL('../../public/js/admin-mobile-tables.js', import.meta.url), 'utf8');
    await page.addScriptTag({ content: script });
}

test('small-screen admin navigation remains visible without horizontal overflow', async () => {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    try {
        await page.setContent(pageMarkup());
        await enhanceAdminTables(page);
        const metrics = await page.locator('.admin-nav').evaluate(nav => ({
            display: getComputedStyle(nav).display,
            scrollWidth: nav.scrollWidth,
            clientWidth: nav.clientWidth,
            columns: getComputedStyle(nav).gridTemplateColumns,
        }));

        assert.equal(metrics.display, 'grid', 'mobile navigation should use the two-column layout configured for small screens');
        assert.ok(metrics.scrollWidth <= metrics.clientWidth, 'navigation should not hide links behind horizontal scrolling');
        assert.equal(metrics.columns.split(' ').length, 2, 'primary navigation should be arranged in two columns');
    } finally {
        await page.close();
    }
});

for (const width of [390, 320]) {
    for (const dark of [false, true]) {
        test(`${dark ? 'dark' : 'light'} admin data cards fit a ${width}px viewport and retain clear labels`, async () => {
            const page = await browser.newPage({ viewport: { width, height: 844 } });
            try {
                await page.setContent(pageMarkup(dark));
                await enhanceAdminTables(page);
                const metrics = await page.evaluate(() => ({
                    viewportWidth: document.documentElement.clientWidth,
                    pageWidth: document.documentElement.scrollWidth,
                    wrapperOverflow: getComputedStyle(document.querySelector('.mobile-stack-wrap')).overflowX,
                    rowDisplay: getComputedStyle(document.querySelector('.mobile-stack-table tbody tr')).display,
                    cellDisplay: getComputedStyle(document.querySelector('.mobile-stack-table td')).display,
                    labels: [...document.querySelectorAll('.mobile-stack-table tbody tr:not(.mobile-table-empty) td')].map(cell => getComputedStyle(cell, '::before').content),
                    actionHeight: document.querySelector('.mobile-stack-table .status-select').getBoundingClientRect().height,
                }));

                assert.ok(metrics.pageWidth <= metrics.viewportWidth, `admin page overflows by ${metrics.pageWidth - metrics.viewportWidth}px`);
                assert.equal(metrics.wrapperOverflow, 'visible', 'mobile cards should not be trapped in a sideways-scrolling table');
                assert.equal(metrics.rowDisplay, 'grid', 'each record should be presented as a mobile card');
                assert.equal(metrics.cellDisplay, 'grid', 'card fields should align with their labels');
                assert.ok(metrics.labels.every(label => label !== 'none' && label !== 'normal'), 'each card value should retain its column label');
                assert.ok(metrics.actionHeight >= 44, `profile action is only ${metrics.actionHeight}px high`);
            } finally {
                await page.close();
            }
        });
    }
}

test('mobile enhancement gives generic admin table cells their readable column labels', async () => {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    try {
        await page.setContent(pageMarkup());
        await enhanceAdminTables(page);
                const labels = await page.locator('.mobile-stack-table tbody tr:not(.mobile-table-empty) td').evaluateAll(cells => cells.map(cell => cell.dataset.label));
        assert.deepEqual(labels, ['Guest', 'Stay', 'Actions', 'Guest', 'Stay', 'Actions']);
        assert.equal(await page.locator('.admin-table-wrap').first().evaluate(node => node.classList.contains('mobile-stack-wrap')), true);
                assert.equal(await page.locator('.mobile-stack-table .mobile-table-empty').evaluate(node => getComputedStyle(node).display), 'block');
    } finally {
        await page.close();
    }
});
