// Read-only guide QA against the existing attested synthetic SQLite server.
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { chromium } = require(process.env.SEERA_PLAYWRIGHT || 'D:/laragon2/www/property_handle/node_modules/playwright');

(async () => {
    const root = fs.realpathSync(process.argv[2] || '');
    if (!/^seera-guide-[a-f0-9]{32}$/.test(path.basename(root))) throw new Error('Disposable fixture required');
    const fixture = JSON.parse(fs.readFileSync(path.join(root, 'fixture.private.json')));
    const env = JSON.parse(fs.readFileSync(path.join(root, 'process-env.private.json')));
    const base = env.APP_URL;
    const proof = await (await fetch(base + '/__guide_fixture_attestation')).json();
    if (!fixture.synthetic || !proof.synthetic || proof.id !== path.basename(root) || proof.production_zatca || proof.business_writes) throw new Error('Unsafe fixture');
    const out = process.env.SEERA_GUIDE_QA_OUT
        ? path.resolve(process.env.SEERA_GUIDE_QA_OUT)
        : path.resolve(__dirname, '../../docs/release-readiness/evidence/client-feedback-2026-11-10/user-guide');
    fs.mkdirSync(out, { recursive: true });
    const results = [], errors = [];
    const check = (name, ok, detail) => {
        results.push({ name, ok: !!ok, ...(detail ? { detail } : {}) });
        if (!ok) throw new Error(name);
    };
    const pages = ['index', 'SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE', 'CLIENT-ENTRY-UPDATES-2026-11-10', 'CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10'];
    const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe' });
    try {
        const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 1440, height: 1000 } });
        await context.route('**/*', route => {
            const request = route.request(), url = new URL(request.url());
            if (url.origin !== base) return route.abort();
            if (!['GET', 'HEAD'].includes(request.method()) && !(request.method() === 'POST' && ['/login', '/admin/locale'].includes(url.pathname))) throw new Error('Business write refused');
            return route.continue();
        });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(String(error)));
        for (const name of pages.slice(2)) {
            await page.goto(base + '/user-guide/' + name + '.html');
            check(name + ': guest requires login', new URL(page.url()).pathname === '/login');
        }
        await page.fill('[name=email]', fixture.user);
        await page.fill('[name=password]', fixture.password);
        await Promise.all([page.waitForURL(url => url.pathname.startsWith('/admin/') || url.pathname.startsWith('/user-guide/')), page.locator('button[type=submit]').click()]);
        for (const locale of ['en', 'ar']) {
            const dashboard = await page.goto(base + '/admin/dashboard');
            check(locale + ': dashboard served', dashboard.status() === 200);
            const token = await page.locator('form[action$="/admin/locale"] input[name=_token]').inputValue();
            const switched = await context.request.post(base + '/admin/locale', { form: { _token: token, locale }, maxRedirects: 0 });
            check(locale + ': locale selected', [302, 303].includes(switched.status()));
            await page.goto(base + '/admin/dashboard');
            check(locale + ': portal has direct client feedback shortcut', await page.locator('[data-client-feedback-guide]').getAttribute('href') === base + '/user-guide/CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.html');
            check(locale + ': portal shortcut is translated', await page.locator('[data-client-feedback-guide]').innerText() === (locale === 'ar' ? 'ملاحظات العميل والإصلاحات' : 'Client Issues & Fixes'));
            for (const width of [320, 390, 768, 1440]) {
                await page.setViewportSize({ width, height: 1000 });
                const bounds = await page.evaluate(() => ({ viewport: document.documentElement.clientWidth, scroll: document.documentElement.scrollWidth }));
                check(locale + ': dashboard shortcut no page overflow at ' + width, bounds.scroll <= bounds.viewport + 1, bounds);
            }
            for (const name of pages) {
                const response = await page.goto(base + '/user-guide/' + name + '.html');
                check(locale + ' ' + name + ': authenticated page', response.status() === 200);
                check(locale + ' ' + name + ': English guide language explicit', await page.locator('html').getAttribute('lang') === 'en');
                check(locale + ' ' + name + ': contents links to client issues', await page.locator('aside.toc a[href="CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.html"]').count() === 1);
                for (const width of [320, 390, 768, 1440]) {
                    await page.setViewportSize({ width, height: 1000 });
                    const bounds = await page.evaluate(() => ({ viewport: document.documentElement.clientWidth, scroll: document.documentElement.scrollWidth }));
                    check(locale + ' ' + name + ': no page overflow at ' + width, bounds.scroll <= bounds.viewport + 1, bounds);
                }
                if (name === 'index') {
                    for (const target of pages.slice(2)) check(locale + ': home links ' + target, await page.locator('main a[href="' + target + '.html"]').count() === 1);
                }
                if (name === pages[3]) {
                    const rows = page.locator('main table').first().locator('tbody tr');
                    check(locale + ': all 13 reported points visible', await rows.count() === 13);
                    const statuses = await rows.evaluateAll(items => items.map(row => row.lastElementChild.textContent.trim()));
                    check(locale + ': 10 ready for review, 1 needs confirmation, 2 pending', statuses.filter(text => text.startsWith('READY FOR REVIEW')).length === 10 && statuses.filter(text => text.startsWith('NEEDS CONFIRMATION')).length === 1 && statuses.filter(text => text.startsWith('PENDING')).length === 2);
                    const clientText = await page.locator('main').innerText();
                    check(locale + ': client review status is clear without deployment notes', clientText.includes('need to be checked on your portal') && !/documentation-only|database migration|feedback branch|development candidate|deploy the approved/i.test(clientText));
                    await page.setViewportSize({ width: 390, height: 1000 });
                    check(locale + ': table scrolls locally on mobile', await page.locator('.table-scroll').first().evaluate(el => el.scrollWidth > el.clientWidth && el.tabIndex === 0));
                    if (locale === 'en') {
                        for (const width of [390, 1440]) {
                            await page.setViewportSize({ width, height: 1000 });
                            await page.screenshot({ path: path.join(out, 'issues-fixes-' + width + '.png'), fullPage: true });
                        }
                    }
                }
            }
        }
        await context.close();
        const offline = await browser.newPage();
        for (const name of pages.slice(2)) {
            await offline.goto(pathToFileURL(path.resolve(__dirname, '../../docs/user-guide/' + name + '.html')).href);
            check(name + ': opens offline', await offline.locator('main h1').count() === 1);
            await offline.emulateMedia({ media: 'print' });
            const print = await offline.locator('main table').first().evaluate(el => ({ width: el.getBoundingClientRect().width, parent: el.parentElement.clientWidth, overflow: getComputedStyle(el.parentElement).overflowX }));
            check(name + ': print table is not clipped', print.width <= print.parent + 1 && print.overflow === 'visible', print);
            await offline.emulateMedia({ media: 'screen' });
        }
        check('No browser JavaScript errors', errors.length === 0);
    } finally {
        await browser.close();
        fs.writeFileSync(path.join(out, 'browser-results.json'), JSON.stringify({ source: proof.source, working_tree_guide_changes: true, synthetic: true, business_writes: false, production_access: false, results, errors }, null, 2) + '\n');
    }
    console.log(JSON.stringify({ passed: results.filter(item => item.ok).length, failed: results.filter(item => !item.ok).length, errors: errors.length }));
})().catch(error => { console.error(error.message); process.exitCode = 1; });
