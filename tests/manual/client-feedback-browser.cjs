// Read-only browser checks against the attested disposable SQLite fixture.
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.SEERA_PLAYWRIGHT || 'D:/laragon2/www/property_handle/node_modules/playwright');
const BASE = 'http://127.0.0.1:18775';
const ROOT = process.env.SEERA_GUIDE_ROOT;
if (!ROOT || !/^seera-guide-[a-f0-9]{32}$/.test(path.basename(ROOT))) throw new Error('Synthetic fixture required');
const fixture = JSON.parse(fs.readFileSync(path.join(ROOT, 'fixture.private.json')));
if (!fixture.synthetic) throw new Error('Synthetic fixture marker missing');
const OUT = process.env.SEERA_GUIDE_QA_OUT ? path.resolve(process.env.SEERA_GUIDE_QA_OUT) : path.resolve(__dirname, '../../docs/release-readiness/evidence/client-feedback-2026-11-10');
const results = [], errors = [];
const check = (name, ok, detail) => { results.push({ name, ok: !!ok, ...(detail ? { detail } : {}) }); console.log(`${ok ? 'PASS' : 'FAIL'} ${name}`); };
(async () => {
    fs.mkdirSync(OUT, { recursive: true });
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
    try {
        const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, serviceWorkers: 'block' });
        await context.route('**/*', route => {
            const req = route.request(), url = new URL(req.url());
            if (url.protocol === 'blob:') return route.continue();
            if (url.origin !== BASE) return route.abort();
            if (!['GET', 'HEAD'].includes(req.method()) && !(req.method() === 'POST' && ['/login', '/admin/locale'].includes(url.pathname))) throw new Error('Business write blocked: ' + url.pathname);
            return route.continue();
        });
        const page = await context.newPage();
        page.on('dialog', dialog => dialog.accept());
        page.setDefaultTimeout(30000);
        page.on('pageerror', error => errors.push(String(error)));
        const attestation = await (await page.request.get(BASE + '/__guide_fixture_attestation')).json();
        if (!attestation.synthetic || attestation.id !== path.basename(ROOT) || attestation.business_writes !== false) throw new Error('Fixture attestation failed');
        await page.goto(BASE + '/login');
        await page.fill('[name=email]', fixture.user); await page.fill('[name=password]', fixture.password);
        await Promise.all([page.waitForURL('**/admin/dashboard'), page.locator('button[type=submit]').click()]);
        const routes = {
            'customer-create': '/admin/master/customers/create',
            'customer-manage': `/admin/master/customers/${fixture.ids.customer}/edit`,
            'customer-view': `/admin/master/customers/${fixture.ids.customer}`,
            'supplier-create': '/admin/master/suppliers/create',
            'supplier-manage': `/admin/master/suppliers/${fixture.ids.supplier}/edit`,
            'supplier-view': `/admin/master/suppliers/${fixture.ids.supplier}`,
            'employee-create': '/admin/hr/employees/create',
            'employee-manage': `/admin/hr/employees/${fixture.ids.employee}/edit`,
            'employee-view': `/admin/hr/employees/${fixture.ids.employee}`,
            'user-create': '/admin/users/create',
            'user-manage': `/admin/users/${fixture.ids.user}/edit`,
            'equipment-create': '/admin/equipment/create',
            'my-leave': '/admin/my-leave',
        };
        for (const lang of ['en', 'ar']) {
            await page.evaluate(async locale => { const form = document.querySelector('.locale-switch'); const data = new FormData(form); data.set('locale', locale); await fetch(form.action, { method: 'POST', body: data }); }, lang);
            for (const width of [320, 390, 768, 1440]) {
                await page.setViewportSize({ width, height: 1000 });
                for (const [screen, url] of Object.entries(routes)) {
                    const response = await page.goto(BASE + url);
                    check(`${lang} ${width} ${screen} HTTP`, response.status() === 200);
                    check(`${lang} ${width} ${screen} no page overflow`, await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
                    check(`${lang} ${width} ${screen} direction`, await page.locator('html').getAttribute('dir') === (lang === 'ar' ? 'rtl' : 'ltr'));
                    if (screen.endsWith('-view')) check(`${lang} ${width} ${screen} stays read-only`, await page.locator('.page form[action*="/admin/master/"], .page form[action*="/admin/hr/"]').count() === 0);
                    if (screen === 'user-manage') {
                        await page.locator('#individual-permissions').evaluate(el => { el.open = true; });
                        await page.locator('#individual-permissions details').first().evaluate(el => { el.open = true; });
                        check(`${lang} ${width} personal permission controls`, await page.locator('#individual-permissions select').count() > 0);
                        check(`${lang} ${width} open permission matrix no overflow`, await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
                        if (width === 390) await page.screenshot({ path: path.join(OUT, `${lang}-personal-permissions-390.png`), fullPage: true });
                    }
                    if (width === 390 && ['supplier-create', 'equipment-create', 'employee-manage', 'user-create', 'my-leave'].includes(screen)) await page.screenshot({ path: path.join(OUT, `${lang}-${screen}-390.png`), fullPage: true });
                }
            }
            await page.goto(BASE + '/admin/master/suppliers/create');
            await page.locator('[data-code-manual]').check();
            check(`${lang} manual code opens`, await page.locator('[data-code-input]').isVisible());
            await page.locator('[data-code-input]').fill('TRAIN-MANUAL');
            await page.locator('[data-code-manual]').uncheck();
            check(`${lang} automatic code clears manual value`, await page.locator('[data-code-input]').inputValue() === '' && !await page.locator('[data-code-input]').isVisible());
            await page.locator('[data-add-document]').click();
            check(`${lang} document rows add distinct fields`, await page.locator('[name="new_documents[1][file]"]').count() === 1);
            await page.locator('[data-document-row]').last().locator('[data-remove-document]').click();
            check(`${lang} unsaved document row removable`, await page.locator('[data-document-row]').count() === 1);
            await page.goto(BASE + '/admin/users/create');
            await page.locator('[name=profile_photo]').setInputFiles({ name: 'training.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAAE0lEQVR4nGNkYPjPAANMcBZeDgAx0wEH1s7nlgAAAABJRU5ErkJggg==', 'base64') });
            check(`${lang} photo chooser and preview work`, await page.locator('[data-photo-preview]').isVisible() && (await page.locator('[data-photo-preview]').getAttribute('src')).startsWith('blob:'));
        }
        check('no uncaught browser errors', errors.length === 0, errors);
    } finally {
        await browser.close();
        fs.writeFileSync(path.join(OUT, 'browser-results.json'), JSON.stringify({ base: BASE, synthetic: true, business_writes: false, results, errors }, null, 2));
    }
    const failed = results.filter(item => !item.ok);
    console.log(JSON.stringify({ checks: results.length, passed: results.length - failed.length, failed: failed.length }));
    if (failed.length || errors.length) process.exitCode = 1;
})().catch(error => { console.error(error); process.exitCode = 1; });
