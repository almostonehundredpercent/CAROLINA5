import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const publicStyles = [
    await readFile(new URL('../../public/css/responsive.css', import.meta.url), 'utf8'),
    await readFile(new URL('../../public/css/navigation-glass.css', import.meta.url), 'utf8'),
    await readFile(new URL('../../public/css/button-refinement.css', import.meta.url), 'utf8'),
].join('\n');
const appLayout = await readFile(new URL('../../resources/views/layouts/app.blade.php', import.meta.url), 'utf8');
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

test('Book now uses a quiet graphite glass style while Sign in keeps its solid gold style', async () => {
    const headerCtas = appLayout.match(/class="nav-button nav-book-now"[\s\S]*?<\/a>/g) ?? [];
    assert.equal(headerCtas.length, 2, 'guest and signed-in headers should both retain Book now');
    assert.ok(headerCtas.every(cta => /<svg viewBox="0 0 20 20"[\s\S]*?<\/svg>/.test(cta)), 'both header CTAs should include the simple arrow detail');

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
                    backgroundColor: style.backgroundColor,
                    backdropFilter: style.backdropFilter,
                    borderColor: style.borderTopColor,
                    color: style.color,
                }];
            }),
        ));

        assert.equal(styles['.nav-book-now'].backgroundImage, 'none');
        assert.equal(styles['.nav-book-now'].backgroundColor, 'rgba(5, 5, 5, 0.2)');
        assert.match(styles['.nav-book-now'].backdropFilter, /blur\(10px\)/);
        assert.equal(styles['.nav-book-now'].color, 'rgb(231, 236, 239)');
        assert.notEqual(styles['.nav-book-now'].backgroundImage, styles['a[href="#sign-in"]'].backgroundImage);
        assert.equal(styles['a[href="#sign-in"]'].backdropFilter, 'none');
    } finally {
        await page.close();
    }
});
