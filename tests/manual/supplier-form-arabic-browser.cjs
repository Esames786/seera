// Synthetic local fixture only. Creates QA suppliers/lookups/accounts, never production records.
const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require(process.env.SEERA_PLAYWRIGHT || 'D:/laragon2/www/property_handle/node_modules/playwright');
const BASE = process.env.SEERA_BASE || 'http://127.0.0.1:18771';
if (!/^http:\/\/127\.0\.0\.1:18771$/.test(BASE)) throw new Error('Only the isolated supplier QA fixture on 18771 is supported');
const OUT = process.env.SEERA_OUT || path.resolve(__dirname, '../../docs/release-readiness/evidence/supplier-form-arabic-2026-11-01');
const SHOTS = path.join(os.tmpdir(), 'seera-supplier-form-arabic-shots');
const AR = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../../lang/ar.json'), 'utf8'));
const results = [], errors = [];
const check = (name, ok, detail = null) => { results.push({ name, ok: !!ok, detail }); console.log(`${ok ? 'PASS' : 'FAIL'} ${name}`); };
const equal = (a, b) => JSON.stringify(a) === JSON.stringify(b);
const widths = [320, 390, 768, 1440];
const routes = { create: '/admin/master/suppliers/create', edit: '/admin/master/suppliers/1/edit' };
const dialogs = ['qc-supplier-category', 'qc-payment-term', 'qc-payable-account'];

async function login(context, email = 'admin@example.com') {
    const page = await context.newPage();
    page.setDefaultTimeout(90000);
    page.on('pageerror', e => errors.push(String(e)));
    await page.goto(BASE + '/login');
    await page.fill('[name=email]', email); await page.fill('[name=password]', 'password');
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    return page;
}
async function locale(page, value) {
    await page.evaluate(async loc => {
        const form = document.querySelector('.locale-switch');
        const data = new FormData(form); data.set('locale', loc);
        await fetch(form.action, { method: 'POST', body: data });
    }, value);
}
async function contract(page) {
    return page.evaluate(() => [...document.querySelectorAll('form#profile, form.quick-create-form')].map(form => ({
        action: new URL(form.action).pathname, method: form.method,
        fields: [...form.querySelectorAll('input[name], select[name], textarea[name], button[name]')].filter(x => x.name !== '_token').map(x => ({
            name: x.name, type: x.type, value: x.value, checked: x.checked, required: x.required,
            options: x.options ? [...x.options].map(o => ({ value: o.value, selected: o.selected })) : null,
        })),
    })));
}
async function inspect(page, selector) {
    return page.locator(selector).evaluate(root => {
        const text = e => e.textContent.replace(/\s+/g, ' ').trim();
        const untranslated = s => /[A-Za-z]{3,}/.test(s) && !/[\u0600-\u06ff]/.test(s) && !/^(SA\d|300X|1010X)/.test(s);
        const card = (root.querySelector('.modal-card') || root).getBoundingClientRect();
        return {
            latinLabels: [...root.querySelectorAll('label:not(.check-line), .section-title, .small, .help-box, .modal-head, button')].map(text).filter(untranslated),
            latinPlaceholders: [...root.querySelectorAll('[placeholder]')].map(x => x.placeholder).filter(untranslated),
            scrollWidth: document.documentElement.scrollWidth, width: window.innerWidth,
            left: card.left, right: card.right,
        };
    });
}
(async () => {
    fs.mkdirSync(OUT, { recursive: true }); fs.mkdirSync(SHOTS, { recursive: true });
    const browser = await chromium.launch({ executablePath: process.env.SEERA_EDGE || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
    try {
        const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block' });
        await context.route('**/*', route => new URL(route.request().url()).origin === BASE ? route.continue() : route.abort());
        const page = await login(context);
        const contracts = {};
        for (const lang of ['en', 'ar']) {
            await locale(page, lang);
            for (const [screen, url] of Object.entries(routes)) {
                const response = await page.goto(BASE + url);
                check(`${lang} ${screen} opens`, response.status() === 200);
                const title = screen === 'create' ? 'Add Supplier' : 'Supplier Workspace';
                const breadcrumb = `Master Setup / Suppliers / ${title}`;
                check(`${lang} ${screen} document title and breadcrumb translate`,
                    (await page.title()).startsWith(lang === 'ar' ? AR[title] : title) &&
                    (await page.locator('.breadcrumb').innerText()).trim().endsWith(lang === 'ar' ? AR[breadcrumb] : breadcrumb));
                contracts[`${lang}-${screen}`] = await contract(page);
                check(`${lang} ${screen} contains main form and three dialogs`, contracts[`${lang}-${screen}`].length === 4);
                const controls = await page.locator('#profile [name=_save_action]').evaluateAll(xs => xs.map(x => x.value));
                check(`${lang} ${screen} Save and Save & Close remain`, controls.includes('stay') && controls.includes('close'), controls);
                if (screen === 'edit') check(`${lang} edit keeps seven related panels`, await page.locator('[data-workspace-related]').count() === 7);
                for (const width of widths) {
                    await page.setViewportSize({ width, height: 900 });
                    const data = await inspect(page, '#profile');
                    check(`${lang} ${screen} ${width}px no overflow`, data.scrollWidth <= width, data);
                    if (lang === 'ar') check(`${screen} ${width}px Arabic fields/placeholders/help/actions`, data.latinLabels.length === 0 && data.latinPlaceholders.length === 0, data);
                    if (width === 390) await page.screenshot({ path: path.join(SHOTS, `${screen}-${lang}-390.png`), fullPage: true });
                    for (const id of dialogs) {
                        await page.click(`[data-quick-create="${id}"]`);
                        await page.locator(`#${id}.open`).waitFor();
                        const d = await inspect(page, `#${id}`);
                        check(`${lang} ${screen} ${width}px ${id} fits and translates`, d.scrollWidth <= width && d.left >= -1 && d.right <= width + 1 && (lang !== 'ar' || (!d.latinLabels.length && !d.latinPlaceholders.length)), d);
                        if (lang === 'ar' && width === 390) await page.screenshot({ path: path.join(SHOTS, `${screen}-${id}-ar-390.png`) });
                        await page.locator(`#${id} .modal-close`).click();
                    }
                }
            }
        }
        for (const screen of Object.keys(routes)) check(`${screen}: identical names, values, options and form endpoints in EN/AR`, equal(contracts[`en-${screen}`], contracts[`ar-${screen}`]));

        // Real browser submissions on the disposable fixture; quick-create must return/select the actual record ID.
        const run = Date.now();
        for (const lang of ['en', 'ar']) {
            await locale(page, lang); await page.goto(BASE + routes.create);
            await page.setViewportSize({ width: 390, height: 900 });
            const category = `QA category ${lang} ${run}`, term = `QA term ${lang} ${run}`, account = `QA payable ${lang} ${run}`;
            for (const [id, fields, target, expected] of [
                ['qc-supplier-category', { value: category }, 'category', category],
                ['qc-payment-term', { name: term, days: '45', description: 'Original user text' }, 'payment_term_id', term],
                ['qc-payable-account', { account_name: account }, 'linked_account_id', account],
            ]) {
                await page.click(`[data-quick-create="${id}"]`);
                for (const [name, value] of Object.entries(fields)) await page.locator(`#${id} [name="${name}"]`).fill(value);
                // Wait on the real endpoint; do not infer success from a closed dialog alone.
                const endpoint = await page.locator(`#${id} form`).getAttribute('action');
                const result = page.waitForResponse(r => r.request().method() === 'POST' && r.url() === endpoint);
                await page.locator(`#${id} button[type=submit]`).click();
                const response = await result;
                const body = await response.json();
                check(`${lang} ${id} actual save succeeds`, response.status() === 201 || response.status() === 200, { status: response.status() });
                await page.locator(`#${id}.open`).waitFor({ state: 'hidden' });
                const selected = await page.locator(`#${target}`).evaluate(x => ({ value: x.value, text: x.selectedOptions[0]?.textContent }));
                check(`${lang} ${id} returned value selected without losing user text`, selected.value === String(body.id) && selected.text.includes(expected), selected);
                if (id === 'qc-payment-term') check(`${lang} new term computed day suffix is localized`, selected.text.endsWith(lang === 'ar' ? '(45 يوماً)' : '(45 days)'), selected);
            }
            const name = `QA supplier ${lang} ${run} مورد`, code = `QA-${lang}-${run}`;
            await page.fill('#name', name); await page.fill('#code', code);
            await page.fill('#opening_balance', '1234.56'); await page.fill('#bank_name', 'Original Bank');
            await page.fill('#bank_account_name', 'Original Holder'); await page.fill('#iban', 'SA001234567890');
            await page.selectOption('#allowed_payment_types', 'Bank');
            await Promise.all([page.waitForNavigation(), page.click('#profile [name=_save_action][value=stay]')]);
            check(`${lang} create Save opens edit with exact submitted values`, /\/suppliers\/\d+\/edit$/.test(page.url()) && await page.inputValue('#name') === name && await page.inputValue('#opening_balance') === '1234.56' && await page.inputValue('#allowed_payment_types') === 'Bank' && await page.inputValue('#bank_name') === 'Original Bank' && await page.inputValue('#iban') === 'SA001234567890');
            await page.fill('#bank_account_name', 'Updated Holder');
            const editUrl = page.url();
            await Promise.all([page.waitForNavigation(), page.click('#profile [name=_save_action][value=close]')]);
            check(`${lang} Edit Save & Close returns to suppliers`, new URL(page.url()).pathname === '/admin/master/suppliers');
            await page.goto(editUrl);
            check(`${lang} edit persisted account holder and retained financial values`, await page.inputValue('#bank_account_name') === 'Updated Holder' && await page.inputValue('#opening_balance') === '1234.56' && await page.inputValue('#allowed_payment_types') === 'Bank');
        }
        await page.goto(BASE + '/admin/master/suppliers/1');
        check('Supplier View has no data-writing form or save controls', await page.locator('#profile input, [data-related-save], [name=_save_action]').count() === 0);
        await context.close();
        for (const email of ['zubair@example.com', 'waleed@example.com']) {
            const ctx = await browser.newContext(); const p = await login(ctx, email);
            for (const [screen, url] of Object.entries(routes)) check(`${email} denied ${screen}`, (await p.goto(BASE + url)).status() === 403);
            await ctx.close();
        }
    } finally { await browser.close(); }
    const summary = { total: results.length, passed: results.filter(x => x.ok).length, failed: results.filter(x => !x.ok).length, pageErrors: errors.length };
    fs.writeFileSync(path.join(OUT, 'browser-results.json'), JSON.stringify({ generatedAt: new Date().toISOString(), base: BASE, summary, results, errors }, null, 2));
    console.log(JSON.stringify(summary)); process.exit(summary.failed || errors.length ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
