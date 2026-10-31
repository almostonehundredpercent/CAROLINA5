import assert from 'node:assert/strict';
import { test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

test('reports chart panels stay within the phone viewport while bars scroll inside their panel', async (t) => {
    let browser;
    try {
        browser = await chromium.launch();
    } catch (error) {
        t.skip(`Chromium is unavailable: ${error.message}`);
        return;
    }
    t.after(() => browser.close());

    const adminStylesheet = await readFile(new URL('../../public/css/admin.css', import.meta.url), 'utf8');
    const reportStyles = `.report-bottom-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}.report-bars{min-width:0;overflow-x:auto;overscroll-behavior-x:contain;height:175px;display:flex;align-items:end;gap:12px;padding:0 5px}.report-bars>div{display:flex;flex:1;min-width:35px;height:100%;flex-direction:column;align-items:center;justify-content:flex-end;gap:5px}.report-bars span{width:min(32px,100%);min-height:8px;background:orange}.report-bars b,.report-bars small{font-size:10px;white-space:nowrap}@media(max-width:1000px){.report-bottom-grid{grid-template-columns:1fr}}@media(max-width:700px){.report-bottom-grid{gap:12px}.report-bars{overflow-x:auto;justify-content:flex-start}.report-bars>div{min-width:52px}}`;
    const browserPage = await browser.newPage({ viewport: { width: 343, height: 844 } });
    await browserPage.setContent(`<!doctype html><html class="dark-mode"><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><div class="admin-shell"><aside class="admin-sidebar"></aside><main class="admin-main"><section class="panel report-main-card"><p class="admin-kicker">NET COLLECTED PAYMENTS</p><strong>₱58,659</strong><div class="line-graph report-line"><svg viewBox="0 0 600 220"><polyline points="0,180 600,20"/></svg></div></section><section class="report-bottom-grid"><article class="panel"><div class="panel-heading"><p class="admin-kicker">BREAKDOWN</p><h2>Collected payments by period</h2></div><div class="report-bars">${Array.from({ length: 7 }, (_, index) => `<div><span style="height:${35 + index * 12}px"></span><b>₱450</b><small>Mon</small></div>`).join('')}</div></article><article class="panel"><div class="panel-heading"><p class="admin-kicker">BOOKING DETAIL · SELECTED PERIOD</p><h2>Latest bookings</h2></div></article></section></main></div></body></html>`);
    await browserPage.addStyleTag({ content: adminStylesheet });
    await browserPage.addStyleTag({ content: reportStyles });

    for (const width of [343, 390]) {
        await browserPage.setViewportSize({ width, height: 844 });
        const layout = await browserPage.evaluate(() => {
            const page = document.documentElement;
            const grid = document.querySelector('.report-bottom-grid');
            const panel = grid.querySelector('.panel');
            const bars = panel.querySelector('.report-bars');
            const gridRect = grid.getBoundingClientRect();
            const panelRect = panel.getBoundingClientRect();
            return {
                viewport: innerWidth,
                pageWidth: page.scrollWidth,
                gridLeft: gridRect.left,
                gridRight: gridRect.right,
                panelLeft: panelRect.left,
                panelRight: panelRect.right,
                barsClientWidth: bars.clientWidth,
                barsScrollWidth: bars.scrollWidth,
                barsOverflowX: getComputedStyle(bars).overflowX,
            };
        });

        assert.ok(layout.pageWidth <= layout.viewport, `the Reports page should not scroll sideways at ${width}px: ${JSON.stringify(layout)}`);
        assert.ok(layout.panelLeft >= layout.gridLeft && layout.panelRight <= layout.gridRight, `the chart panel should fit inside the Reports column at ${width}px: ${JSON.stringify(layout)}`);
        assert.equal(layout.barsOverflowX, 'auto', 'the bars should scroll inside their own chart instead of expanding the whole page');
    }
});
