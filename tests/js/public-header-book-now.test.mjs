import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const publicStyles = [
    await readFile(new URL('../../public/css/responsive.css', import.meta.url), 'utf8'),
    await readFile(new URL('../../public/css/navigation-glass.css', import.meta.url), 'utf8'),
].join('\n');
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

test('Book now uses a translucent glass style while Sign in keeps its solid gold style', async () => {
    const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
    try {
        await page.setContent(`<!doctype html><html><head><style>${publicStyles}</style></head><body>
            <header class="nav"><a class="brand" href="#">Carolina</a><nav>
                <a class="nav-button nav-book-now" href="#rooms">Book now</a>
                <a class="nav-button" href="#sign-in">Sign in</a>
            </nav></header>
        </body></html>`);

        const styles = await page.evaluate(() => Object.fromEntries(
            ['.nav-book-now', 'a[href="#sign-in"]'].map(selector => {
                const style = getComputedStyle(document.querySelector(selector));
                return [selector, {
                    backgroundImage: style.backgroundImage,
                    backdropFilter: style.backdropFilter,
                    borderColor: style.borderTopColor,
                    color: style.color,
                }];
            }),
        ));

        assert.match(styles['.nav-book-now'].backgroundImage, /rgba\(30,\s*21,\s*15,\s*0\.62\)/);
        assert.match(styles['.nav-book-now'].backdropFilter, /blur\(10px\)/);
        assert.equal(styles['.nav-book-now'].color, 'rgb(255, 246, 233)');
        assert.notEqual(styles['.nav-book-now'].backgroundImage, styles['a[href="#sign-in"]'].backgroundImage);
        assert.equal(styles['a[href="#sign-in"]'].backdropFilter, 'none');
    } finally {
        await page.close();
    }
});
