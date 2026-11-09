import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const css = await Promise.all([
    'site.css',
    'palette.css',
    'home-premium.css',
    'promo-campaign.css',
    'button-refinement.css',
].map(file => readFile(new URL(`../../public/css/${file}`, import.meta.url), 'utf8')));

let browser;
before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

test('home promo is compact and readable on mobile without changing desktop sizing', async () => {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    try {
        await page.setContent(`<html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css.join('\n')}</style></head><body>
            <section class="ber-promo ber-promo-home" aria-labelledby="promo-title"><div class="ber-promo-layout"><div class="ber-promo-copy">
                <span class="ber-promo-kicker">Ber-Months Promo · Code CAROLINA</span>
                <h2 id="promo-title">Free breakfast is back.</h2>
                <p>Enjoy a simple, comfortable <strong>22-hour Fan Room Solo stay for only ₱450</strong>—then use code <strong>CAROLINA</strong> for 10% off.</p>
                <div class="ber-promo-highlights"><span>Free breakfast</span><span>Smart TV + WiFi</span><span>Pet-friendly</span><span>Children below 7 stay free</span></div>
            </div><a class="button" href="#offer">View the offer <span aria-hidden="true">→</span></a></div></section>
        </body></html>`);

        for (const width of [390, 320]) {
            await page.setViewportSize({ width, height: 844 });
            const mobile = await page.locator('.ber-promo-home').evaluate(element => {
                const button = element.querySelector('.button');
                return {
                    height: Math.round(element.getBoundingClientRect().height),
                    padding: getComputedStyle(element).padding,
                    buttonHeight: Math.round(button.getBoundingClientRect().height),
                    cardWidth: Math.round(element.getBoundingClientRect().width),
                    cardRight: Math.round(element.getBoundingClientRect().right),
                    pageWidth: document.documentElement.scrollWidth,
                    buttonBottom: Math.round(button.getBoundingClientRect().bottom - element.getBoundingClientRect().top),
                };
            });
            assert.equal(mobile.padding, '16px', `promo should have compact 16px padding at ${width}px`);
            assert.ok(mobile.height < 290, `promo should be shorter on mobile; got ${mobile.height}px at ${width}px`);
            assert.ok(mobile.buttonHeight >= 48, `offer button must remain easy to tap at ${width}px`);
            assert.ok(mobile.cardWidth < width, `promo should keep its side margins at ${width}px`);
            assert.ok(mobile.cardRight <= width, `promo should fit inside the viewport at ${width}px`);
            assert.equal(mobile.pageWidth, width, `promo should not cause horizontal page overflow at ${width}px`);
            assert.ok(mobile.buttonBottom < mobile.height + 1, 'offer button should remain inside the promo card');
        }

        await page.setViewportSize({ width: 1280, height: 900 });
        const desktop = await page.locator('.ber-promo-home').evaluate(element => ({
            padding: getComputedStyle(element).padding,
            height: Math.round(element.getBoundingClientRect().height),
            buttonWidth: Math.round(element.querySelector('.button').getBoundingClientRect().width),
        }));
        assert.equal(desktop.padding, '30px 34px', 'desktop promo padding should remain unchanged');
        assert.ok(desktop.buttonWidth < 300, 'desktop offer button should stay content-sized');
    } finally {
        await page.close();
    }
});
