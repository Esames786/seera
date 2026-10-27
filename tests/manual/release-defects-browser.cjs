// Focused closure of the three failed scenarios. Reuses the private, synthetic UAT fixture.
// node tests/manual/release-defects-browser.cjs <playwright-module> <fixture-root>
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
const out = path.resolve('docs/release-readiness/evidence/defect-closure');
fs.mkdirSync(out, {recursive: true});
const results = [], errors = [], failedResources = [];
const routes = {dashboard: '/admin/dashboard', employee: '/admin/hr/employees/1/edit', customer: '/admin/master/customers/1/edit'};
(async () => {
    const browser = await chromium.launch({headless: true, executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'});
    const context = await browser.newContext({viewport: {width: 1440, height: 1000}, serviceWorkers: 'block'});
    await context.route('**/*', r => new URL(r.request().url()).origin === env.APP_URL ? r.continue() : r.abort());
    const page = await context.newPage();
    page.setDefaultTimeout(120000);
    page.on('pageerror', e => errors.push(e.message));
    page.on('response', r => { if (r.status() >= 400) failedResources.push({url: r.url(), status: r.status()}); });
    const check = async (id, fn) => { try { const detail = await fn(); results.push({id, status: 'PASS', detail}); } catch (e) { results.push({id, status: 'FAIL', detail: e.message}); } };
    const size = () => page.evaluate(() => ({viewport: innerWidth, document: document.documentElement.scrollWidth}));
    const noOverflow = async () => { const s = await size(); assert.ok(s.document <= s.viewport + 1, JSON.stringify(s)); return s; };
    try {
        await page.goto(env.APP_URL + '/login');
        await page.locator('#email').fill('admin@example.com');
        await page.locator('#password').fill('password'); // Synthetic fixture only.
        await Promise.all([page.waitForURL('**/admin/dashboard'), page.locator('button[type=submit]').click()]);
        await page.locator('#interface-locale').selectOption('ar');
        await Promise.all([page.waitForNavigation(), page.locator('form.locale-switch button').click()]);
        for (const width of [320, 375, 390, 430, 768]) {
            await page.setViewportSize({width, height: 844});
            for (const [name, route] of Object.entries(routes)) {
                await check(`${name}-ar-${width}`, async () => {
                    assert.equal((await page.goto(env.APP_URL + route)).status(), 200);
                    assert.equal(await page.locator('html').getAttribute('dir'), 'rtl');
                    await page.waitForLoadState('networkidle');
                    const measurements = await noOverflow();
                    assert.match(await page.locator('.page-title').innerText(), /[\u0600-\u06ff]/);
                    await page.screenshot({path: path.join(out, `${name}-ar-${width}.png`), fullPage: true});
                    return measurements;
                });
            }
        }
        await page.setViewportSize({width: 390, height: 844});
        for (const name of ['employee', 'customer']) {
            await page.goto(env.APP_URL + routes[name]);
            await page.waitForLoadState('networkidle');
            const selector = name === 'employee' ? '.employee-workspace-nav a' : '.workspace-nav a';
            const sections = await page.locator(selector).evaluateAll(elements => elements.map(e => e.getAttribute('href')));
            for (const section of sections) {
                await check(`${name}-section-${section}`, async () => {
                    await page.locator(`${selector}[href="${section}"]`).click();
                    await page.waitForLoadState('networkidle');
                    const s = await noOverflow();
                    // Main tabs and AJAX panels keep their navigation links visible.
                    assert.ok(await page.locator(`${selector}[href="${section}"]`).isVisible());
                    const untranslatedLabels = await page.locator('.page label:visible, .page th:visible, .page button:visible, .workspace-nav a:visible, .employee-workspace-nav a:visible').evaluateAll(elements =>
                        elements.map(e => e.textContent.trim()).filter(t => /[A-Za-z]{3}/.test(t) && !/[\u0600-\u06ff]/.test(t) && !/^(IBAN|SAR|IQAMA)$/.test(t)));
                    assert.deepEqual(untranslatedLabels, [], 'English application labels remain');
                    return s;
                });
            }
        }
        await check('customer-long-content-and-unsaved-back', async () => {
            await page.goto(env.APP_URL + routes.customer);
            const input = page.locator('input[name=name]').first();
            const original = await input.inputValue();
            await input.fill('عميل'.repeat(50));
            await page.locator('.page-title').evaluate(e => { e.textContent += ' اسم طويل جداً'.repeat(20); });
            await noOverflow();
            await page.locator('.breadcrumb a').first().click();
            await page.locator('#unsaved-changes[open]').waitFor();
            await noOverflow();
            await page.screenshot({path: path.join(out, 'customer-ar-unsaved-dialog.png'), fullPage: true});
            await page.locator('[data-unsaved-stay]').click();
            assert.equal(await input.inputValue(), 'عميل'.repeat(50));
            await input.fill(original);
        });
        await check('employee-leave-master-dialog-ar-320', async () => {
            await page.setViewportSize({width: 320, height: 844});
            await page.goto(env.APP_URL + routes.employee + '#leaves');
            await page.locator('[data-quick-create="employee-master-leave-type"]').click();
            await page.locator('#employee-master-leave-type.open').waitFor();
            await noOverflow();
            assert.match(await page.locator('#employee-master-leave-type .modal-head').innerText(), /إجازة/);
            await page.screenshot({path: path.join(out, 'employee-ar-leave-master-320.png'), fullPage: true});
            await page.locator('#employee-master-leave-type .modal-close').click();
        });
        await page.locator('#interface-locale').selectOption('en');
        await Promise.all([page.waitForNavigation(), page.locator('form.locale-switch button').click()]);
        for (const [name, route] of Object.entries(routes)) {
            await check(`${name}-en-390`, async () => {
                await page.setViewportSize({width: 390, height: 844});
                assert.equal((await page.goto(env.APP_URL + route)).status(), 200);
                assert.equal(await page.locator('html').getAttribute('dir'), 'ltr');
                return noOverflow();
            });
        }
        await check('no-script-errors', async () => assert.deepEqual(errors, []));
        await check('no-failed-resources', async () => assert.deepEqual(failedResources, []));
    } finally {
        fs.writeFileSync(path.join(out, 'browser-results.json'), JSON.stringify({base: env.APP_URL, results, errors, failedResources}, null, 2));
        console.log(JSON.stringify({results, errors, failedResources}, null, 2));
        await browser.close();
    }
    if (results.some(r => r.status === 'FAIL')) process.exitCode = 1;
})().catch(e => {console.error(e); process.exitCode = 1;});
