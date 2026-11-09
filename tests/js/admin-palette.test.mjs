import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const stylesheet = await readFile(new URL('../../public/css/admin.css', import.meta.url), 'utf8');

for (const theme of ['light', 'dark']) {
    test(`admin palette uses Carolina swatches with readable surfaces in ${theme} mode`, async () => {
        const browser = await chromium.launch();
        try {
            const page = await browser.newPage();
            await page.setContent(`<html class="${theme === 'dark' ? 'dark-mode' : ''}"><head><style>${stylesheet}</style></head><body>
                <div class="admin-shell">
                    <aside class="admin-sidebar"><a class="admin-brand">Carolina</a><nav class="admin-nav"><a class="active">Bookings</a></nav></aside>
                    <main class="admin-main"><section class="panel"><h2>Bookings</h2><table><thead><tr><th>Guest</th></tr></thead></table><button class="button">Confirm</button><input value="Guest" /></section></main>
                </div>
            </body></html>`);

            for (const width of [1280, 390]) {
                await page.setViewportSize({ width, height: 900 });
                const colors = await page.evaluate(() => {
                    const style = selector => getComputedStyle(document.querySelector(selector));
                    return {
                        canvas: style('body').backgroundColor,
                        canvasImage: style('body').backgroundImage,
                        rail: style('.admin-sidebar').backgroundColor,
                        panel: style('.panel').backgroundColor,
                        ink: style('.panel h2').color,
                        tableHeader: style('th').backgroundColor,
                        accent: getComputedStyle(document.documentElement).getPropertyValue('--orange').trim().toLowerCase(),
                    };
                });

                if (theme === 'light') {
                    assert.equal(colors.canvas, 'rgb(231, 236, 239)');
                    assert.equal(colors.rail, 'rgb(57, 57, 58)');
                    assert.equal(colors.panel, 'rgb(255, 255, 255)');
                    assert.equal(colors.ink, 'rgb(57, 57, 58)');
                    assert.equal(colors.tableHeader, 'rgb(231, 236, 239)');
                } else {
                    assert.equal(colors.canvas, 'rgb(57, 57, 58)');
                    assert.equal(colors.rail, 'rgb(57, 57, 58)');
                    assert.equal(colors.panel, 'rgb(109, 103, 110)');
                    assert.equal(colors.ink, 'rgb(231, 236, 239)');
                    assert.equal(colors.tableHeader, 'rgb(57, 57, 58)');
                }
                assert.equal(colors.canvasImage, 'none', `admin backgrounds should use the selected palette at ${width}px`);
                assert.equal(colors.accent, '#c97900');
            }
            await page.close();
        } finally {
            await browser.close();
        }
    });
}
