import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { existsSync } from 'node:fs';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const layout = await readFile(new URL('../../resources/views/layouts/app.blade.php', import.meta.url), 'utf8');
const adminSidebar = await readFile(new URL('../../resources/views/admin/partials/sidebar.blade.php', import.meta.url), 'utf8');
const publicControl = layout.match(/<button class="theme-toggle nav-theme-toggle"[\s\S]*?<\/button>/)?.[0];
const staffControl = adminSidebar.match(/<button class="admin-theme-toggle"[\s\S]*?<\/button>/)?.[0];
const stylePath = new URL('../../public/css/theme-switch.css', import.meta.url);
const scriptPath = new URL('../../public/js/theme-switch.js', import.meta.url);
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

test('theme switches stay synchronized, persist the choice, and fit a mobile navigation row', async () => {
    assert.ok(publicControl, 'public navigation should render the switch control');
    assert.ok(staffControl, 'staff sidebar should render the switch control');
    assert.ok(existsSync(stylePath), 'shared theme switch styling should exist');
    assert.ok(existsSync(scriptPath), 'shared theme switch behavior should exist');
    const [styles, script, adminStyles] = await Promise.all([
        readFile(stylePath, 'utf8'),
        readFile(scriptPath, 'utf8'),
        readFile(new URL('../../public/css/admin.css', import.meta.url), 'utf8'),
    ]);
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });

    try {
        await page.route('https://theme-switch.test/**', route => route.fulfill({ contentType: 'text/html', body: '<!doctype html><html><body></body></html>' }));
        await page.goto('https://theme-switch.test');
        await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles}\n${adminStyles}</style></head><body>
            <div class="admin-shell"><header class="nav"><nav class="is-open">${publicControl}</nav></header>
            <aside>${staffControl}</aside>
            </div>
        </body></html>`);
        await page.addScriptTag({ content: script });

        const publicSwitch = page.getByRole('switch', { name: 'Dark mode' }).first();
        const staffSwitch = page.getByRole('switch', { name: 'Dark mode' }).nth(1);
        await publicSwitch.click();
        await assert.equal(await publicSwitch.getAttribute('aria-checked'), 'true');
        await assert.equal(await staffSwitch.getAttribute('aria-checked'), 'true');
        await assert.equal(await page.evaluate(() => localStorage.getItem('carolina-theme')), 'dark');
        await page.waitForTimeout(250);
        const darkThumbPlacement = await publicSwitch.evaluate(element => {
            const track = element.querySelector('.theme-switch-track').getBoundingClientRect();
            const thumb = element.querySelector('.theme-switch-thumb').getBoundingClientRect();
            return Math.round(track.right - thumb.right);
        });
        assert.ok(darkThumbPlacement <= 4, `dark-mode thumb should slide to the right side of the track, got ${darkThumbPlacement}px remaining`);
        await staffSwitch.click();
        await assert.equal(await publicSwitch.getAttribute('aria-checked'), 'false');

        const geometry = await publicSwitch.evaluate(element => {
            const button = element.getBoundingClientRect();
            const track = element.querySelector('.theme-switch-track').getBoundingClientRect();
            return { buttonHeight: button.height, trackWidth: track.width, trackHeight: track.height, overflow: document.documentElement.scrollWidth > innerWidth };
        });
        assert.ok(geometry.buttonHeight >= 44, `mobile switch hit area should be at least 44px, got ${geometry.buttonHeight}`);
        assert.ok(geometry.trackWidth >= 48 && geometry.trackHeight >= 26, 'switch track should remain clearly recognizable');
        assert.equal(geometry.overflow, false, 'switch should not create mobile horizontal overflow');
        const adminTrack = await page.locator('[data-admin-theme-toggle] .theme-switch-track').boundingBox();
        assert.equal(Math.round(adminTrack.width), 52, 'staff switch should use the same track width');
        assert.equal(Math.round(adminTrack.height), 30, 'staff switch should use the same track height');
        const adminControlLayout = await page.locator('[data-admin-theme-toggle]').evaluate(element => {
            const style = getComputedStyle(element);
            return { justifyContent: style.justifyContent, boxSizing: style.boxSizing, height: element.getBoundingClientRect().height };
        });
        assert.equal(adminControlLayout.justifyContent, 'space-between', 'staff label and switch should sit at opposite ends of the row');
        assert.equal(adminControlLayout.boxSizing, 'border-box', 'staff theme control height should include its padding');
        assert.ok(adminControlLayout.height <= 52, `staff theme control should stay compact, got ${adminControlLayout.height}px`);

        await page.setViewportSize({ width: 1280, height: 800 });
        await publicSwitch.focus();
        await page.keyboard.press('Space');
        await assert.equal(await publicSwitch.getAttribute('aria-checked'), 'true', 'keyboard activation should toggle dark mode');
        const desktopButton = await publicSwitch.boundingBox();
        assert.ok(desktopButton.width >= 44 && desktopButton.height >= 44, 'desktop switch should retain a comfortable hit area');
    } finally {
        await page.close();
    }
});
