import assert from 'node:assert/strict';
import test from 'node:test';
import { after, before } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const baseStyles = await readFile(new URL('../../public/css/site.css', import.meta.url), 'utf8');
const roomsStyles = await readFile(new URL('../../public/css/rooms-premium.css', import.meta.url), 'utf8');
let browser;

before(async () => { browser = await chromium.launch({ headless: true }); });
after(async () => { await browser?.close(); });

async function render(viewport) {
    const page = await browser.newPage({ viewport });
    await page.setContent(`<style>${baseStyles}\n${roomsStyles}</style>
        <section class="section compact rooms-results"><form class="filter-bar rooms-search">
            <button class="rooms-date-trigger"><span>Check-in date</span><b>Choose date</b></button>
            <div class="rooms-choice"><span>Stay length</span><button>1 day stay</button></div>
            <div class="rooms-time-field"><span>Check-in time</span><button>12:00 PM</button></div>
            <div class="rooms-choice"><span>Guests</span><button>Any guests</button></div>
            <label class="rooms-sort-control">Sort by price<select><option>Recommended</option></select></label>
            <button class="button">Check availability</button>
        </form></section>`);
    return page;
}

test('price sorting keeps all room search controls readable on desktop and mobile', async () => {
    for (const width of [1274, 1024, 901, 768, 390]) {
        const page = await render({ width, height: 850 });
        try {
            const layout = await page.locator('.rooms-search').evaluate(form => {
                const bounds = selector => {
                    const rect = form.querySelector(selector).getBoundingClientRect();
                    return { left: rect.left, right: rect.right, width: rect.width };
                };
                return {
                    date: bounds('.rooms-date-trigger'),
                    sort: bounds('.rooms-sort-control'),
                    submit: bounds('.rooms-search > .button'),
                    form: form.getBoundingClientRect(),
                    items: [...form.children].filter(element => element.getBoundingClientRect().width > 0)
                        .map(element => element.getBoundingClientRect().toJSON()),
                };
            });
            assert.ok(layout.date.width >= 145, `date field should remain readable at ${width}px`);
            assert.ok(layout.sort.width >= 170, `price sort should remain readable at ${width}px`);
            assert.ok(layout.submit.left >= layout.form.left && layout.submit.right <= layout.form.right,
                `submit button should fit inside the search bar at ${width}px`);
            for (let index = 0; index < layout.items.length; index++) {
                for (let other = index + 1; other < layout.items.length; other++) {
                    const first = layout.items[index], second = layout.items[other];
                    const overlaps = first.left < second.right && first.right > second.left
                        && first.top < second.bottom && first.bottom > second.top;
                    assert.equal(overlaps, false, `search controls should not overlap at ${width}px`);
                }
            }
        } finally {
            await page.close();
        }
    }
});

test('price sort control feels refined on hover and gives clear keyboard focus', async () => {
    for (const darkMode of [false, true]) {
        const page = await render({ width: 1274, height: 850 });
        try {
            if (darkMode) await page.evaluate(() => document.documentElement.classList.add('dark-mode'));
            const control = page.locator('.rooms-sort-control');
            const initialBorder = await control.evaluate(element => getComputedStyle(element).borderColor);
            await control.hover();
            await page.waitForTimeout(220);
            const hoverStyle = await control.evaluate(element => ({
                border: getComputedStyle(element).borderColor,
                shadow: getComputedStyle(element).boxShadow,
                translation: new DOMMatrixReadOnly(getComputedStyle(element).transform).m42,
            }));
            assert.notEqual(hoverStyle.border, initialBorder, 'hover should reveal the warm accent border');
            assert.notEqual(hoverStyle.shadow, 'none', 'hover should lift the control from the search surface');
            assert.ok(hoverStyle.translation < 0, 'hover should give a subtle lift');

            await control.locator('select').focus();
            await page.waitForTimeout(220);
            const focusShadow = await control.evaluate(element => getComputedStyle(element).boxShadow);
            assert.notEqual(focusShadow, 'none', 'keyboard focus should remain clearly visible');
        } finally {
            await page.close();
        }
    }
});

test('premium room search hover effects respect reduced-motion preferences', async () => {
    const page = await render({ width: 1274, height: 850 });
    try {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.locator('.rooms-sort-control').hover();
        await page.waitForTimeout(220);
        const offset = await page.locator('.rooms-sort-control').evaluate(element => new DOMMatrixReadOnly(getComputedStyle(element).transform).m42);
        assert.equal(offset, 0, 'reduced-motion users should not see the hover lift');
    } finally {
        await page.close();
    }
});

async function renderDarkSortMenu() {
    const page = await browser.newPage({ viewport: { width: 390, height: 700 } });
    await page.setContent(`<html class="dark-mode"><style>${baseStyles}\n${roomsStyles}</style>
        <form class="rooms-search"><label class="rooms-sort-control">Sort by price
            <select size="3"><option>Recommended</option><option>Lowest first</option><option>Highest first</option></select>
        </label></form></html>`);
    return page;
}

test('dark price sort options use a readable dark surface and light text', async () => {
    const page = await renderDarkSortMenu();
    try {
        const optionColors = await page.locator('.rooms-sort-control option').evaluateAll(options => options.map(option => ({
            color: getComputedStyle(option).color,
            background: getComputedStyle(option).backgroundColor,
        })));
        for (const option of optionColors) {
            assert.equal(option.color, 'rgb(244, 233, 220)');
            assert.equal(option.background, 'rgb(42, 33, 27)');
        }
    } finally {
        await page.close();
    }
});

test('price sort labels fit inside the compact dropdown without clipping', async () => {
    const page = await renderDarkSortMenu();
    try {
        const select = await page.locator('.rooms-sort-control select').evaluate(select => ({
            width: select.clientWidth,
            optionWidths: [...select.options].map(option => {
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                context.font = getComputedStyle(select).font;
                return context.measureText(option.textContent).width;
            }),
        }));
        assert.ok(select.optionWidths.every(width => width <= select.width - 30), 'every choice should fit before the dropdown arrow');
    } finally {
        await page.close();
    }
});
