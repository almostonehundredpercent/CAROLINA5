import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const scriptPath = new URL('../../public/js/admin-confirmations.js', import.meta.url);
const script = await readFile(scriptPath, 'utf8');
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

async function pageWithCheckInForm() {
    const page = await browser.newPage();
    await page.setContent(`
        <form method="post" data-admin-confirm
            data-confirm-title="Check in this guest?"
            data-confirm-message="Check in Jordan Lee now? This marks the reservation as checked in."
            data-confirm-action="Check in guest">
            <button type="submit">Check in</button>
        </form>
        <dialog class="admin-confirm-dialog" id="admin-confirm-dialog"
            aria-labelledby="admin-confirm-title" aria-describedby="admin-confirm-message">
            <div class="admin-confirm-card">
                <span class="admin-confirm-mark" aria-hidden="true">✓</span>
                <p class="admin-confirm-kicker">CONFIRM CHECK-IN</p>
                <h2 id="admin-confirm-title">Check in this guest?</h2>
                <p id="admin-confirm-message"></p>
                <div class="admin-confirm-actions">
                    <button type="button" data-confirm-cancel>Cancel</button>
                    <button type="button" data-confirm-accept>Check in guest</button>
                </div>
            </div>
        </dialog>
        <output id="submit-count">0</output>
        <script>document.querySelector('form').addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            event.preventDefault();
            document.querySelector('#submit-count').textContent = String(
                Number(document.querySelector('#submit-count').textContent) + 1
            );
        });</script>`);
    await page.addScriptTag({ content: script });
    await page.evaluate(() => {
        window.nativeConfirmCalls = 0;
        window.confirm = () => { window.nativeConfirmCalls += 1; return true; };
    });
    return page;
}

test('check-in opens an in-page confirmation and cancel leaves the form untouched', async () => {
    const page = await pageWithCheckInForm();
    try {
        await page.getByRole('button', { name: 'Check in', exact: true }).click();

        const dialog = page.getByRole('dialog', { name: 'Check in this guest?' });
        await dialog.waitFor({ state: 'visible' });
        assert.match(await dialog.innerText(), /Check in Jordan Lee now\?/);
        assert.equal(await page.locator('#submit-count').textContent(), '0');

        await dialog.getByRole('button', { name: 'Cancel' }).click();

        await page.waitForFunction(() => !document.querySelector('dialog').open);
        assert.equal(await page.locator('#submit-count').textContent(), '0');
        assert.equal(await page.evaluate(() => window.nativeConfirmCalls), 0);
    } finally {
        await page.close();
    }
});

test('check-in submits exactly once only after the in-page confirmation is accepted', async () => {
    const page = await pageWithCheckInForm();
    try {
        await page.getByRole('button', { name: 'Check in', exact: true }).click();
        const dialog = page.getByRole('dialog', { name: 'Check in this guest?' });
        await dialog.waitFor({ state: 'visible' });
        await dialog.getByRole('button', { name: 'Check in guest' }).click();

        await page.waitForFunction(() => document.querySelector('#submit-count').textContent === '1');
        assert.equal(await page.evaluate(() => window.nativeConfirmCalls), 0);
        assert.equal(await page.locator('#admin-confirm-dialog').evaluate(element => element.open), false);
    } finally {
        await page.close();
    }
});

test('the shared dialog displays payment-specific confirmation details', async () => {
    const page = await pageWithCheckInForm();
    try {
        await page.locator('form').evaluate(form => {
            form.dataset.confirmTitle = 'Update payment record?';
            form.dataset.confirmMessage = 'Mark this payment as unpaid? This does not issue a refund.';
            form.dataset.confirmAction = 'Mark unpaid';
            form.dataset.confirmKicker = 'PAYMENT RECORD';
            form.dataset.confirmIcon = '₱';
        });
        await page.getByRole('button', { name: 'Check in', exact: true }).click();

        const dialog = page.locator('#admin-confirm-dialog');
        await page.waitForFunction(() => document.querySelector('#admin-confirm-dialog').open);
        assert.equal(await dialog.locator('#admin-confirm-title').textContent(), 'Update payment record?');
        assert.match(await dialog.innerText(), /PAYMENT RECORD/);
        assert.match(await dialog.innerText(), /Mark this payment as unpaid\?/);
        assert.equal(await dialog.locator('.admin-confirm-mark').textContent(), '₱');
        assert.equal(await dialog.getByRole('button', { name: 'Mark unpaid' }).count(), 1);
    } finally {
        await page.close();
    }
});
