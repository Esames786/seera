// Offline browser regression: real release JS/CSS, synthetic shared-control markup.
// All requests are fulfilled locally. No server, credentials or business submission.
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.SEERA_PLAYWRIGHT || 'D:/laragon2/www/property_handle/node_modules/playwright');
const manifest = JSON.parse(fs.readFileSync('public/build/manifest.json'));
const script = fs.readFileSync(path.join('public/build', manifest['resources/js/app.js'].file));
const style = fs.readFileSync(path.join('public/build', manifest['resources/css/erp.css'].file));
const origin = 'https://seera-controls.test';
const out = path.resolve('docs/release-readiness/evidence/asset-permissions-2026-11-11');
fs.mkdirSync(out, { recursive: true });
const checks = [];
const check = (name, ok) => { checks.push({ name, ok: !!ok }); if (!ok) throw new Error(name); };
const html = (kind, locale, restored = false) => `<!doctype html><html lang="${locale}" dir="${locale === 'ar' ? 'rtl' : 'ltr'}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/release.css"><script type="module" src="/release.js"></script></head><body><main class="page" style="margin:0;padding:12px"><h1>Synthetic ${kind} control regression</h1><form><div data-master-code><label for="code-preview" data-code-label>${kind} code</label><input id="code-preview" class="input" data-code-preview value="${kind === 'Customer' ? 'CUS' : 'SUP'}-002" readonly aria-label="Automatic code"><label><input type="checkbox" data-code-manual ${restored ? 'checked' : ''}>Enter my own code instead</label><input class="input" id="code" name="code" data-code-input hidden autocomplete="off"><div data-code-help>Assigned automatically when saved.</div></div><div data-document-inputs><div data-document-row><select name="new_documents[0][document_type]"><option value="other">Other</option></select><input type="file" name="new_documents[0][file]"><button type="button" data-remove-document>Remove</button></div><button type="button" data-add-document>Add document</button></div><div data-profile-photo><img data-photo-preview hidden width="80" height="80"><input type="file" data-photo-input name="profile_photo" accept="image/png,image/jpeg,image/webp"></div></form></main></body></html>`;
(async () => {
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
    const errors = [];
    try {
        const blocked = await browser.newContext({ serviceWorkers: 'block' });
        await blocked.route('**/*', route => {
            const u = new URL(route.request().url());
            if (u.origin !== origin) throw new Error('External request refused');
            return route.fulfill(u.pathname === '/release.js' ? { status: 403, body: 'Forbidden' }
                : u.pathname === '/release.css' ? { contentType: 'text/css', body: style }
                    : { contentType: 'text/html', body: html('Customer', 'en') });
        });
        const failed = await blocked.newPage();
        await failed.goto(origin);
        await failed.locator('[data-code-manual]').check();
        check('403 script reproduces checked checkbox with manual field still hidden', !await failed.locator('[data-code-input]').isVisible());
        check('403 script leaves automatic preview read-only', !await failed.locator('[data-code-preview]').isEditable());
        await blocked.close();

        const context = await browser.newContext({ serviceWorkers: 'block' });
        await context.route('**/*', route => {
            const u = new URL(route.request().url());
            if (u.origin !== origin) throw new Error('External request refused');
            if (route.request().method() !== 'GET') throw new Error('All form submissions refused');
            return route.fulfill(u.pathname === '/release.js' ? { contentType: 'text/javascript', body: script }
                : u.pathname === '/release.css' ? { contentType: 'text/css', body: style }
                    : { contentType: 'text/html', body: html(u.pathname.includes('supplier') ? 'Supplier' : 'Customer', u.searchParams.get('locale') || 'en', u.searchParams.has('restored')) });
        });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        for (const kind of ['customer', 'supplier']) for (const locale of ['en', 'ar']) for (const width of [320, 390, 768, 1440]) {
            const label = `${kind} ${locale} ${width}`;
            await page.setViewportSize({ width, height: 900 });
            await page.goto(`${origin}/${kind}?locale=${locale}`);
            check(label + ': automatic preview starts read-only', !await page.locator('[data-code-preview]').isEditable());
            check(label + ': no submitted manual value initially', await page.locator('[data-code-input]').inputValue() === '' && !await page.locator('[data-code-input]').isVisible());
            await page.locator('[data-code-manual]').check();
            await page.locator('[data-code-input]').waitFor({ state: 'visible' });
            check(label + ': manual input becomes editable', await page.locator('[data-code-input]').isEditable());
            check(label + ': manual mode hides the readonly preview and automatic help', !await page.locator('[data-code-preview]').isVisible() && !await page.locator('[data-code-help]').isVisible());
            const code = `TRAIN-${kind.toUpperCase()}-101`;
            await page.locator('[data-code-input]').fill(code);
            check(label + ': typed manual value preserved', await page.locator('[data-code-input]').inputValue() === code);
            check(label + ': submitted field is the manual code, not the preview', await page.locator('form').evaluate(form => new FormData(form).get('code')) === code);
            if (width === 390 && locale === 'en') await page.screenshot({ path: path.join(out, `${kind}-manual-code-synthetic.png`) });
            await page.locator('[data-code-manual]').uncheck();
            check(label + ': automatic mode clears and hides manual value', !await page.locator('[data-code-input]').isVisible() && await page.locator('[data-code-input]').inputValue() === '');
            await page.locator('[data-code-manual]').evaluate(el => { el.checked = true; window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })); });
            check(label + ': browser history restoration synchronizes without a change event', await page.locator('[data-code-input]').isEditable() && !await page.locator('[data-code-preview]').isVisible());
            await page.locator('[data-add-document]').click();
            check(label + ': document add creates a distinct file field', await page.locator('[name="new_documents[1][file]"]').count() === 1);
            await page.locator('[data-document-row]').last().locator('[data-remove-document]').click();
            check(label + ': document remove works', await page.locator('[data-document-row]').count() === 1);
            await page.locator('[data-photo-input]').setInputFiles({ name: 'training.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAAE0lEQVR4nGNkYPjPAANMcBZeDgAx0wEH1s7nlgAAAABJRU5ErkJggg==', 'base64') });
            check(label + ': photo preview uses selected local image', await page.locator('[data-photo-preview]').isVisible() && (await page.locator('[data-photo-preview]').getAttribute('src')).startsWith('blob:'));
        }
        for (const kind of ['customer', 'supplier']) for (const locale of ['en', 'ar']) {
            await page.goto(`${origin}/${kind}?locale=${locale}&restored=1`);
            check(`${kind} ${locale}: initially restored checked mode is editable`, await page.locator('[data-code-input]').isVisible() && await page.locator('[data-code-input]').isEditable());
        }
        check('No JavaScript page errors when release asset loads', errors.length === 0);
    } finally {
        await browser.close();
        fs.writeFileSync(path.join(out, 'browser-results.json'), JSON.stringify({ synthetic: true, actual_app_http_pages: false, real_release_assets: true, production_access: false, form_submissions: false, checks, errors }, null, 2));
    }
    console.log(JSON.stringify({ passed: checks.filter(c => c.ok).length, failed: checks.filter(c => !c.ok).length }));
})().catch(error => { console.error(error.message); process.exitCode = 1; });
