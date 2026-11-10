// Read-only EN/AR responsive QA. No payment, settlement or other business POST is permitted.
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.SEERA_PLAYWRIGHT || 'D:/laragon2/www/property_handle/node_modules/playwright');
(async () => {
    const root = fs.realpathSync(process.argv[2] || '');
    if (!/^seera-guide-[a-f0-9]{32}$/.test(path.basename(root))) throw new Error('Synthetic fixture required');
    const fixture = JSON.parse(fs.readFileSync(path.join(root, 'fixture.private.json')));
    const env = JSON.parse(fs.readFileSync(path.join(root, 'process-env.private.json')));
    const ids = JSON.parse(fs.readFileSync(path.join(root, 'closure.private.json')));
    const base = env.APP_URL;
    const proof = await (await fetch(base + '/__guide_fixture_attestation')).json();
    if (!fixture.synthetic || !proof.synthetic || proof.id !== path.basename(root) || proof.production_zatca || proof.business_writes) throw new Error('Unsafe server');
    const out = path.resolve('docs/release-readiness/evidence/client-closure-2026-11-11/browser'); fs.mkdirSync(out, { recursive: true });
    const results = [], errors = [];
    const check = (name, ok, detail) => { results.push({ name, ok: !!ok, detail }); if (!ok) throw new Error(name + ' ' + JSON.stringify(detail)); };
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
    try {
        const context = await browser.newContext({ serviceWorkers: 'block' });
        await context.route('**/*', route => {
            const req = route.request(), url = new URL(req.url());
            if (url.origin !== base) return route.abort();
            if (!['GET', 'HEAD'].includes(req.method()) && !(req.method() === 'POST' && ['/login', '/admin/locale'].includes(url.pathname))) throw new Error('Business write blocked');
            return route.continue();
        });
        const page = await context.newPage(); page.on('pageerror', e => errors.push(String(e))); page.setDefaultTimeout(20000);
        await page.goto(base + '/login'); await page.fill('[name=email]', fixture.user); await page.fill('[name=password]', fixture.password);
        await Promise.all([page.waitForURL(url => url.pathname !== '/login'), page.locator('button[type=submit]').click()]);
        for (const locale of ['en', 'ar']) {
            await page.goto(base + '/admin/dashboard');
            await Promise.all([page.waitForNavigation(), page.locator('form[action$="/locale"]').evaluate((form, locale) => { form.querySelector('select').value = locale; form.requestSubmit(); }, locale)]);
            for (const width of [320, 390, 768, 1440]) {
                await page.setViewportSize({ width, height: 1000 });
                for (const [name, route] of [
                    ['dashboard', '/admin/dashboard'], ['users', '/admin/users'], ['supplier-create', '/admin/master/suppliers/create'],
                    ['supplier-manage', `/admin/master/suppliers/${ids.supplier}/edit#advances`],
                    ['supplier-view', `/admin/master/suppliers/${ids.supplier}`], ['notifications', '/admin/notifications'],
                    ['employee', `/admin/hr/employees/${fixture.ids.employee}/edit`], ['customer', `/admin/master/customers/${fixture.ids.customer}/edit`],
                ]) {
                    const response = await page.goto(base + route); check(`${locale}/${width}/${name}: response`, response.status() === 200);
                    if (name === 'supplier-manage') await page.waitForSelector('[name=recoverable_confirmed]', { state: 'attached' });
                    const layout = await page.evaluate(() => ({ width: innerWidth, scroll: document.documentElement.scrollWidth, dir: document.documentElement.dir }));
                    check(`${locale}/${width}/${name}: viewport`, layout.scroll <= width + 1 && layout.dir === (locale === 'ar' ? 'rtl' : 'ltr'), layout);
                    const text = await page.locator('body').innerText();
                    check(`${locale}/${width}/${name}: translation keys`, !/\b(?:supplier_advances|notifications_ui|ui)\.[a-z_]+/.test(text));
                    if (name === 'supplier-view') check(`${locale}/${width}: supplier read only`, await page.locator('[name=recoverable_confirmed]').count() === 0 && !await page.locator('form[action*="/advances"]').count());
                    if (name === 'supplier-manage') {
                        check(`${locale}/${width}: recoverable actions`, await page.locator('form[action*="/advances"]').count() >= 4);
                        check(`${locale}/${width}: saved training balance`, text.includes('900.00'));
                        await page.locator('[name=recoverable_confirmed]').evaluate(el => { el.closest('details').open = true; });
                        check(`${locale}/${width}: expanded payment form no overflow`, await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
                    }
                    if (name === 'notifications') check(`${locale}/${width}: overdue invoice link`, !!await page.locator(`a[href$="/accounts-receivable/${ids.invoice}"]`).count());
                    if (width === 390 || width === 1440) await page.screenshot({ path: path.join(out, `${locale}-${name}-${width}.png`), fullPage: true });
                }
            }
        }
        check('No browser JavaScript errors', errors.length === 0, errors);
    } finally {
        fs.writeFileSync(path.join(out, 'results.json'), JSON.stringify({ synthetic: true, business_posts: false, results, errors, passed: results.filter(r => r.ok).length, failed: results.filter(r => !r.ok).length }, null, 2));
        await browser.close();
    }
    console.log(JSON.stringify({ passed: results.length, errors: errors.length }));
})().catch(e => { console.error(e); process.exitCode = 1; });
