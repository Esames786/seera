// Integrated release QA (Sales VAT + Equipment Arabic + bilingual approvals/availability UI).
// Real Laravel browser run on the private, synthetic UAT fixture only; not client sign-off.
// node tests/manual/integration-release-browser.cjs <playwright-module> <fixture-root> [screenshot-dir]
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
const shots = path.resolve(process.argv[4] || path.join(root, 'integration-shots'));
fs.mkdirSync(shots, {recursive: true});
const out = path.resolve('docs/release-readiness/evidence/integration-2026-10-28');
fs.mkdirSync(out, {recursive: true});
const WIDTHS = [320, 375, 390, 430, 768, 1440];
const results = [], errors = [], failedResources = [], untranslated = {};
const ASSET_CODE = 'QA-EQ-' + Date.now().toString().slice(-6);
const stamp = () => new Date().toISOString().slice(0, 10);
const allow = /^(SAR|GPS|IBAN|VAT|ZATCA|QR|KSA|PR|PO|GRN|CAT|km|h|KM|ID|PDF|CSV|XML|OK|UTC)$|^[A-Z]{1,3}-?[\d-]+$|^\d/;
const latinOnly = t => /[A-Za-z]{3}/.test(t) && !/[\u0600-\u06ff]/.test(t) && !allow.test(t.trim());

(async () => {
    const browser = await chromium.launch({headless: true, executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'});
    const contexts = {};
    const pageFor = async (email) => {
        if (contexts[email]) return contexts[email];
        const context = await browser.newContext({viewport: {width: 1440, height: 1000}, serviceWorkers: 'block'});
        await context.route('**/*', r => new URL(r.request().url()).origin === base ? r.continue() : r.abort());
        const page = await context.newPage();
        page.setDefaultTimeout(90000);
        page.on('pageerror', e => errors.push({user: email, url: page.url(), message: e.message}));
        page.on('response', r => { if (r.status() >= 400) failedResources.push({user: email, url: r.url(), status: r.status()}); });
        await page.goto(base + '/login');
        await page.locator('#email').fill(email);
        await page.locator('#password').fill('password'); // Synthetic fixture only.
        await Promise.all([page.waitForURL('**/admin/dashboard'), page.locator('button[type=submit]').click()]);
        contexts[email] = page;
        return page;
    };
    const setLocale = async (page, locale) => {
        await page.goto(base + '/admin/dashboard');
        const current = await page.locator('#interface-locale').inputValue();
        if (current === locale) return;
        await page.locator('#interface-locale').selectOption(locale);
        await Promise.all([page.waitForNavigation(), page.locator('form.locale-switch button').click()]);
        assert.equal(await page.locator('html').getAttribute('dir'), locale === 'ar' ? 'rtl' : 'ltr');
    };
    const check = async (id, fn) => {
        try { const detail = await fn(); results.push({id, status: 'PASS', detail: detail === undefined ? null : detail}); }
        catch (e) { results.push({id, status: 'FAIL', detail: e.message.split('\n')[0].slice(0, 400)}); }
    };
    const size = page => page.evaluate(() => ({viewport: innerWidth, document: document.documentElement.scrollWidth}));
    const noOverflow = async page => { const s = await size(page); assert.ok(s.document <= s.viewport + 1, 'horizontal overflow ' + JSON.stringify(s)); return s; };
    const scanArabic = async (page, key) => {
        const texts = await page.locator('.page label:visible, .page th:visible, .page button:visible, .page .btn:visible, .page h2:visible, .page h3:visible, .page .page-title:visible, .page .small:visible, .page p:visible, .page dt:visible, .page legend:visible, .sidebar a:visible, .topbar .btn:visible').evaluateAll(els =>
            els.map(e => ({tag: e.tagName.toLowerCase() + (e.classList.contains('btn') ? '.btn' : ''), text: e.innerText.trim().replace(/\s+/g, ' ')})));
        const bad = texts.filter(t => latinOnly(t.text));
        const actions = bad.filter(t => /^button|\.btn$/.test(t.tag));
        untranslated[key] = [...new Set(bad.map(t => t.tag + ': ' + t.text))].slice(0, 40);
        return actions.map(t => t.text);
    };
    // Responsive sweep of one route in one locale, at every width.
    const sweep = async (page, user, name, route, locale, extra = async () => {}) => {
        for (const width of WIDTHS) {
            await check(`${name}-${locale}-${width}`, async () => {
                await page.setViewportSize({width, height: 900});
                const response = await page.goto(base + route);
                assert.equal(response.status(), 200, 'HTTP ' + response.status());
                assert.equal(await page.locator('html').getAttribute('dir'), locale === 'ar' ? 'rtl' : 'ltr');
                await page.waitForLoadState('networkidle');
                const s = await noOverflow(page);
                if (locale === 'ar') {
                    const actions = await scanArabic(page, `${name}-${locale}-${width}`);
                    assert.deepEqual(actions, [], 'untranslated priority actions: ' + actions.join(' | '));
                }
                await extra(width);
                if (width === 390 || width === 1440) await page.screenshot({path: path.join(shots, `${name}-${locale}-${width}.png`), fullPage: true});
                return s;
            });
        }
        await page.setViewportSize({width: 1440, height: 1000});
    };

    try {
        const admin = await pageFor('admin@example.com');
        await setLocale(admin, 'en');

        // ---------- Topbar / locale ----------
        await check('topbar-admin-en-approvals-link-no-decorative-icons', async () => {
            await admin.goto(base + '/admin/dashboard');
            assert.ok(await admin.locator('.topbar a[href$="/admin/my-approvals"]').isVisible(), 'My Approvals shortcut missing');
            assert.equal(await admin.locator('.topbar span[title="Notifications"], .topbar span[title="Search"], .topbar span[title="Settings"]').count(), 0);
            assert.ok(await admin.locator('.topbar a[href*="user-guide"]').isVisible());
        });
        await check('locale-switch-ar-persists', async () => {
            await setLocale(admin, 'ar');
            await admin.goto(base + '/admin/equipment');
            assert.equal(await admin.locator('html').getAttribute('dir'), 'rtl');
            assert.equal(await admin.locator('html').getAttribute('lang'), 'ar');
            assert.match(await admin.locator('.topbar a[href$="/admin/my-approvals"]').innerText(), /[\u0600-\u06ff]/);
        });
        await check('locale-switch-back-to-en', async () => {
            await setLocale(admin, 'en');
            await admin.goto(base + '/admin/equipment');
            assert.equal(await admin.locator('html').getAttribute('dir'), 'ltr');
        });

        // ---------- Equipment: Add (EN) ----------
        let assetUrl = null;
        const entered = {asset_code: ASSET_CODE, name: 'QA Integration Excavator', asset_type: 'equipment', ownership_type: 'owned', meter_type: 'hours', current_meter: '120', make: 'Caterpillar', model: '320D', year: '2021', serial_number: 'SN-320D-QA', tracker_provider: 'Not connected yet', notes: 'Integration QA record (synthetic fixture).'};
        await check('equipment-add-en-creates-asset', async () => {
            await admin.goto(base + '/admin/equipment/create');
            for (const [k, v] of Object.entries(entered)) {
                const el = admin.locator(`[name="${k}"]`);
                if ((await el.evaluate(e => e.tagName)) === 'SELECT') await el.selectOption(v); else await el.fill(v);
            }
            await Promise.all([admin.waitForNavigation(), admin.locator('button[name=_save_action][value=close]').click()]);
            assert.equal(await admin.locator('.field-error, .alert-danger, .errors').count(), 0, await admin.locator('.field-error').allInnerTexts().then(t => t.join(' | ')));
            const link = admin.locator(`a:has-text("${ASSET_CODE}")`).first();
            if (!(await link.count())) { await admin.goto(base + '/admin/equipment?search=' + ASSET_CODE); }
            assetUrl = new URL(await admin.locator(`a:has-text("${ASSET_CODE}")`).first().getAttribute('href'), base).pathname;
            assert.match(assetUrl, /\/admin\/equipment\/\d+$/);
            return assetUrl;
        });
        // Only the record's own form (never the topbar locale switch); numbers compared by value.
        const readForm = page => page.locator('.page form [name]').evaluateAll(els => Object.fromEntries(els.filter(e => e.name && !/^_|token|^locale$/.test(e.name)).map(e => [e.name, e.type === 'checkbox' ? (e.checked ? '1' : '0') : (e.type === 'number' && e.value !== '' ? String(Number(e.value)) : e.value)])));
        let enEdit = null, enOptions = null;
        const optionSets = page => page.locator('select[name]').evaluateAll(els => Object.fromEntries(els.map(e => [e.name, [...e.options].map(o => o.value)])));
        await check('equipment-edit-en-holds-entered-values', async () => {
            await admin.goto(base + assetUrl + '/edit');
            enEdit = await readForm(admin);
            enOptions = await optionSets(admin);
            for (const [k, v] of Object.entries(entered)) assert.equal(String(enEdit[k]), v, k);
            return Object.keys(enEdit).length + ' fields';
        });
        let enShowOptions = null;
        await check('equipment-view-en-sections', async () => {
            await admin.goto(base + assetUrl);
            for (const id of ['overview', 'assignment', 'fuel', 'add-fuel', 'maintenance', 'add-maintenance', 'costs', 'documents', 'tracking', 'activity']) assert.ok(await admin.locator('#' + id).count(), 'section #' + id);
            enShowOptions = await optionSets(admin);
            assert.deepEqual(enShowOptions.payment_type, ['Cash', 'Bank', 'Supplier Credit']);
            assert.ok(enShowOptions.document_type.length >= 3 && enShowOptions.maintenance_type.length >= 2);
            return {document_type: enShowOptions.document_type, maintenance_type: enShowOptions.maintenance_type};
        });
        for (const [name, route] of [['equipment-register', '/admin/equipment'], ['equipment-add', '/admin/equipment/create'], ['equipment-edit', assetUrl + '/edit'], ['equipment-view', assetUrl]]) await sweep(admin, 'admin', name, route, 'en');

        // ---------- Equipment: Arabic ----------
        await setLocale(admin, 'ar');
        await check('equipment-edit-ar-same-values-and-option-values', async () => {
            await admin.goto(base + assetUrl + '/edit');
            const arEdit = await readForm(admin), arOptions = await optionSets(admin);
            assert.deepEqual(arEdit, enEdit, 'edit form values differ between locales');
            assert.deepEqual(arOptions, enOptions, 'select option values differ between locales');
            const arLabels = await admin.locator('form label:visible').allInnerTexts();
            assert.ok(arLabels.filter(t => /[\u0600-\u06ff]/.test(t)).length >= arLabels.length - 2, 'Arabic labels: ' + arLabels.join(' | '));
            return Object.keys(arEdit).length + ' fields identical';
        });
        await check('equipment-view-ar-sections-gps-not-connected-same-option-values', async () => {
            await admin.goto(base + assetUrl);
            for (const id of ['overview', 'assignment', 'fuel', 'add-fuel', 'maintenance', 'add-maintenance', 'costs', 'documents', 'tracking', 'activity']) assert.ok(await admin.locator('#' + id).count(), 'section #' + id);
            assert.deepEqual(await optionSets(admin), enShowOptions, 'operation form option values differ between locales');
            const tracking = await admin.locator('#tracking').innerText();
            assert.match(tracking, /غير متصل/);
            assert.ok((await admin.locator('.page').innerText()).includes(entered.name), 'asset name shown unchanged');
            return tracking.slice(0, 120);
        });
        await check('equipment-ar-fuel-and-maintenance-forms-submit-values-unchanged', async () => {
            await admin.goto(base + assetUrl);
            const names = await admin.locator('#add-fuel [name], #add-maintenance [name]').evaluateAll(els => [...new Set(els.map(e => e.name))].sort());
            for (const n of ['fuel_date', 'quantity', 'rate', 'payment_type', 'service_date', 'maintenance_type', 'cost']) assert.ok(names.includes(n), n);
            return names;
        });
        for (const [name, route] of [['equipment-register', '/admin/equipment'], ['equipment-add', '/admin/equipment/create'], ['equipment-edit', assetUrl + '/edit'], ['equipment-view', assetUrl]]) await sweep(admin, 'admin', name, route, 'ar');

        // ---------- Equipment: scoped visibility (assign to project 3 / site 4) ----------
        await setLocale(admin, 'en');
        await check('equipment-assign-to-project-3-site-4', async () => {
            await admin.goto(base + assetUrl);
            const form = admin.locator('form[action$="/assign"]');
            await form.locator('[name=project_id]').selectOption('3');
            if (await form.locator('[name=site_id]').count()) await form.locator('[name=site_id]').selectOption('4');
            await form.locator('[name=assigned_from]').fill(stamp());
            await Promise.all([admin.waitForNavigation(), form.locator('button[type=submit]').click()]);
            const errs = await admin.locator('.field-error').allInnerTexts();
            assert.deepEqual(errs, [], errs.join(' | '));
            assert.match(await admin.locator('#assignment').innerText(), /Dammam Road/);
        });
        await check('equipment-restricted-site-user-sees-only-scoped-asset', async () => {
            const zafar = await pageFor('zafar@example.com');
            await zafar.goto(base + '/admin/equipment');
            assert.ok((await zafar.locator('.page').innerText()).includes(ASSET_CODE), 'site-4 user should see the asset assigned to site 4');
            const nabeel = await pageFor('nabeel@example.com');
            await nabeel.goto(base + '/admin/equipment');
            const status = (await nabeel.goto(base + '/admin/equipment')).status();
            if (status === 200) assert.ok(!(await nabeel.locator('.page').innerText()).includes(ASSET_CODE), 'project-1 user must not see a project-3 asset');
            return {nabeelStatus: status};
        });

        // ---------- Approvals: create a pending runtime task on PR-2026-0007 ----------
        await check('approvals-submit-pr-7-for-runtime-approval', async () => {
            await admin.goto(base + '/admin/inventory/purchase-requests/7');
            const form = admin.locator('form[action$="/submit-approval"]');
            if (await form.count()) {
                await form.locator('select[name=approval_workflow_id]').selectOption('2');
                await Promise.all([admin.waitForNavigation(), form.locator('button[type=submit]').click()]);
            }
            const text = await admin.locator('.page').innerText();
            assert.match(text, /Approval requested|Attempt 1: Pending/i, text.slice(0, 300));
        });
        await check('approvals-admin-project-filter-lists-all-three', async () => {
            await admin.goto(base + '/admin/my-approvals');
            const opts = (await optionSets(admin)).project;
            assert.deepEqual(opts, ['', '3', '2', '1'], 'company-level actor: all projects, ordered by name');
        });
        const nabeel = await pageFor('nabeel@example.com');
        await setLocale(nabeel, 'en');
        await check('approvals-nabeel-en-scoped-project-filter-and-action-links', async () => {
            await nabeel.goto(base + '/admin/my-approvals');
            const opts = await optionSets(nabeel);
            // Nabeel is project-level (home project 1) but is manager_id of all three fixture projects, so the scope service resolves all three.
            assert.deepEqual(opts.project, ['', '3', '2', '1'], 'project filter must list exactly the projects the scope service resolves for the user');
            assert.deepEqual(opts.status, ['pending']);
            assert.deepEqual(opts.module, ['', 'Purchase Requests', 'Site Expenses']);
            const row = nabeel.locator('tbody tr', {hasText: 'PR-2026-0007'});
            assert.equal(await row.count(), 1, 'pending task row');
            const links = await row.locator('a').evaluateAll(as => as.map(a => [a.textContent.trim(), new URL(a.href).pathname]));
            assert.deepEqual(links.map(l => l[0]), ['PR-2026-0007', 'View', 'Review & approve', 'Review & reject']);
            assert.ok(links.every(l => l[1] === '/admin/inventory/purchase-requests/7'));
            return links;
        });
        await check('approvals-nabeel-filter-by-module-and-project', async () => {
            await nabeel.goto(base + '/admin/my-approvals?module=Purchase+Requests&project=1&status=pending');
            assert.equal(await nabeel.locator('tbody tr', {hasText: 'PR-2026-0007'}).count(), 1);
            assert.equal(await nabeel.locator('select[name=module]').inputValue(), 'Purchase Requests');
            assert.equal(await nabeel.locator('select[name=project]').inputValue(), '1');
            const forged = await nabeel.goto(base + '/admin/my-approvals?project=3');
            assert.equal(await nabeel.locator('tbody tr', {hasText: 'PR-2026-0007'}).count(), 0, 'a project outside scope must not match');
            return {forgedStatus: forged.status()};
        });
        await sweep(nabeel, 'nabeel', 'my-approvals', '/admin/my-approvals', 'en');
        await setLocale(nabeel, 'ar');
        await check('approvals-nabeel-ar-labels-and-same-submit-values', async () => {
            await nabeel.goto(base + '/admin/my-approvals');
            const opts = await optionSets(nabeel);
            assert.deepEqual(opts.project, ['', '3', '2', '1']);
            assert.deepEqual(opts.module, ['', 'Purchase Requests', 'Site Expenses']);
            const labels = await nabeel.locator('.page form label').allInnerTexts();
            assert.ok(labels.every(t => /[\u0600-\u06ff]/.test(t)), labels.join(' | '));
            const row = nabeel.locator('tbody tr', {hasText: 'PR-2026-0007'});
            const links = await row.locator('a').allInnerTexts();
            assert.ok(links.slice(1).every(t => /[\u0600-\u06ff]/.test(t)), links.join(' | '));
            assert.match(await nabeel.locator('thead').innerText(), /[\u0600-\u06ff]/);
            return links;
        });
        await sweep(nabeel, 'nabeel', 'my-approvals', '/admin/my-approvals', 'ar');
        await check('approvals-zafar-site-user-sees-only-project-3-and-no-task', async () => {
            const zafar = await pageFor('zafar@example.com');
            await zafar.goto(base + '/admin/my-approvals');
            const opts = await optionSets(zafar);
            assert.deepEqual(opts.project, ['', '3']);
            assert.equal(await zafar.locator('tbody tr', {hasText: 'PR-2026-0007'}).count(), 0);
        });
        await check('topbar-hr-user-without-approval-modules-has-no-shortcut', async () => {
            const waleed = await pageFor('waleed@example.com');
            await waleed.goto(base + '/admin/dashboard');
            assert.equal(await waleed.locator('.topbar a[href$="/admin/my-approvals"]').count(), 0);
            const r = await waleed.goto(base + '/admin/my-approvals');
            assert.equal(r.status(), 403);
        });

        // ---------- Coming Soon ----------
        for (const module of ['hr-reports', 'project-reports', 'settings']) {
            await setLocale(admin, 'en');
            await check(`coming-soon-${module}-en`, async () => {
                await admin.goto(base + '/admin/coming-soon/' + module);
                const text = await admin.locator('.coming-soon').innerText();
                assert.match(text, /Coming Soon/);
                assert.doesNotMatch(text, /Phase 1|Expected in/);
                assert.ok(await admin.locator('.coming-soon a[href$="/admin/dashboard"]').isVisible());
                return text.split('\n')[0];
            });
            await sweep(admin, 'admin', 'coming-soon-' + module, '/admin/coming-soon/' + module, 'en');
            await setLocale(admin, 'ar');
            await check(`coming-soon-${module}-ar`, async () => {
                await admin.goto(base + '/admin/coming-soon/' + module);
                const text = await admin.locator('.coming-soon').innerText();
                assert.match(text, /غير متاح بعد/);
                assert.match(text, /العودة إلى لوحة التحكم/);
                assert.doesNotMatch(text, /Phase 1|Coming Soon/);
                // The module name must come from the sidebar's translated label, not a title-cased slug.
                assert.doesNotMatch(await admin.locator('.coming-soon h2').innerText(), /[A-Za-z]{3}/, 'module title still English');
                assert.doesNotMatch(await admin.locator('.breadcrumb').innerText(), /Hr Reports|Project Reports|Settings/);
                return text.split('\n')[0];
            });
            await sweep(admin, 'admin', 'coming-soon-' + module, '/admin/coming-soon/' + module, 'ar');
        }

        // ---------- Sales invoice VAT controls ----------
        const fillInvoice = async (page, {vat, reason, forceEditable}) => {
            await page.goto(base + '/admin/accounting/accounts-receivable/create');
            await page.locator('select[name=customer_id]').selectOption('1');
            await page.locator('[name="lines[0][description]"]').fill('Integration QA line');
            await page.locator('[name="lines[0][quantity]"]').fill('2');
            await page.locator('[name="lines[0][unit_price]"]').fill('100');
            if (forceEditable) await page.locator('[name=vat_rate]').evaluate(e => e.removeAttribute('readonly'));
            if (forceEditable || (await page.locator('[name=vat_rate]').getAttribute('readonly')) === null) await page.locator('[name=vat_rate]').fill(String(vat));
            else assert.equal(await page.locator('[name=vat_rate]').inputValue(), String(vat), 'read-only rate must already hold the requested value');
            if (reason !== undefined && await page.locator('[name=vat_override_reason]').count()) await page.locator('[name=vat_override_reason]').fill(reason);
            await Promise.all([page.waitForNavigation(), page.locator('button[name=_save_action][value=close], form button.btn.primary[type=submit]').first().click()]);
        };
        await setLocale(admin, 'en');
        await check('sales-vat-superadmin-en-controls', async () => {
            await admin.goto(base + '/admin/accounting/accounts-receivable/create');
            const rate = admin.locator('[name=vat_rate]');
            assert.equal(await rate.inputValue(), '15');
            assert.equal(await rate.getAttribute('readonly'), null, 'Super Admin rate must be editable');
            assert.equal(await admin.locator('[name=vat_override_reason]').count(), 1);
            assert.match(await admin.locator('.page').innerText(), /manual override and needs a reason/);
        });
        await check('sales-vat-superadmin-override-without-reason-refused', async () => {
            await fillInvoice(admin, {vat: 5, reason: ''});
            assert.match(await admin.locator('.page').innerText(), /Enter a reason \(up to 500 characters\)/);
            assert.ok(admin.url().endsWith('/accounts-receivable/create') || admin.url().includes('/accounts-receivable'));
        });
        await check('sales-vat-superadmin-override-with-reason-saved', async () => {
            await fillInvoice(admin, {vat: 5, reason: 'Integration QA: contractual 5% (synthetic fixture)'});
            assert.equal(await admin.locator('.field-error').count(), 0, (await admin.locator('.field-error').allInnerTexts()).join(' | '));
            assert.doesNotMatch(await admin.locator('.page').innerText(), /Enter a reason/);
            return admin.url();
        });
        const zubair = await pageFor('zubair@example.com');
        await setLocale(zubair, 'en');
        await check('sales-vat-accounts-manager-rate-readonly-no-reason-field', async () => {
            await zubair.goto(base + '/admin/accounting/accounts-receivable/create');
            const rate = zubair.locator('[name=vat_rate]');
            assert.equal(await rate.inputValue(), '15');
            assert.equal(await rate.getAttribute('readonly'), '', 'non-Super Admin rate must be read-only');
            assert.equal(await zubair.locator('[name=vat_override_reason]').count(), 0);
            assert.match(await zubair.locator('.page').innerText(), /Only a Super Admin can override it/);
        });
        await check('sales-vat-accounts-manager-forged-5pct-refused-server-side', async () => {
            await fillInvoice(zubair, {vat: 5, forceEditable: true});
            assert.match(await zubair.locator('.page').innerText(), /Only a Super Admin may override the standard 15% sales VAT rate/);
        });
        await check('sales-vat-accounts-manager-standard-15-saves', async () => {
            await fillInvoice(zubair, {vat: 15});
            assert.equal(await zubair.locator('.field-error').count(), 0, (await zubair.locator('.field-error').allInnerTexts()).join(' | '));
            assert.doesNotMatch(await zubair.locator('.page').innerText(), /Only a Super Admin may override/);
            return zubair.url();
        });
        await setLocale(zubair, 'ar');
        await check('sales-vat-ar-labels-and-forged-refusal-arabic', async () => {
            await zubair.goto(base + '/admin/accounting/accounts-receivable/create');
            assert.match(await zubair.locator('label[for=vat_rate]').innerText(), /[\u0600-\u06ff]/);
            assert.match(await zubair.locator('.page').innerText(), /لا يمكن تجاوزها إلا للمسؤول الأعلى/);
            await fillInvoice(zubair, {vat: 0, forceEditable: true});
            assert.match(await zubair.locator('.page').innerText(), /لا يمكن تجاوز نسبة ضريبة المبيعات القياسية 15% إلا للمسؤول الأعلى/);
        });
        await sweep(zubair, 'zubair', 'sales-invoice-create', '/admin/accounting/accounts-receivable/create', 'ar');
        await setLocale(zubair, 'en');
        await sweep(zubair, 'zubair', 'sales-invoice-create', '/admin/accounting/accounts-receivable/create', 'en');
        await setLocale(admin, 'ar');
        await sweep(admin, 'admin', 'sales-invoice-create-superadmin', '/admin/accounting/accounts-receivable/create', 'ar');
        await setLocale(admin, 'en');
    } catch (e) {
        results.push({id: 'harness', status: 'FAIL', detail: e.stack.slice(0, 1200)});
    } finally {
        await browser.close();
    }
    const summary = {
        generated_at: new Date().toISOString(), fixture: path.basename(root), base, asset_code: ASSET_CODE,
        totals: {checks: results.length, pass: results.filter(r => r.status === 'PASS').length, fail: results.filter(r => r.status === 'FAIL').length, page_errors: errors.length, failed_resources: failedResources.length},
        results, page_errors: errors, failed_resources: failedResources, untranslated_text_by_screen: untranslated,
    };
    fs.writeFileSync(path.join(out, 'integration-browser-results.json'), JSON.stringify(summary, null, 2));
    console.log(JSON.stringify(summary.totals));
    for (const r of results.filter(r => r.status === 'FAIL')) console.log('FAIL', r.id, '-', r.detail);
    process.exitCode = summary.totals.fail ? 1 : 0;
})();
