import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const styles = await Promise.all(['site.css', 'palette.css', 'responsive.css', 'button-refinement.css'].map(file =>
    readFile(new URL(`../../public/css/${file}`, import.meta.url), 'utf8')
));

test('sign-in page uses a warm neutral canvas in light mode and stays graphite in dark mode', async () => {
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage();
        await page.setContent(`<html><head><style>${styles.join('\n')}</style></head><body>
            <main class="auth-page"><form class="auth-card"><h1>Sign in to Carolina.</h1></form></main>
        </body></html>`);

        const light = await page.locator('.auth-page').evaluate(element => getComputedStyle(element).backgroundColor);
        assert.equal(light, 'rgb(244, 238, 230)', `light sign-in canvas should be warm neutral, got ${light}`);

        await page.locator('html').evaluate(element => element.classList.add('dark-mode'));
        const dark = await page.locator('.auth-page').evaluate(element => getComputedStyle(element).backgroundColor);
        assert.equal(dark, 'rgb(57, 57, 58)', `dark sign-in canvas should remain graphite, got ${dark}`);
    } finally {
        await browser.close();
    }
});

test('sign-in submit button keeps a dirty-white label in light and dark mode', async () => {
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage();
        await page.setContent(`<html><head><style>${styles.join('\n')}</style></head><body>
            <form class="auth-card"><button class="button" type="submit">Sign in</button></form>
        </body></html>`);

        const light = await page.locator('.auth-card .button').evaluate(element => getComputedStyle(element).color);
        assert.equal(light, 'rgb(245, 245, 240)', 'the light-mode sign-in label should use dirty white');

        await page.locator('html').evaluate(element => element.classList.add('dark-mode'));
        const dark = await page.locator('.auth-card .button').evaluate(element => getComputedStyle(element).color);
        assert.equal(dark, 'rgb(245, 245, 240)', 'the dark-mode sign-in label should use dirty white');
    } finally {
        await browser.close();
    }
});
