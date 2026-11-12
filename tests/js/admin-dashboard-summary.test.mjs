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
