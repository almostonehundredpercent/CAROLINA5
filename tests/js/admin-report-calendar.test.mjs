import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

let calendarModule = null;
try {
    calendarModule = await import('../../public/js/admin-report-calendar.mjs');
} catch {}

test('report calendar exports the real date-grid behavior', () => {
    assert.ok(calendarModule?.buildMonthCells, 'the custom report calendar module should expose its month-grid behavior');
    assert.deepEqual(calendarModule.buildMonthCells(2026, 9), [
        null, null, null, null,
        '2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04', '2026-10-05', '2026-10-06', '2026-10-07',
        '2026-10-08', '2026-10-09', '2026-10-10', '2026-10-11', '2026-10-12', '2026-10-13', '2026-10-14',
        '2026-10-15', '2026-10-16', '2026-10-17', '2026-10-18', '2026-10-19', '2026-10-20', '2026-10-21',
        '2026-10-22', '2026-10-23', '2026-10-24', '2026-10-25', '2026-10-26', '2026-10-27', '2026-10-28',
        '2026-10-29', '2026-10-30', '2026-10-31',
    ]);
});

test('report date calendar stays usable when selecting and changing a range', async (t) => {
    if (!calendarModule?.mountReportDateCalendars) {
        t.skip('the report date calendar module has not been implemented yet');
        return;
    }

    let browser;
    try {
        browser = await chromium.launch();
    } catch (error) {
        t.skip(`Chromium is unavailable: ${error.message}`);
        return;
    }
    t.after(() => browser.close());

    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    page.setDefaultTimeout(2000);
    const script = await readFile(new URL('../../public/js/admin-report-calendar.mjs', import.meta.url), 'utf8');
    const stylesheet = await readFile(new URL('../../public/css/admin-report-calendar.css', import.meta.url), 'utf8');
    const adminStylesheet = await readFile(new URL('../../public/css/admin.css', import.meta.url), 'utf8');

    await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>.test-transformed-shell{width:420px;transform:translateZ(0)}</style></head><body><div class="test-transformed-shell"><form id="report-range-form" class="booking-filters">
        <input type="hidden" name="from" value="2026-10-01">
        <input type="hidden" name="to" value="">
        <div data-report-date-calendars>
            <label class="report-date-field">From <button type="button" class="report-date-trigger" data-date-trigger="from" aria-expanded="false"><span data-date-value>Choose date</span></button><div class="report-date-calendar" data-date-calendar="from" role="dialog" hidden></div></label>
            <label class="report-date-field report-date-field-to">To <button type="button" class="report-date-trigger" data-date-trigger="to" aria-expanded="false"><span data-date-value>Choose date</span></button><div class="report-date-calendar" data-date-calendar="to" role="dialog" hidden></div></label>
        </div>
        <button type="submit">Custom range</button>
    </form></div></body></html>`);
    await page.addStyleTag({ content: adminStylesheet });
    await page.addStyleTag({ content: stylesheet });
    await page.addScriptTag({ type: 'module', content: script });
    await page.evaluate(() => window.mountReportDateCalendars(document.querySelector('[data-report-date-calendars]')));

    await page.locator('[data-date-trigger="from"]').click();
    assert.equal(await page.getByRole('dialog', { name: 'Choose start date' }).count(), 1, 'opening the start control should show its calendar dialog');
    assert.equal(await page.getByRole('button', { name: 'Close calendar' }).count(), 1, 'the open calendar should have a visible close button');
    assert.equal(await page.evaluate(() => document.activeElement.getAttribute('data-date-value')), '2026-10-01', 'opening the calendar should move keyboard focus to the selected day');
    const dayGrid = await page.locator('[data-date-calendar="from"] .report-calendar-days').evaluate(element => {
        const grid = element.getBoundingClientRect();
        const buttons = [...element.querySelectorAll('button')].map(button => button.getBoundingClientRect());
        return { left: grid.left, right: grid.right, buttonRight: Math.max(...buttons.map(button => button.right)), viewport: innerWidth, maxButtonWidth: Math.max(...buttons.map(button => button.width)) };
    });
    assert.ok(dayGrid.right <= dayGrid.viewport, `the calendar grid should fit within the mobile viewport: ${JSON.stringify(dayGrid)}`);
    assert.ok(dayGrid.buttonRight <= dayGrid.right, `day buttons should stay within their seven-column grid rather than inherit full form width: ${JSON.stringify(dayGrid)}`);
    assert.ok(dayGrid.maxButtonWidth < 60, `day buttons should remain compact calendar cells on mobile: ${JSON.stringify(dayGrid)}`);
    const neutralDay = await page.locator('[data-date-calendar="from"] [data-date-value="2026-10-02"]').evaluate(element => {
        const style = getComputedStyle(element);
        return { backgroundColor: style.backgroundColor, backgroundImage: style.backgroundImage };
    });
    assert.equal(neutralDay.backgroundImage, 'none', `unselected calendar days should not inherit the admin form button fill: ${JSON.stringify(neutralDay)}`);
    const triggerFill = await page.locator('[data-date-trigger="from"]').evaluate(element => {
        const style = getComputedStyle(element);
        return { backgroundColor: style.backgroundColor, backgroundImage: style.backgroundImage };
    });
    assert.equal(triggerFill.backgroundImage, 'none', `date triggers should retain the calendar field surface instead of inheriting the form submit fill: ${JSON.stringify(triggerFill)}`);
    await page.getByRole('button', { name: 'Close calendar' }).click();
    assert.equal(await page.locator('[data-date-calendar="from"]').isHidden(), true, 'the visible close control should dismiss the calendar');
    assert.equal(await page.locator('[data-date-trigger="from"]').getAttribute('aria-expanded'), 'false');
    assert.equal(await page.evaluate(() => document.activeElement.getAttribute('data-date-trigger')), 'from', 'closing the calendar should return focus to its date control');
    await page.locator('[data-date-trigger="from"]').click();
    await page.getByRole('button', { name: 'October 7, 2026' }).click();
    assert.equal(await page.locator('[name="from"]').inputValue(), '2026-10-07');
    assert.equal(await page.locator('[name="to"]').inputValue(), '2027-01-07', 'selecting a start date should suggest a three-month range');
    assert.equal(await page.evaluate(() => document.activeElement.getAttribute('data-date-trigger')), 'from', 'choosing a date should return keyboard focus to its date control');

    await page.locator('[data-date-trigger="to"]').click();
    assert.equal(await page.getByRole('dialog', { name: 'Choose end date' }).count(), 1);
    assert.equal(await page.getByRole('button', { name: 'January 8, 2027' }).isDisabled(), true, 'the end calendar must not allow a range longer than three months');
    const calendarBox = await page.locator('[data-date-calendar="to"]').evaluate(element => {
        const { left, right, width } = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        return { left, right, width, viewport: window.innerWidth, position: style.position, cssLeft: style.left, cssRight: style.right, cssWidth: style.width };
    });
    assert.ok(calendarBox.left >= 12 && calendarBox.right <= 378, `the end-date calendar should keep mobile side gutters instead of touching or spilling off screen: ${JSON.stringify(calendarBox)}`);

    await page.setViewportSize({ width: 320, height: 700 });
    const narrowCalendarBox = await page.locator('[data-date-calendar="to"]').evaluate(element => {
        const { left, right } = element.getBoundingClientRect();
        return { left, right, viewport: innerWidth, scrollWidth: element.scrollWidth, clientWidth: element.clientWidth };
    });
    assert.ok(narrowCalendarBox.left >= 12 && narrowCalendarBox.right <= narrowCalendarBox.viewport - 12, `the open calendar should remain inside a narrow phone viewport: ${JSON.stringify(narrowCalendarBox)}`);
    assert.ok(narrowCalendarBox.scrollWidth <= narrowCalendarBox.clientWidth, `the calendar contents should not create horizontal scrolling on a narrow phone: ${JSON.stringify(narrowCalendarBox)}`);

    await page.setViewportSize({ width: 1280, height: 900 });
    const desktopCalendarBox = await page.locator('[data-date-calendar="to"]').evaluate(element => {
        const { left, right, width } = element.getBoundingClientRect();
        return { left, right, width, viewport: innerWidth };
    });
    assert.ok(desktopCalendarBox.left >= 12 && desktopCalendarBox.right <= desktopCalendarBox.viewport - 12, `the viewport-positioned calendar should remain anchored and contained on desktop too: ${JSON.stringify(desktopCalendarBox)}`);
    assert.equal(desktopCalendarBox.width, 320, 'desktop calendar width should remain at its existing compact size');

    await browser.close();
});
