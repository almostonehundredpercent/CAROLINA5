import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const stylesheet = await readFile(new URL('../../public/css/admin.css', import.meta.url), 'utf8');
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

function luminance(color) {
    const channels = color.match(/[\d.]+/g)?.slice(0, 3).map(Number);
    assert.equal(channels?.length, 3, `Expected an RGB color, received ${color}`);
    const linear = channels.map(value => {
        const channel = value / 255;
        return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    });
    return linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722;
}

function contrast(foreground, background) {
    const values = [luminance(foreground), luminance(background)].sort((a, b) => b - a);
    return (values[0] + 0.05) / (values[1] + 0.05);
}

async function render(dark, width) {
    const page = await browser.newPage({ viewport: { width, height: 900 } });
    await page.setContent(`
        <html class="${dark ? 'dark-mode' : ''}">
        <head><style>${stylesheet}</style></head>
        <body><main class="admin-main">
            <header class="housekeeping-header"><div class="housekeeping-counts"><span><b>5</b> ready</span></div></header>
            <section class="room-board"><article class="housekeeping-card available">
                <div class="housekeeping-card-top"><span class="housekeeping-capacity">Up to 2 guests</span></div>
                <h2>Room 2</h2><p class="housekeeping-room-type">Air-conditioned room</p>
                <div class="housekeeping-state"><b>Ready for the next guest</b><span>No cleaning or maintenance task is scheduled.</span></div>
                <button class="housekeeping-button" type="button">Start cleaning</button>
                <details class="housekeeping-plan" open><summary>Plan a one-day task</summary>
                    <form class="housekeeping-form">
                        <div class="housekeeping-choice"><label><input type="radio" checked> Cleaning</label><label><input type="radio"> Maintenance</label></div>
                        <label>Work date<span class="housekeeping-date-picker"><button class="housekeeping-date-trigger" type="button">Wed, Oct 7</button>
                            <div class="housekeeping-calendar"><div class="calendar-head"><button type="button">‹</button><b>October 2026</b><button type="button">›</button></div>
                                <div class="calendar-week"><span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span></div>
                                <div class="calendar-days"><button type="button">6</button><button class="selected" type="button">7</button></div>
                                <button class="calendar-today" type="button">Today</button>
                            </div></span></label>
                        <span class="housekeeping-time-menu"><button class="selected" type="button">8 AM</button></span>
                    </form>
                </details>
            </article></section>
        </main></body></html>`);
    return page;
}

async function colors(page, foreground, background) {
    return page.evaluate(([textSelector, surfaceSelector]) => {
        const text = getComputedStyle(document.querySelector(textSelector));
        const surface = getComputedStyle(document.querySelector(surfaceSelector));
        return { foreground: text.color, background: surface.backgroundColor, size: Number.parseFloat(text.fontSize) };
    }, [foreground, background]);
}

for (const [width, dark] of [[1280, false], [1280, true], [390, false], [390, true]]) {
    test(`${dark ? 'dark' : 'light'} housekeeping planner keeps all small text readable at ${width}px`, async () => {
        const page = await render(dark, width);
        try {
            const samples = [
                ['status count', '.housekeeping-counts span', '.housekeeping-counts span'],
                ['open task heading', '.housekeeping-plan[open] summary', '.housekeeping-plan[open]'],
                ['chosen task', '.housekeeping-choice label:has(input:checked)', '.housekeeping-choice label:has(input:checked)'],
                ['calendar month', '.calendar-head b', '.housekeeping-calendar'],
                ['calendar weekday', '.calendar-week', '.housekeeping-calendar'],
                ['calendar date', '.calendar-days button:not(.selected)', '.housekeeping-calendar'],
                ['selected calendar date', '.calendar-days button.selected', '.calendar-days button.selected'],
                ['today link', '.calendar-today', '.housekeeping-calendar'],
                ['selected hour', '.housekeeping-time-menu button.selected', '.housekeeping-time-menu button.selected'],
            ];
            for (const [name, textSelector, surfaceSelector] of samples) {
                const { foreground, background } = await colors(page, textSelector, surfaceSelector);
                assert.ok(contrast(foreground, background) >= 4.5,
                    `${name} contrast ${contrast(foreground, background).toFixed(2)}:1 is below 4.5:1 in ${dark ? 'dark' : 'light'} mode`);
            }
            const date = await colors(page, '.calendar-days button:not(.selected)', '.housekeeping-calendar');
            assert.ok(date.size >= 14, `calendar dates are only ${date.size}px`);
        } finally {
            await page.close();
        }
    });

    test(`${dark ? 'dark' : 'light'} housekeeping card labels and primary action are easy to read at ${width}px`, async () => {
        const page = await render(dark, width);
        try {
            for (const [name, selector, minimum] of [
                ['room capacity', '.housekeeping-capacity', 12],
                ['room type', '.housekeeping-room-type', 14],
                ['room state', '.housekeeping-state b', 14],
                ['state detail', '.housekeeping-state span', 13],
                ['primary action', '.housekeeping-button', 14],
            ]) {
                const size = await page.locator(selector).evaluate(element => Number.parseFloat(getComputedStyle(element).fontSize));
                assert.ok(size >= minimum, `${name} is only ${size}px in ${dark ? 'dark' : 'light'} mode`);
            }
            const action = await page.locator('.housekeeping-button').evaluate(element => {
                const style = getComputedStyle(element);
                return { text: style.color, gradient: style.backgroundImage };
            });
            const stops = action.gradient.match(/rgba?\([^)]+\)/g) ?? [];
            assert.ok(stops.length >= 2, 'primary action should keep its copper gradient');
            for (const stop of stops) {
                assert.ok(contrast(action.text, stop) >= 4.5,
                    `primary action contrast ${contrast(action.text, stop).toFixed(2)}:1 is below 4.5:1 in ${dark ? 'dark' : 'light'} mode`);
            }
        } finally {
            await page.close();
        }
    });
}
