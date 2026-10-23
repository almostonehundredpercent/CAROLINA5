import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const stylesheet = await readFile(new URL('../../public/css/home-premium.css', import.meta.url), 'utf8');
const baseStylesheet = await readFile(new URL('../../public/css/site.css', import.meta.url), 'utf8');
const hero = '<section class="home-hero"></section>';

test('the hero loads one correctly sized image at mobile and desktop widths', async () => {
    const browser = await chromium.launch();
    try {
        for (const [width, expectedWidth] of [[390, 'w=900'], [1280, 'w=1800']]) {
            const page = await browser.newPage({ viewport: { width, height: 844 } });
            const heroRequests = [];
            page.on('request', request => {
                if (request.url().includes('photo-1601918774946-25832a4be0d6')) heroRequests.push(request.url());
            });
            await page.route('https://images.unsplash.com/**', route => route.fulfill({
                status: 200,
                contentType: 'image/svg+xml',
                body: '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>',
            }));
            await page.setContent(`<head><style>${baseStylesheet}</style><style>${stylesheet}</style></head><body>${hero}</body>`);
            const background = await page.locator('.home-hero').evaluate(element => getComputedStyle(element).backgroundImage);
            assert.ok(background.includes(expectedWidth), `${width}px should request ${expectedWidth}: ${background}`);
            assert.equal(heroRequests.length, 1, `${width}px should download only its selected hero image: ${heroRequests}`);
            assert.ok(heroRequests[0].includes(expectedWidth));
            await page.close();
        }
    } finally {
        await browser.close();
    }
});
