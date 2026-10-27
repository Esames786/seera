// Actual Laravel browser smoke, not client sign-off. Use ONLY the isolated local
// synthetic fixture created for this review. No live host or external requests.
// node tests/manual/uat-browser-smoke.cjs <playwright-module> <isolated-temp-root>
const { chromium } = require(process.argv[2]);
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(process.argv[3] || '');
assert.match(path.basename(root), /^seera-uat-20261026-[a-f0-9]{32}$/);
const env = JSON.parse(fs.readFileSync(path.join(root, 'process-env.json'), 'utf8').replace(/^\uFEFF/, ''));
assert.equal(env.APP_URL, 'http://127.0.0.1:18769');
assert.equal(env.DB_CONNECTION, 'sqlite');
assert.equal(path.resolve(env.DB_DATABASE), path.join(root, 'uat.sqlite'));
const base = env.APP_URL;
const results = [];
const errors = [];
const failedAssets = [];

(async () => {
    const browser = await chromium.launch({headless: true, executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'});
    const context = await browser.newContext({viewport: {width: 1440, height: 1000}, serviceWorkers: 'block'});
    await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
    const page = await context.newPage();
    page.on('pageerror', e => errors.push({url: page.url(), message: e.message}));
    page.on('response', response => { if (response.status() >= 400) failedAssets.push({url: response.url(), status: response.status()}); });
    const check = async (id, fn) => {
        try { await fn(); results.push({id, status: 'PASS'}); }
        catch (e) { results.push({id, status: 'FAIL', detail: e.message}); }
    };
    try {
        await page.goto(base+'/login');
        await page.locator('#email').fill('admin@example.com');
        await page.locator('#password').fill('password'); // synthetic DatabaseSeeder fixture only
        await Promise.all([page.waitForURL('**/admin/dashboard'), page.getByRole('button', {name: 'Sign In', exact: true}).click()]);
        results.push({id: 'synthetic-admin-login', status: 'PASS'});
        for (const route of ['/admin/dashboard', '/admin/users', '/admin/roles', '/admin/hr/employees', '/admin/hr/payroll',
            '/admin/master/customers', '/admin/master/suppliers', '/admin/master/projects', '/admin/master/sites', '/admin/master/warehouses',
            '/admin/inventory/items', '/admin/inventory/purchase-orders', '/admin/inventory/goods-receipts',
            '/admin/accounting/accounts-payable', '/admin/accounting/accounts-receivable', '/admin/accounting/accounts-receivable/credits',
            '/admin/accounting/journal-entries', '/admin/site-expenses', '/admin/equipment', '/admin/accounting/zatca', '/user-guide']) {
            await check('GET '+route, async () => {
                const response = await page.goto(base+route);
                assert.equal(response.status(), 200);
                assert.ok(!page.url().includes('/login'));
                await page.screenshot({path: path.join(root, route.replaceAll('/', '_')+'.png')});
            });
        }
        for (const route of ['/admin/hr/employees/1/edit', '/admin/master/customers/1/edit', '/admin/master/suppliers/1/edit', '/admin/master/projects/1/edit']) {
            await check('workspace '+route, async () => {
                assert.equal((await page.goto(base+route)).status(), 200);
                await page.waitForLoadState('networkidle');
                assert.ok(await page.locator('button:visible').filter({hasText: /Save.*close/i}).count() > 0, 'Save & close visible');
                await page.screenshot({path: path.join(root, route.replaceAll('/', '_')+'.png')});
            });
        }
        await check('customer dirty guard keep/discard', async () => {
            await page.goto(base+'/admin/master/customers/1/edit');
            await page.waitForLoadState('networkidle');
            const name = page.locator('input[name="name"]').first();
            const original = await name.inputValue();
            await name.fill(original+' UAT-unsaved');
            await page.locator('.breadcrumb a').first().click();
            await page.locator('#unsaved-changes[open]').waitFor();
            await page.locator('[data-unsaved-stay]').click();
            assert.equal(await name.inputValue(), original+' UAT-unsaved');
            await page.locator('.breadcrumb a').first().click();
            await Promise.all([page.waitForURL('**/admin/dashboard'), page.locator('[data-unsaved-discard]').click()]);
            await page.goto(base+'/admin/master/customers/1/edit');
            assert.equal(await page.locator('input[name="name"]').first().inputValue(), original);
        });
        await check('Arabic locale and RTL shell', async () => {
            await page.goto(base+'/admin/dashboard');
            await page.locator('#interface-locale').selectOption('ar');
            await Promise.all([page.waitForNavigation(), page.locator('form.locale-switch button').click()]);
            assert.equal(await page.locator('html').getAttribute('lang'), 'ar');
            assert.equal(await page.locator('html').getAttribute('dir'), 'rtl');
            await page.screenshot({path: path.join(root, 'dashboard-ar-desktop.png')});
        });
        await page.setViewportSize({width: 390, height: 844});
        for (const route of ['/admin/dashboard', '/admin/hr/employees/1/edit', '/admin/master/customers/1/edit']) {
            await check('mobile Arabic '+route, async () => {
                assert.equal((await page.goto(base+route)).status(), 200);
                await page.screenshot({path: path.join(root, 'mobile-ar'+route.replaceAll('/', '_')+'.png'), fullPage: true});
                const sizes = await page.evaluate(() => ({viewport: innerWidth, document: document.documentElement.scrollWidth}));
                assert.ok(sizes.document <= sizes.viewport+2, 'page overflow: '+JSON.stringify(sizes));
            });
        }
        await check('no browser script exceptions', async () => assert.deepEqual(errors, []));
        await check('no failed same-origin resources', async () => assert.deepEqual(failedAssets, []));
    } finally {
        fs.writeFileSync(path.join(root, 'browser-results.json'), JSON.stringify({base, results, errors, failedAssets}, null, 2));
        await browser.close();
        console.log(JSON.stringify({results, errors, failedAssets}, null, 2));
    }
    if (results.some(r => r.status === 'FAIL')) process.exitCode = 1;
})().catch(e => {console.error(e.message); process.exitCode = 1;});
