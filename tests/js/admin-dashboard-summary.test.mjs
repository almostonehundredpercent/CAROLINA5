import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const adminCss = await readFile(new URL('../../public/css/admin.css', import.meta.url), 'utf8');

test('dashboard summary panels stay compact and readable across themes and phone widths', async () => {
    const browser = await chromium.launch();
    try {
        for (const mode of ['light', 'dark']) {
            for (const width of [1440, 390]) {
                const page = await browser.newPage({ viewport: { width, height: 900 } });
                await page.setContent(`<html class="${mode === 'dark' ? 'dark-mode' : ''}"><head><style>${adminCss}</style></head><body>
                    <section class="panel dashboard-summary-panel">
                        <div class="panel-heading"><div><p class="admin-kicker">SHIFT ALERTS</p><h2>Needs attention</h2></div></div>
                        <div class="status-row"><span>Cleaning or maintenance in the next 24 hours</span><b>2</b></div>
                    </section>
                </body></html>`);

                const result = await page.locator('.dashboard-summary-panel').evaluate(panel => {
                    const row = panel.querySelector('.status-row');
                    const label = row.querySelector('span');
                    const kicker = panel.querySelector('.admin-kicker');
                    return {
                        padding: Number.parseFloat(getComputedStyle(panel).paddingTop),
                        rowFontSize: Number.parseFloat(getComputedStyle(label).fontSize),
                        kickerFontSize: Number.parseFloat(getComputedStyle(kicker).fontSize),
                        rowMargin: Number.parseFloat(getComputedStyle(row).marginTop),
                        documentWidth: document.documentElement.scrollWidth,
                        viewportWidth: document.documentElement.clientWidth,
                    };
                });

                assert.ok(result.padding <= 18, `summary panels should be compact at ${width}px in ${mode} mode`);
                assert.ok(result.rowFontSize >= 14, `summary labels should be readable at ${width}px in ${mode} mode`);
                assert.ok(result.kickerFontSize >= 11, `summary section labels should be readable at ${width}px in ${mode} mode`);
                assert.ok(result.rowMargin <= 6, `summary rows should not create excess vertical space at ${width}px in ${mode} mode`);
                assert.ok(result.documentWidth <= result.viewportWidth, `summary panels should not overflow at ${width}px in ${mode} mode`);

                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('dashboard summaries align as three consistent desktop columns and reflow cleanly on narrow screens', async () => {
    const browser = await chromium.launch();
    try {
        for (const width of [1440, 1024, 390]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            await page.setContent(`<html><head><style>${adminCss}</style></head><body>
                <section class="dashboard-summary-grid">
                    <article class="panel dashboard-summary-panel"><h2>Shift alerts</h2><div class="status-row"><span>Arrivals ready</span><b>2</b></div><div class="status-row"><span>Guests due</span><b>1</b></div><div class="status-row"><span>Requests waiting for review</span><b>6</b></div><div class="status-row"><span>Cleaning or maintenance scheduled</span><b>3</b></div></article>
                    <article class="panel dashboard-summary-panel"><h2>Live inventory</h2><div class="status-row"><span>Booked or held</span><b>5</b></div><div class="status-row"><span>Cleaning / maintenance</span><b>3</b></div><div class="status-row"><span>Guests checked in</span><b>2</b></div><div class="status-row"><span>Guests checked out today</span><b>4</b></div><p class="dashboard-summary-note">Completed checkouts and still-open shift alerts are separate counts.</p></article>
                    <article class="panel dashboard-summary-panel"><h2>Payment and cancellation</h2><div class="status-row"><span>Payment records pending</span><b>20</b></div><div class="status-row"><span>Cancelled reservations</span><b>39</b></div><p>Payments appear after verification.</p></article>
                </section>
            </body></html>`);

            const result = await page.locator('.dashboard-summary-grid').evaluate(grid => {
                const panels = [...grid.querySelectorAll('.dashboard-summary-panel')];
                const rectangles = panels.map(panel => panel.getBoundingClientRect());
                const columns = getComputedStyle(grid).gridTemplateColumns.split(' ').length;
                return {
                    columns,
                    heights: rectangles.map(rect => rect.height),
                    widths: rectangles.map(rect => rect.width),
                    documentWidth: document.documentElement.scrollWidth,
                    viewportWidth: document.documentElement.clientWidth,
                };
            });

            if (width >= 1200) {
                assert.equal(result.columns, 3, 'desktop dashboard summaries should share one aligned row');
                assert.ok(Math.max(...result.heights) - Math.min(...result.heights) <= 1, 'desktop summary panels should have equal heights');
                assert.ok(result.widths[2] < result.widths[0], 'payment panel should be narrower than operational summaries');
            } else if (width >= 700) {
                assert.equal(result.columns, 2, 'tablet summaries should use two columns');
            } else {
                assert.equal(result.columns, 1, 'phone summaries should stack vertically');
            }

            assert.ok(result.documentWidth <= result.viewportWidth, `summary grid should not overflow at ${width}px`);
            await page.close();
        }
    } finally {
        await browser.close();
    }
});
