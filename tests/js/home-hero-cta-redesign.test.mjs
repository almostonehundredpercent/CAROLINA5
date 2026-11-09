import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const styles = await Promise.all([
    'site.css',
    'palette.css',
    'responsive.css',
    'home-premium.css',
    'motion.css',
    'navigation-glass.css',
    'button-refinement.css',
].map(file => readFile(new URL(`../../public/css/${file}`, import.meta.url), 'utf8')));

const markup = `
    <section class="hero home-hero">
        <div class="hero-copy">
            <span class="eyebrow">CAROLINA · TABACO CITY</span>
            <h1>Stay well.<br>Spend less.</h1>
            <p>Affordable bed &amp; breakfast and boarding-house stays in Tabaco City.</p>
            <div class="home-hero-actions">
                <a class="button home-hero-primary" href="/rooms">
                    <span>Book now</span>
                    <span class="home-hero-arrow" aria-hidden="true">
                        <svg viewBox="0 0 20 20" focusable="false"><path d="M3.5 10h12m-5-5 5 5-5 5" /></svg>
                    </span>
                </a>
            </div>
        </div>
    </section>
`;

const luminance = color => {
    const [red, green, blue] = color.match(/\d+/g).slice(0, 3).map(channel => Number(channel) / 255).map(channel => (
        channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4
    ));
    return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
};

test('homepage Book now uses the selected smoky glass surface and framed arrow', async () => {
    const browser = await chromium.launch();
    try {
        for (const viewport of [{ width: 390, height: 844 }, { width: 1440, height: 900 }]) {
            const page = await browser.newPage({ viewport });
            await page.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles.join('\n')}</style>${markup}`);
            const button = page.locator('.home-hero-primary');
            const base = await button.evaluate(element => {
                const style = getComputedStyle(element);
                const rect = element.getBoundingClientRect();
                const arrow = element.querySelector('.home-hero-arrow');
                return {
                    label: element.querySelector(':scope > span:first-child')?.textContent.trim(),
                    href: element.getAttribute('href'),
                    background: style.backgroundImage,
                    backgroundColor: style.backgroundColor,
                    color: style.color,
                    backdropFilter: style.backdropFilter,
                    radius: Number.parseFloat(style.borderTopLeftRadius),
                    height: rect.height,
                    width: rect.width,
                    arrowBorderStyle: arrow ? getComputedStyle(arrow).borderTopStyle : 'none',
                    arrowBackground: arrow ? getComputedStyle(arrow).backgroundColor : 'none',
                    arrowRadius: arrow ? Number.parseFloat(getComputedStyle(arrow).borderTopLeftRadius) : 0,
                    arrowSvg: Boolean(arrow?.querySelector('svg path')),
                };
            });

            assert.equal(base.label, 'Book now');
            assert.equal(base.href, '/rooms');
            assert.equal(base.background, 'none');
            assert.equal(base.backgroundColor, 'rgba(31, 25, 19, 0.58)');
            assert.equal(base.color, 'rgb(255, 250, 242)');
            assert.match(base.backdropFilter, /blur\(11px\)/);
            const contrast = (Math.max(luminance(base.backgroundColor), luminance(base.color)) + 0.05) / (Math.min(luminance(base.backgroundColor), luminance(base.color)) + 0.05);
            assert.ok(contrast >= 4.5, `button text contrast should be at least 4.5:1, got ${contrast.toFixed(2)}:1`);
            assert.ok(base.radius >= 12 && base.radius <= 14, `glass button should retain its modest 13px corner radius, got ${base.radius}px`);
            assert.ok(base.height >= 54, `button should remain easy to tap at ${viewport.width}px`);
            assert.equal(base.arrowBorderStyle, 'solid', 'arrow should have a visible framed container');
            assert.equal(base.arrowBackground, 'rgba(255, 250, 242, 0.1)');
            assert.equal(base.arrowRadius, 10);
            assert.ok(base.arrowSvg, 'direction cue should be a crisp SVG arrow');
            if (viewport.width <= 460) assert.ok(base.width >= viewport.width * 0.72, 'phone button should retain a generous tap width');

            await button.hover();
            await page.waitForTimeout(200);
            const hoverTransform = await button.evaluate(element => getComputedStyle(element).transform);
            assert.match(hoverTransform, /matrix\(1, 0, 0, 1, 0, -1\)/, 'hover should provide only a restrained 1px lift');
            await page.close();
        }
    } finally {
        await browser.close();
    }
});

test('the redesigned CTA honors reduced-motion preferences', async () => {
    const browser = await chromium.launch();
    try {
        const context = await browser.newContext({ reducedMotion: 'reduce' });
        const page = await context.newPage();
        await page.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles.join('\n')}</style>${markup}`);
        await page.locator('.home-hero-primary').hover();
        const transform = await page.locator('.home-hero-primary').evaluate(element => getComputedStyle(element).transform);
        assert.equal(transform, 'none');
        await context.close();
    } finally {
        await browser.close();
    }
});
