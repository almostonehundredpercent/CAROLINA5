import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const stylesheet = await readFile(new URL('../../public/css/admin.css', import.meta.url), 'utf8');
const template = await readFile(new URL('../../resources/views/admin/staff.blade.php', import.meta.url), 'utf8');
const pickerScript = await readFile(new URL('../../public/js/staff-role-picker.js', import.meta.url), 'utf8').catch(() => '');
const inlineStyles = [...template.matchAll(/<style>([\s\S]*?)<\/style>/g)].map(([, styles]) => styles).join('\n');
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

test('staff page ships an enhanced role picker while keeping its form select', () => {
    assert.match(template, /data-staff-role-picker/);
    assert.match(template, /data-staff-role-trigger/);
    assert.match(template, /<select[^>]+name="staff_role"/);
    assert.match(template, /staff-role-picker\.js/);
    assert.match(pickerScript, /aria-expanded/);
});

for (const [width, dark] of [[320, true], [320, false], [390, true], [390, false], [1280, true], [1280, false]]) {
    test(`${dark ? 'dark' : 'light'} role picker opens in the page flow and updates the form at ${width}px`, async () => {
        const page = await browser.newPage({ viewport: { width, height: 900 } });
        try {
            await page.setContent(`<html class="${dark ? 'dark-mode' : ''}"><head><style>${stylesheet}\n${inlineStyles}</style></head><body>
                <main class="admin-main"><section class="panel"><div class="admin-table-wrap"><table class="staff-access-table"><thead><tr><th>Staff</th><th>Email</th><th>Role</th><th>Save</th></tr></thead><tbody>
                    <tr><td data-label="Staff member"><b>Team member</b></td><td data-label="Email">staff@example.test</td>
                    <td data-label="Role"><form id="staff-form" method="POST"><div class="staff-role-picker" data-staff-role-picker>
                        <select name="staff_role" aria-label="Role for Team member"><option value="front_desk">Front desk</option><option value="housekeeping">Housekeeping</option><option value="viewer">Viewer</option><option value="guest" selected>Guest</option></select>
                        <button type="button" class="staff-role-trigger" data-staff-role-trigger aria-haspopup="listbox" aria-expanded="false"><span data-staff-role-label>Guest</span><span aria-hidden="true">⌄</span></button>
                        <div class="staff-role-options" role="listbox" data-staff-role-options hidden><button type="button" class="staff-role-option" role="option" data-value="front_desk">Front desk</button><button type="button" class="staff-role-option" role="option" data-value="housekeeping">Housekeeping</button><button type="button" class="staff-role-option" role="option" data-value="viewer">Viewer</button><button type="button" class="staff-role-option" role="option" data-value="guest" aria-selected="true">Guest</button></div>
                    </div></form></td><td data-label="Save"><button class="staff-role-save" type="submit" form="staff-form">Save</button></td></tr>
                    <tr><td data-label="Staff"><b>Next member</b></td><td data-label="Email">next@example.test</td><td data-label="Role">Viewer</td><td data-label="Save">—</td></tr>
                </tbody></table></div></section></main></body></html>`);
            await page.evaluate(pickerScript);

            const trigger = page.locator('[data-staff-role-trigger]').first();
            await trigger.click();
            const menu = page.locator('[role="listbox"]');
            assert.equal(await menu.isVisible(), true);
            assert.equal(await trigger.getAttribute('aria-expanded'), 'true');
            const positions = await page.evaluate(() => ({
                menuBottom: document.querySelector('[role="listbox"]').getBoundingClientRect().bottom,
                nextRowTop: document.querySelectorAll('.staff-access-table tbody tr')[1].getBoundingClientRect().top,
                nativeHidden: document.querySelector('select[name="staff_role"]').getBoundingClientRect().width <= 1,
                pageOverflow: document.documentElement.scrollWidth > innerWidth,
                roleTargetHeight: document.querySelector('[data-staff-role-trigger]').getBoundingClientRect().height,
            }));
            assert.ok(positions.menuBottom <= positions.nextRowTop, 'open menu should push the next row down rather than overlap it');
            assert.ok(!positions.pageOverflow, 'staff page should fit the viewport');
            assert.ok(positions.nativeHidden, 'the real select should remain in the form but be visually hidden after enhancement');
            assert.ok(positions.roleTargetHeight >= 44, 'role picker should be an easy-to-press target');

            await menu.getByRole('option', { name: 'Housekeeping' }).click();
            assert.equal(await page.locator('select[name="staff_role"]').inputValue(), 'housekeeping');
            assert.equal(await page.locator('[data-staff-role-label]').textContent(), 'Housekeeping');
            assert.equal(await menu.isVisible(), false);

            await trigger.press('ArrowDown');
            assert.equal(await menu.isVisible(), true, 'keyboard should open the role menu');
            assert.equal(await page.locator('[role="option"][aria-selected="true"]').evaluate(option => option === document.activeElement), true,
                'keyboard opening should focus the currently selected role');
            await page.locator('[role="option"][aria-selected="true"]').press('ArrowDown');
            await page.locator('[role="option"]:focus').press('Enter');
            assert.equal(await page.locator('select[name="staff_role"]').inputValue(), 'viewer');
        } finally {
            await page.close();
        }
    });
}
