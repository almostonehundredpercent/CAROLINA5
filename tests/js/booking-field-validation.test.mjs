import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const view = await readFile(new URL('../../resources/views/bookings/create.blade.php', import.meta.url), 'utf8');
assert.match(view, /asset\('js\/booking-validation\.js'\)/, 'the booking form should load its client-side validator');
const validator = await readFile(new URL('../../public/js/booking-validation.js', import.meta.url), 'utf8');

test('booking validation blocks invalid contact/details and clears messages after correction', async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();

    try {
        await page.setContent(`<div class="booking-form"><form>
            <section class="booking-validation-summary" id="booking-validation-summary" role="alert" tabindex="-1" hidden>
                <strong id="booking-validation-title">Please correct these details before continuing.</strong><ul></ul>
            </section>
            <label>Full name<input id="guest-name" name="guest_name" required></label>
            <label>Email<input id="guest-email" type="email" name="guest_email" required></label>
            <label>Phone<input id="guest-phone" type="tel" name="guest_phone" required></label>
            <label>Check-in<input type="hidden" name="check_in" required><button type="button">Choose date</button></label>
            <label>Check-out<input type="hidden" name="check_out" required><button type="button">Choose date</button></label>
            <label><input type="checkbox" name="terms_accepted" required> Accept terms</label>
            <button id="booking-submit" type="submit">Continue</button>
        </form></div>`);
        await page.addScriptTag({ content: validator });
        await page.evaluate(() => document.querySelector('form').addEventListener('submit', event => {
            window.bookingSubmitReached = true;
            event.preventDefault();
        }));

        await page.locator('#guest-phone').fill('123');
        await page.locator('#booking-submit').click();
        assert.equal(await page.locator('#booking-validation-summary').isVisible(), true);
        assert.match(await page.locator('#guest-phone-error').innerText(), /Philippine mobile number/);
        assert.equal(await page.locator('#guest-phone').getAttribute('aria-invalid'), 'true');
        assert.equal(await page.locator('[data-validation-field="check_in"]').count(), 1);
        assert.equal(await page.evaluate(() => window.bookingSubmitReached ?? false), false, 'invalid input must not reach the normal submit path');

        await page.locator('#guest-name').fill('Maria Santos');
        await page.locator('#guest-email').fill('maria@example.com');
        await page.locator('#guest-phone').fill('0917 123 4567');
        await page.locator('[name="check_in"]').evaluate(input => { input.value = '2026-11-10'; input.dispatchEvent(new Event('change', { bubbles: true })); });
        await page.locator('[name="check_out"]').evaluate(input => { input.value = '2026-11-11'; input.dispatchEvent(new Event('change', { bubbles: true })); });
        await page.locator('[name="terms_accepted"]').check();
        assert.equal(await page.locator('#guest-phone-error').count(), 0, 'a server-compatible formatted number should clear its field error');
        assert.equal(await page.locator('#booking-validation-summary').isVisible(), false, 'the summary should close after every error is corrected');

        await page.locator('#booking-submit').click();
        assert.equal(await page.evaluate(() => window.bookingSubmitReached), true, 'valid form data should reach the normal availability check');
    } finally {
        await browser.close();
    }
});
