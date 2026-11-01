// Targeted rerun of the receipt journey after correcting the harness cash/bank pairing.
const fs = require('fs'), path = require('path');
const { chromium } = require(process.env.SEERA_PLAYWRIGHT || 'D:/laragon2/www/property_handle/node_modules/playwright');
const BASE = 'http://127.0.0.1:18771';
const OUT = path.resolve('docs/release-readiness/evidence/core-integration-review-2026-11-01');
const { invoicePath } = JSON.parse(fs.readFileSync(path.join(OUT, 'financial-journey.json'), 'utf8'));
if (!/^\/admin\/accounting\/accounts-receivable\/\d+$/.test(invoicePath)) throw new Error('Invalid fixture invoice');
const results = [], errors = [];
const check = (name, ok, detail = null) => { results.push({ name, ok: !!ok, detail }); console.log(`${ok ? 'PASS' : 'FAIL'} ${name}`); };
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe' });
    try {
        const context = await browser.newContext({ serviceWorkers: 'block' });
        await context.route('**/*', r => new URL(r.request().url()).origin === BASE ? r.continue() : r.abort());
        const page = await context.newPage(); page.setDefaultTimeout(90000);
        page.on('pageerror', e => errors.push(String(e)));
        await page.goto(BASE + '/login'); await page.fill('#email', 'admin@example.com'); await page.fill('#password', 'password');
        await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
        await page.goto(BASE + invoicePath + '/receipt');
        const accounts = await page.locator('#receipt_account_id option').evaluateAll(xs => xs.filter(x => x.value).map(x => ({ value: x.value, text: x.textContent })));
        const cash = accounts.find(x => /cash/i.test(x.text)), bank = accounts.find(x => /bank/i.test(x.text));
        if (!cash || !bank) throw new Error('Fixture needs both cash and bank accounts');
        await page.selectOption('#receipt_account_id', cash.value); await page.selectOption('#payment_method', 'Bank Transfer');
        await page.fill('#amount', '100'); await page.fill('#reference_number', 'CORE-RELEASE-QA');
        await Promise.all([page.waitForNavigation(), page.locator('form button[type=submit].primary').click()]);
        check('Cash account with Bank Transfer is refused server-side', (await page.locator('.page').innerText()).includes('The payment method must match'));
        await page.selectOption('#receipt_account_id', bank.value); await page.selectOption('#payment_method', 'Bank Transfer');
        await Promise.all([page.waitForNavigation(), page.locator('form button[type=submit].primary').click()]);
        check('Correct bank receipt saves and returns to invoice', new URL(page.url()).pathname === invoicePath);
        check('100 received, 130 outstanding after corrected receipt', (await page.locator('.page').innerText()).includes('130.00') && (await page.locator('.page').innerText()).includes('100.00'));
        check('Posted invoice edit remains forbidden', (await context.request.get(BASE + invoicePath + '/edit')).status() === 403);
        await context.close();
    } finally { await browser.close(); }
    const summary = { total: results.length, passed: results.filter(x => x.ok).length, failed: results.filter(x => !x.ok).length, pageErrors: errors.length };
    fs.writeFileSync(path.join(OUT, 'receipt-browser-results.json'), JSON.stringify({ summary, results, errors }, null, 2));
    console.log(JSON.stringify(summary)); process.exit(summary.failed || errors.length ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
