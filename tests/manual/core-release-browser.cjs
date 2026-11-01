// Core integration review: disposable SQLite fixture only. Never use a remote target.
const fs = require('fs'), path = require('path'), os = require('os');
const { chromium } = require(process.env.SEERA_PLAYWRIGHT || 'D:/laragon2/www/property_handle/node_modules/playwright');
const BASE = 'http://127.0.0.1:18771';
const OUT = path.resolve('docs/release-readiness/evidence/core-integration-review-2026-11-01');
const SHOTS = path.join(os.tmpdir(), 'seera-core-integration-review-shots');
const results = [], errors = [];
const check = (name, ok, detail = null) => { results.push({ name, ok: !!ok, detail }); console.log(`${ok ? 'PASS' : 'FAIL'} ${name}`); };
async function login(context, email) {
    const page = await context.newPage(); page.setDefaultTimeout(90000);
    page.on('pageerror', e => errors.push(String(e)));
    await page.goto(BASE + '/login'); await page.fill('#email', email); await page.fill('#password', 'password');
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]); return page;
}
async function locale(page, value) {
    await page.evaluate(async loc => { const form = document.querySelector('.locale-switch'); const data = new FormData(form); data.set('locale', loc); await fetch(form.action, { method: 'POST', body: data }); }, value);
}
async function submitInvoice(page, rate, forged = false, reason = null) {
    await page.goto(BASE + '/admin/accounting/accounts-receivable/create');
    await page.selectOption('#customer_id', '1');
    await page.fill('[name="lines[0][description]"]', 'Core release QA line');
    await page.fill('[name="lines[0][quantity]"]', '2'); await page.fill('[name="lines[0][unit_price]"]', '100');
    if (forged) await page.locator('#vat_rate').evaluate(x => x.removeAttribute('readonly'));
    if (await page.locator('#vat_rate').getAttribute('readonly') === null) await page.fill('#vat_rate', String(rate));
    if (reason !== null) await page.fill('#vat_override_reason', reason);
    await Promise.all([page.waitForNavigation(), page.locator('button[name=_save_action][value=stay], form button.btn.primary[type=submit]').first().click()]);
}
(async () => {
    fs.mkdirSync(OUT, { recursive: true }); fs.mkdirSync(SHOTS, { recursive: true });
    const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe' });
    try {
        const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block' });
        await ctx.route('**/*', route => new URL(route.request().url()).origin === BASE ? route.continue() : route.abort());
        const admin = await login(ctx, 'admin@example.com');
        const journeys = {
            dashboard: '/admin/dashboard', hr: '/admin/hr/dashboard', 'hr-reports': '/admin/reports/hr',
            employee: '/admin/hr/employees/1/edit', project: '/admin/master/projects/1', 'project-manage': '/admin/master/projects/1/edit',
            budget: '/admin/master/projects/1/budget', 'project-reports': '/admin/reports/projects?search=PRJ-001',
            customer: '/admin/master/customers/1', supplier: '/admin/master/suppliers/1', 'supplier-manage': '/admin/master/suppliers/1/edit',
            'purchase-requests': '/admin/inventory/purchase-requests', 'purchase-orders': '/admin/inventory/purchase-orders',
            'goods-receipts': '/admin/inventory/goods-receipts', 'supplier-bills': '/admin/accounting/accounts-payable',
            invoices: '/admin/accounting/accounts-receivable', credits: '/admin/accounting/accounts-receivable/credits',
            approvals: '/admin/my-approvals', journals: '/admin/accounting/journal-entries', ledger: '/admin/accounting/general-ledger',
            'vat-report': '/admin/accounting/reports/vat-report', 'trial-balance': '/admin/accounting/reports/trial-balance',
        };
        for (const lang of ['en', 'ar']) {
            await locale(admin, lang);
            for (const [name, url] of Object.entries(journeys)) {
                const response = await admin.goto(BASE + url);
                const dir = await admin.locator('html').getAttribute('dir');
                check(`${lang} ${name} opens with expected direction`, response.status() === 200 && dir === (lang === 'ar' ? 'rtl' : 'ltr'), { status: response.status(), dir });
                if (['dashboard', 'hr', 'hr-reports', 'employee', 'project', 'customer', 'supplier'].includes(name)) {
                    for (const width of [320, 390, 768, 1440]) {
                        await admin.setViewportSize({ width, height: 900 });
                        const size = await admin.evaluate(() => ({ width: innerWidth, scroll: document.documentElement.scrollWidth }));
                        check(`${lang} ${name} ${width}px fits`, size.scroll <= size.width, size);
                        if (lang === 'ar' && width === 390) await admin.screenshot({ path: path.join(SHOTS, `${name}-ar-390.png`), fullPage: true });
                    }
                }
            }
        }
        // Real Sales/AR journey in the disposable fixture: draft -> approve/post -> print -> receipt.
        await locale(admin, 'en'); await admin.setViewportSize({ width: 1440, height: 900 });
        await submitInvoice(admin, 5, false, '');
        check('Super Admin nonstandard VAT without reason refused', (await admin.locator('.page').innerText()).includes('Enter a reason'));
        await submitInvoice(admin, 5, false, 'Core release QA: synthetic override');
        check('Super Admin reasoned override creates draft', await admin.locator('.field-error').count() === 0 && /\/accounts-receivable\/\d+$/.test(new URL(admin.url()).pathname), { url: new URL(admin.url()).pathname });

        await submitInvoice(admin, 15);
        const invoicePath = new URL(admin.url()).pathname;
        check('Standard invoice draft shows correct 230 total', /\/accounts-receivable\/\d+$/.test(invoicePath) && (await admin.locator('.page').innerText()).includes('230.00'), { invoicePath });
        const approve = admin.locator(`form[action$="${invoicePath}/approve"] button`);
        await Promise.all([admin.waitForNavigation(), approve.click()]);
        const body = await admin.locator('.page').innerText();
        check('Invoice approval posts and removes edit/approve action', await admin.locator(`form[action$="${invoicePath}/approve"]`).count() === 0 && /230\.00/.test(body));
        const print = await admin.goto(BASE + invoicePath + '/print');
        check('Approved invoice bilingual printable document opens', print.status() === 200 && /230\.00/.test(await admin.locator('body').innerText()));
        await admin.goto(BASE + invoicePath + '/receipt');
        const account = await admin.locator('#receipt_account_id option').evaluateAll(options => options.find(x => x.value && /bank/i.test(x.textContent))?.value);
        if (!account) throw new Error('The synthetic fixture has no bank receipt account');
        await admin.selectOption('#receipt_account_id', account); await admin.fill('#amount', '100');
        await admin.selectOption('#payment_method', 'Bank Transfer');
        await admin.fill('#reference_number', 'CORE-RELEASE-QA');
        await Promise.all([admin.waitForNavigation(), admin.locator('form button[type=submit].primary').click()]);
        check('Partial receipt persists and leaves 130 outstanding', new URL(admin.url()).pathname === invoicePath && (await admin.locator('.page').innerText()).includes('130.00'));
        fs.writeFileSync(path.join(OUT, 'financial-journey.json'), JSON.stringify({ invoicePath, expected: { taxable: 200, vat: 30, total: 230, received: 100, balance: 130 } }, null, 2));
        await ctx.close();

        const financeCtx = await browser.newContext(); const finance = await login(financeCtx, 'zubair@example.com');
        await locale(finance, 'en'); await finance.goto(BASE + '/admin/accounting/accounts-receivable/create');
        check('Ordinary Finance VAT control locked', await finance.locator('#vat_rate').getAttribute('readonly') !== null && await finance.locator('#vat_override_reason').count() === 0);
        await submitInvoice(finance, 5, true);
        check('Ordinary Finance forged 5 percent request refused', (await finance.locator('.page').innerText()).includes('Only a Super Admin may override'));
        await submitInvoice(finance, 15);
        check('Ordinary Finance standard 15 percent invoice saves', await finance.locator('.field-error').count() === 0 && /\/accounts-receivable\/\d+$/.test(new URL(finance.url()).pathname));
        for (const url of ['/admin/hr/dashboard', '/admin/master/suppliers/1/edit']) check(`Finance denied ${url}`, (await finance.goto(BASE + url)).status() === 403);
        await financeCtx.close();
        const hrCtx = await browser.newContext(); const hr = await login(hrCtx, 'waleed@example.com');
        check('HR permitted workforce report', (await hr.goto(BASE + '/admin/reports/hr')).status() === 200);
        for (const url of ['/admin/accounting/accounts-receivable', '/admin/accounting/journal-entries']) check(`HR denied ${url}`, (await hr.goto(BASE + url)).status() === 403);
        await hrCtx.close();
        const siteCtx = await browser.newContext(); const site = await login(siteCtx, 'zafar@example.com');
        check('Site role permitted own project', (await site.goto(BASE + '/admin/master/projects/3')).status() === 200);
        check('Site role denied foreign project', [403, 404].includes((await site.goto(BASE + '/admin/master/projects/1')).status()));
        await siteCtx.close();
    } finally { await browser.close(); }
    const summary = { total: results.length, passed: results.filter(x => x.ok).length, failed: results.filter(x => !x.ok).length, pageErrors: errors.length };
    fs.writeFileSync(path.join(OUT, 'critical-browser-results.json'), JSON.stringify({ generatedAt: new Date().toISOString(), base: BASE, summary, results, errors }, null, 2));
    console.log(JSON.stringify(summary)); process.exit(summary.failed || errors.length ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
