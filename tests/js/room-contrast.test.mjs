import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const stylesheet = await readFile(new URL('../../public/css/admin.css', import.meta.url), 'utf8');
const roomsTemplate = await readFile(new URL('../../resources/views/admin/rooms.blade.php', import.meta.url), 'utf8');
const roomStyles = roomsTemplate.match(/<style>([\s\S]*?)<\/style>/)?.[1] ?? '';
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

function luminance(color) {
    const hex = color.match(/^#([\da-f]{3}|[\da-f]{6})$/i)?.[1];
    const channels = hex
        ? (hex.length === 3 ? [...hex].map(channel => Number.parseInt(channel + channel, 16)) : hex.match(/.{2}/g).map(channel => Number.parseInt(channel, 16)))
        : color.match(/[\d.]+/g)?.slice(0, 3).map(Number);
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

async function render(dark) {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.setContent(`<!doctype html><html class="${dark ? 'dark-mode' : ''}"><head><style>${stylesheet}</style><style>${roomStyles}</style></head><body>
        <main class="admin-main">
            <header class="room-operations-header"><div><p class="admin-kicker">PROPERTY MANAGEMENT</p><h1>Rooms</h1><p class="admin-subtitle">A live view of every room, stay, and task.</p></div></header>
            <section class="room-summary">
                <article class="room-summary-card available"><small>Available</small><strong>12</strong></article>
                <article class="room-summary-card reserved"><small>Arriving soon</small><strong>2</strong></article>
                <article class="room-summary-card not-available"><small>Not available</small><strong>4</strong></article>
            </section>
            <div class="room-board-legend"><span><i class="available"></i>Available</span><span><i class="arriving"></i>Arriving soon</span><span><i class="not-available"></i>Not available</span></div>
            <div class="room-page-indicator">Rooms 1–6 of 18 · Group 1 of 3</div>
            <article class="room-card"><div class="room-card-main"><div class="room-card-top"><div><h2 class="room-card-title">Room 1</h2><p class="room-card-meta">Fan room · Sleeps 1</p><span class="room-card-rate">₱450 per 22-hour stay</span></div><span class="room-status occupied">Occupied</span></div>
                <div class="room-card-schedule"><div class="schedule-item"><small>Guest in house</small><b>Guest name</b><span>Oct 24, 6 AM – Oct 26, 6 AM</span></div><div class="schedule-item"><small>Operations</small><b>No cleaning or maintenance planned</b></div></div>
                <div class="room-card-actions"><a class="room-action-link" href="#">Edit details</a><button class="room-archive-button" type="button">Archive</button></div>
            </div></article>
        </main>
    </body></html>`);
    return page;
}

async function assertReadable(page, samples, background) {
    for (const [name, selector] of samples) {
        const elements = page.locator(selector);
        for (let index = 0; index < await elements.count(); index++) {
            const foreground = await elements.nth(index).evaluate(element => getComputedStyle(element).color);
            const ratio = contrast(foreground, background);
            assert.ok(ratio >= 4.5, `${name} contrast ${ratio.toFixed(2)}:1 is below 4.5:1`);
        }
    }
}

test('dark room operations text remains legible against Carolina dark surfaces', async () => {
    const page = await render(true);
    try {
        await assertReadable(page, [
            ['section label', '.room-operations-header .admin-kicker'],
            ['page description', '.room-operations-header .admin-subtitle'],
            ['summary labels', '.room-summary-card small'],
            ['room type', '.room-card-meta'],
            ['room rate', '.room-card-rate'],
            ['availability legend', '.room-board-legend'],
            ['room pagination', '.room-page-indicator'],
            ['available status', '.room-summary-card.available strong'],
            ['arriving status', '.room-summary-card.reserved strong'],
            ['unavailable status', '.room-summary-card.not-available strong'],
            ['occupied status', '.room-status.occupied'],
        ], '#3a281e');
        await assertReadable(page, [
            ['room action', '.room-action-link'],
            ['archive action', '.room-archive-button'],
        ], '#ffffff');
    } finally {
        await page.close();
    }
});

test('light room status colors remain readable against light cards', async () => {
    const page = await render(false);
    try {
        await assertReadable(page, [
            ['available status', '.room-summary-card.available strong'],
            ['arriving status', '.room-summary-card.reserved strong'],
            ['unavailable status', '.room-summary-card.not-available strong'],
            ['occupied status', '.room-status.occupied'],
        ], '#fffdf9');
    } finally {
        await page.close();
    }
});
