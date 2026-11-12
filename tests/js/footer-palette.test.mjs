import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const stylesheets = await Promise.all(['site.css', 'palette.css', 'responsive.css', 'footer-apple.css'].map(file =>
    readFile(new URL(`../../public/css/${file}`, import.meta.url), 'utf8')));
const paletteColors = ['FB9E2D', 'FCAE50', 'FCBE73', 'FDCF96', 'FEDFB9', 'FEEFDC'];
const luminance = (rgb) => {
    const [r, g, b] = rgb.match(/\d+/g).map(Number).map(value => {
        const channel = value / 255;
        return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

test('footer uses the supplied warm gradient with readable text in both themes', async () => {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    try {
        await page.setContent(`<!doctype html><html><head><style>${stylesheets.join('\n')}</style></head><body>
            <footer class="site-footer"><div class="footer-content"><section class="footer-column"><h2>Explore</h2><a href="#rooms">Rooms</a></section></div></footer>
        </body></html>`);

        for (const theme of ['light', 'dark']) {
            await page.locator('html').evaluate((element, value) => element.classList.toggle('dark-mode', value === 'dark'), theme);
            const footer = await page.locator('.site-footer').evaluate(element => ({
                background: getComputedStyle(element).backgroundImage,
                text: getComputedStyle(element.querySelector('a')).color,
                overflow: document.documentElement.scrollWidth > innerWidth,
            }));
            assert.ok(footer.background.includes('linear-gradient'), `${theme} footer should use the palette gradient`);
            for (const color of ['251, 158, 45', '252, 190, 115', '254, 239, 220']) {
                assert.ok(footer.background.includes(color), `${theme} footer gradient should include ${color}`);
            }
            assert.equal(footer.text, 'rgb(57, 57, 58)', `${theme} footer links should use the dark readable text color`);
            const textLuminance = luminance(footer.text);
            for (const color of paletteColors) {
                const rgb = `rgb(${parseInt(color.slice(0, 2), 16)}, ${parseInt(color.slice(2, 4), 16)}, ${parseInt(color.slice(4, 6), 16)})`;
                const contrast = (luminance(rgb) + 0.05) / (textLuminance + 0.05);
                assert.ok(contrast >= 4.5, `${theme} footer text contrast against #${color} should be at least 4.5:1, got ${contrast.toFixed(2)}:1`);
            }
            assert.equal(footer.overflow, false, `${theme} footer should not overflow on mobile`);
        }
    } finally {
        await page.close();
    }
});
