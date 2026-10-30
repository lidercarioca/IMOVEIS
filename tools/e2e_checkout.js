const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: false });
    const page = await browser.newPage({ viewport: { width: 1440, height: 1200 } });
    page.on('console', (msg) => console.log('BROWSER_LOG=' + msg.type() + ':' + msg.text()));
    page.on('pageerror', (err) => console.log('PAGEERROR=' + err.message));
    page.on('requestfailed', (req) =>
        console.log('REQUESTFAILED=' + req.url() + ' ' + (req.failure()?.errorText || 'unknown'))
    );

    const checkoutUrl =
        'https://checkout.stripe.com/c/pay/cs_test_a1fRwJILUY0UmB7dJgCVoq3dVvzwNpbRrcS5FRWWP7OZEs5h3zurGtM48F#fidnandhYHdWcXxpYCc%2FJ2FgY2RwaXEnKSdpamZkaWAnPyd1cScpJ2JwZGZkaGppYFNkd2xka3EnPydmamtxd2ppJyknZHVsTmB8Jz8ndW5acWB2cVowNFFJaV1QSVBDdG5TfUFNbX1kbktSR0hsUjZTS25nR2tXamt0TkRdRlZSQEBtUk5IVGtmM0BLNWJ8UG08cU92UH9jbjFjSDBmbFEyVmg3PVN1RGEzfXZhRDU1NFFmYWk8al0nKSdjd2poVmB3c2B3Jz9xd3BgKSdnZGZuYndqcGthRmppancnPycmY2NjY2NjJyknaWR8anBxUXx1YCc%2FJ3Zsa2JpYFpscWBoJyknYGtkZ2lgVWlkZmBtamlhYHd2Jz9xd3BgeCUl';
    const adminEmail = 'e2e-checkout@example.com';
    const adminName = 'E2E Checkout User';

    await page.goto(checkoutUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(6000);

    console.log(
        'CHECKOUT_FRAMES=' +
            JSON.stringify(
                page.frames().map((frame) => ({
                    url: frame.url(),
                    name: frame.name(),
                }))
            )
    );

    const checkoutInputs = await page.evaluate(() =>
        Array.from(document.querySelectorAll('input, select, button')).map((el) => ({
            tag: el.tagName,
            type: el.type || '',
            id: el.id || '',
            name: el.name || '',
            placeholder: el.placeholder || '',
            autocomplete: el.getAttribute('autocomplete') || '',
            ariaLabel: el.getAttribute('aria-label') || '',
            value: el.value || '',
            text: el.textContent ? el.textContent.trim() : '',
        }))
    );
    console.log('CHECKOUT_INPUTS=' + JSON.stringify(checkoutInputs.slice(0, 200)));

    const emailField = page.locator('#email');
    const cardNumberField = page.locator('#cardNumber');
    const cardExpiryField = page.locator('#cardExpiry');
    const cardCvcField = page.locator('#cardCvc');
    const billingNameField = page.locator('#billingName');
    const billingCountryField = page.locator('#billingCountry');
    const payButton = page
        .locator('button')
        .filter({ hasText: /Assinar|Pagar|Pay/i })
        .first();

    await emailField.waitFor({ state: 'visible', timeout: 30000 });
    await emailField.fill(adminEmail);
    await cardNumberField.waitFor({ state: 'visible', timeout: 30000 });
    await cardNumberField.fill('4242 4242 4242 4242');
    await cardExpiryField.fill('12 / 34');
    await cardCvcField.fill('123');
    await billingNameField.fill(adminName);
    await billingCountryField.selectOption({ label: 'Brasil' });

    console.log(
        'CARD_FIELD_STATE=' +
            JSON.stringify({
                email: await emailField.inputValue(),
                cardNumber: await cardNumberField.inputValue(),
                cardExpiry: await cardExpiryField.inputValue(),
                cardCvc: await cardCvcField.inputValue(),
                billingName: await billingNameField.inputValue(),
                billingCountry: await billingCountryField.inputValue(),
            })
    );

    await payButton.waitFor({ state: 'visible', timeout: 30000 });
    const buttonDisabled = await payButton.evaluate(
        (el) => el.disabled || el.getAttribute('aria-disabled') === 'true'
    );
    if (buttonDisabled) {
        throw new Error('Stripe payment button is disabled before submission');
    }

    console.log('PAY_BUTTON_TEXT=' + (await payButton.innerText()));
    await payButton.click();
    await page.waitForURL(/stripe_success\.php|checkout\.stripe\.com/, { timeout: 60000 });
    console.log('AFTER_PAYMENT_URL=' + page.url());
    await page.waitForTimeout(6000);
    await browser.close();
})();
