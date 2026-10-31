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

    await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><form id="report-range-form">
        <input type="hidden" name="from" value="2026-10-01">
        <input type="hidden" name="to" value="">
        <div data-report-date-calendars>
            <label class="report-date-field">From <button type="button" data-date-trigger="from" aria-expanded="false"><span data-date-value>Choose date</span></button><div class="report-date-calendar" data-date-calendar="from" role="dialog" hidden></div></label>
            <label class="report-date-field report-date-field-to">To <button type="button" data-date-trigger="to" aria-expanded="false"><span data-date-value>Choose date</span></button><div class="report-date-calendar" data-date-calendar="to" role="dialog" hidden></div></label>
        </div>
        <button type="submit">Custom range</button>
    </form></body></html>`);
    await page.addStyleTag({ content: stylesheet });
    await page.addScriptTag({ type: 'module', content: script });
    await page.evaluate(() => window.mountReportDateCalendars(document.querySelector('[data-report-date-calendars]')));

    await page.locator('[data-date-trigger="from"]').click();
    assert.equal(await page.getByRole('dialog', { name: 'Choose start date' }).count(), 1, 'opening the start control should show its calendar dialog');
    assert.equal(await page.evaluate(() => document.activeElement.getAttribute('data-date-value')), '2026-10-01', 'opening the calendar should move keyboard focus to the selected day');
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

    await browser.close();
});
