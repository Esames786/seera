const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { pathToFileURL } = require('node:url');
const { chromium } = require(process.env.SEERA_PLAYWRIGHT || 'D:/laragon2/www/property_handle/node_modules/playwright');
(async () => {
    const root = fs.realpathSync(process.argv[2] || '');
    if (!/^seera-guide-[a-f0-9]{32}$/.test(path.basename(root))) throw new Error('Synthetic fixture required');
    const fixture = JSON.parse(fs.readFileSync(path.join(root, 'fixture.private.json')));
    const env = JSON.parse(fs.readFileSync(path.join(root, 'process-env.private.json')));
    const out = path.join(root, 'november-visual-guide-qa'); fs.mkdirSync(out, { recursive: true });
    const manifest = JSON.parse(fs.readFileSync('docs/user-guide/november-screenshots/manifest.json'));
    const results = [], errors = [];
    const check = (name, ok) => { results.push({ name, ok: !!ok }); if (!ok) throw new Error(name); };
    check('21 synthetic, unsubmitted application screenshots', manifest.synthetic && !manifest.business_forms_submitted && !manifest.production_access && manifest.captures.length === 21);
    for (const capture of manifest.captures) check('Image hash and screen URL: ' + capture.file, crypto.createHash('sha256').update(fs.readFileSync(path.join('docs/user-guide/november-screenshots', capture.file))).digest('hex') === capture.sha256 && capture.live_url.startsWith('https://seera.tech-brit.co.uk/admin/'));
    const proof = await (await fetch(env.APP_URL + '/__guide_fixture_attestation')).json();
    if (!fixture.synthetic || !proof.synthetic || proof.id !== path.basename(root) || proof.production_zatca || proof.business_writes) throw new Error('Unsafe server');
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
    try {
        const context = await browser.newContext({ serviceWorkers: 'block' });
        await context.route('**/*', route => {
            const req = route.request(), url = new URL(req.url());
            if (url.origin !== env.APP_URL) return route.abort();
            if (!['GET', 'HEAD'].includes(req.method()) && !(req.method() === 'POST' && ['/login', '/admin/locale'].includes(url.pathname))) throw new Error('Business write blocked');
            return route.continue();
        });
        const page = await context.newPage(); page.on('pageerror', e => errors.push(String(e)));
        const url = env.APP_URL + '/user-guide/CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.html';
        await page.goto(url); check('Guide requires login', new URL(page.url()).pathname === '/login');
        await page.fill('[name=email]', fixture.user); await page.fill('[name=password]', fixture.password);
        await Promise.all([page.waitForURL(u => u.pathname !== '/login'), page.locator('button[type=submit]').click()]);
        await page.goto(url);
        await page.locator('main img').evaluateAll(images => images.forEach(img => { img.loading = 'eager'; }));
        await page.waitForFunction(() => [...document.querySelectorAll('main img')].every(img => img.complete && img.naturalWidth > 0));
        check('All 21 embedded images load while authenticated', await page.locator('main img[src^="data:image/jpeg;base64,"]').count() === 21);
        check('No screenshot asset endpoint dependency', await page.locator('main img:not([src^="data:"])').count() === 0);
        check('All 16 screenshot anchors resolve', await page.locator('main a[id^="screenshot-"]').count() === 16);
        check('All screen links use the canonical site', await page.locator('main a[href^="https://seera.tech-brit.co.uk/admin/"]').count() >= 32);
        check('Training and pending-state explanation retained', (await page.locator('main').innerText()).includes('Actual cleanup and any identity correction are still pending'));
        await page.locator('main details').evaluateAll(items => items.forEach(el => { el.open = true; }));
        for (const width of [320, 390, 768, 1440]) {
            await page.setViewportSize({ width, height: 1000 });
            check('Expanded images stay inside viewport at ' + width, await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        }
        await context.close();
        const offline = await browser.newPage();
        await offline.goto(pathToFileURL(path.resolve('docs/user-guide/CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.html')).href);
        await offline.locator('main img').evaluateAll(images => images.forEach(img => { img.loading = 'eager'; }));
        await offline.waitForFunction(() => [...document.querySelectorAll('main img')].every(img => img.complete && img.naturalWidth > 0));
        check('All 21 images also load offline', await offline.locator('main img').count() === 21);
        check('No JavaScript errors', errors.length === 0);
    } finally { fs.writeFileSync(path.join(out, 'results.json'), JSON.stringify({ results, errors, passed: results.filter(r => r.ok).length, failed: results.filter(r => !r.ok).length }, null, 2)); await browser.close(); }
    console.log(JSON.stringify({ passed: results.length, failed: 0, images: 21 }));
})().catch(e => { console.error(e); process.exitCode = 1; });
