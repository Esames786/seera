// Arabic GAP-02 verification (Customer Invoice form + Equipment asset page help text).
// Real Laravel browser run on the private, synthetic UAT fixture only; not client sign-off.
// node tests/manual/arabic-gap02-browser.cjs <playwright-module> <fixture-root> [screenshot-dir]
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
const shots = path.resolve(process.argv[4] || path.join(root, 'gap02-shots'));
fs.mkdirSync(shots, {recursive: true});
const out = path.resolve('docs/release-readiness/evidence/arabic-gap02-2026-10-28');
fs.mkdirSync(out, {recursive: true});
const WIDTHS = [320, 390, 768, 1440];
const results = [], errors = [], failedResources = [], untranslated = {};
const AR = /[\u0600-\u06ff]/;
const allow = /^(SAR|GPS|IBAN|VAT|ZATCA|QR|KSA|PR|PO|GRN|CAT|km|h|KM|ID|PDF|CSV|XML|OK|UTC)$|^[A-Z]{1,3}-?[\d-]+$|^\d/;
const latinOnly = t => /[A-Za-z]{3}/.test(t) && !AR.test(t) && !allow.test(t.trim());

// English copy that must stay byte-identical (fallback unchanged).
const EN_INVOICE = {
    labels: ['Customer *', 'Invoice Number', 'Invoice Date *', 'Due Date', 'Project', 'Cost Center', 'Notes'],
    headers: ['Item / Description *', 'Qty', 'Unit Price', 'Revenue Account', 'Cost Center'],
    sections: ['Invoice Information', 'Invoice Lines'],
    help: ['Required for a standard tax invoice (KSA-5).', 'Taxable amount, VAT and total are calculated from quantity × unit price and the invoice VAT rate. Rows without a description or unit price are ignored.'],
    placeholder: 'Auto-generated if left blank',
    title: 'The revenue account this line is credited to. Leave on the default for project work; pick Service Revenue for services.',
};
const AR_INVOICE = {
    labels: ['العميل *', 'رقم الفاتورة', 'تاريخ الفاتورة *', 'تاريخ الاستحقاق', 'مركز التكلفة'],
    headers: ['الصنف / الوصف *', 'الكمية', 'سعر الوحدة', 'حساب الإيرادات', 'مركز التكلفة'],
    sections: ['بيانات الفاتورة', 'بنود الفاتورة'],
    help: ['مطلوب للفاتورة الضريبية القياسية (KSA-5).', 'تُحسب القيمة الخاضعة للضريبة والضريبة والإجمالي من الكمية × سعر الوحدة ونسبة ضريبة الفاتورة. يتم تجاهل البنود دون وصف أو سعر.'],
    placeholder: 'يُنشأ تلقائياً إذا تُرك فارغاً',
    title: 'حساب الإيرادات الدائن لهذا البند. اترك الافتراضي لأعمال المشاريع واختر إيرادات الخدمات للخدمات.',
};
const EN_EQUIPMENT = [
    'Readings from assignments, fuel and maintenance only move it forward; a lower reading needs a logged correction.',
    'One active assignment per asset. Reassigning closes the current one and keeps it in the history.',
    'Total = quantity × rate (server-calculated). A fuel entry becomes project cost only when Finance posts it (Cash / Bank journal) or the supplier bill it creates is posted.',
    'Open → Completed (or Cancelled). Completed records stay in the history; cost is posted by Finance after completion.',
    'Equipment cost reaches the Project Cost Report and the project budget only through posted accounting (Cash / Bank journal or the posted supplier bill), never from the operational totals below.',
    'Private files (registration, insurance, inspection, permits, invoices, receipts). Served only to authorized users; no public links.',
    'Live GPS tracking is not connected to a telematics provider. No position, route, speed, idle time or geofence event is recorded or shown for this asset.',
    'Project / site assignment, operator, meter readings from assignments, fuel and maintenance, and the site geofence settings used by mobile attendance. The provider integration contract is documented for the next phase.',
    'Equipment Phase 1: asset master, assignment history, fuel, maintenance, private documents and accounting through Expense Categories and the Finance payment catalogue. Live GPS / routes / speed / idle time, utilization analytics and depreciation are NOT YET OPERATIONAL.',
];
const AR_EQUIPMENT = [
    'قراءات التخصيص والوقود والصيانة تزيد العداد فقط؛ تخفيض القراءة يتطلب تصحيحاً موثقاً.',
    'الإجمالي = الكمية × السعر (محسوب من الخادم). لا تصبح كمية الوقود تكلفة للمشروع إلا بعد ترحيل المالية بقيد نقدي/بنكي أو ترحيل فاتورة المورد المرتبطة.',
    'مفتوح ← مكتمل (أو ملغى). تبقى السجلات المكتملة في التاريخ ولا تُرحّل التكلفة إلا بواسطة المالية بعد الإكمال.',
    'تدخل تكلفة المعدات في تقرير تكاليف المشروع وميزانيته فقط عبر القيود المحاسبية المرحلة أو فاتورة المورد المرحلة، وليس من المجاميع التشغيلية أدناه.',
    'ملفات خاصة للتسجيل والتأمين والفحص والتصاريح والفواتير والإيصالات؛ لا تُعرض إلا للمخولين ولا توجد روابط عامة.',
    'التتبع المباشر GPS غير متصل بمزود خدمة. لا يُسجل أو يُعرض موقع أو مسار أو سرعة أو توقف أو حدث سياج جغرافي لهذا الأصل.',
    'المتاح حالياً: تخصيص المشروع والموقع والمشغل وقراءات العداد من التخصيص والوقود والصيانة، وإعدادات السياج الجغرافي لحضور الجوال. الربط بمزود التتبع موثق لمرحلة لاحقة.',
    'المرحلة الأولى من المعدات: بيانات الأصل وتاريخ التخصيصات والوقود والصيانة والمستندات الخاصة والمحاسبة عبر فئات المصروف وطرق الدفع. التتبع المباشر والمسارات والسرعة والتوقف وتحليل الاستخدام والإهلاك غير تشغيلية بعد.',
];

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
    const pageText = page => page.locator('.page').innerText();
    // Every reader-facing text node in the record area, classified; Latin-only ones are reported.
    const scanArabic = async (page, key) => {
        const texts = await page.locator('.page label:visible, .page th:visible, .page button:visible, .page .btn:visible, .page h2:visible, .page h3:visible, .page .page-title:visible, .page .section-title:visible, .page .table-title:visible, .page .small:visible, .page p:visible, .page .note:visible, .page .table-empty:visible, .page option, .sidebar a:visible, .topbar .btn:visible').evaluateAll(els =>
            els.map(e => ({tag: e.tagName.toLowerCase() + (e.classList.contains('btn') ? '.btn' : ''), text: (e.tagName === 'OPTION' ? e.textContent : e.innerText).trim().replace(/\s+/g, ' ')})));
        const bad = texts.filter(t => latinOnly(t.text));
        const actions = bad.filter(t => /^button|\.btn$/.test(t.tag));
        untranslated[key] = [...new Set(bad.map(t => t.tag + ': ' + t.text))].slice(0, 60);
        return actions.map(t => t.text);
    };
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
    // Only the record's own form (never the topbar locale switch); numbers compared by value.
    const readForm = page => page.locator('.page form [name]').evaluateAll(els => Object.fromEntries(els.filter(e => e.name && !/^_|token|^locale$/.test(e.name)).map(e => [e.name, e.type === 'checkbox' ? (e.checked ? '1' : '0') : (e.type === 'number' && e.value !== '' ? String(Number(e.value)) : e.value)])));
    const optionSets = page => page.locator('.page select[name]').evaluateAll(els => Object.fromEntries(els.map(e => [e.name, [...e.options].map(o => o.value)])));
    const formNames = page => page.locator('.page form [name]').evaluateAll(els => [...new Set(els.map(e => e.name).filter(n => n && !/^_|token|^locale$/.test(n)))].sort());
    const invoiceTexts = async page => ({
        labels: await page.locator('.page form label').allInnerTexts().then(t => t.map(x => x.trim())),
        headers: await page.locator('.page form th').allInnerTexts().then(t => t.map(x => x.trim())),
        sections: await page.locator('.page .section-title').allInnerTexts().then(t => t.map(x => x.trim())),
        placeholder: await page.locator('[name=invoice_number]').getAttribute('placeholder'),
        title: await page.locator('[name="lines[0][revenue_account_id]"]').getAttribute('title'),
        defaultOption: await page.locator('[name="lines[0][revenue_account_id]"] option[value=""]').textContent().then(t => t.trim()),
        text: await pageText(page),
    });
    const INVOICE_CREATE = '/admin/accounting/accounts-receivable/create';
    const fillInvoice = async (page, {vat, reason, forceEditable}) => {
        await page.goto(base + INVOICE_CREATE);
        await page.locator('select[name=customer_id]').selectOption('1');
        await page.locator('[name="lines[0][description]"]').fill('GAP-02 QA line');
        await page.locator('[name="lines[0][quantity]"]').fill('2');
        await page.locator('[name="lines[0][unit_price]"]').fill('100');
        if (forceEditable) await page.locator('[name=vat_rate]').evaluate(e => e.removeAttribute('readonly'));
        if (forceEditable || (await page.locator('[name=vat_rate]').getAttribute('readonly')) === null) await page.locator('[name=vat_rate]').fill(String(vat));
        else assert.equal(await page.locator('[name=vat_rate]').inputValue(), String(vat), 'read-only rate must already hold the requested value');
        if (reason !== undefined && await page.locator('[name=vat_override_reason]').count()) await page.locator('[name=vat_override_reason]').fill(reason);
        await Promise.all([page.waitForNavigation(), page.locator('button[name=_save_action][value=close], form button.btn.primary[type=submit]').first().click()]);
    };

    try {
        const admin = await pageFor('admin@example.com');
        const zubair = await pageFor('zubair@example.com');

        // ---------- Customer Invoice create: English fallback ----------
        await setLocale(admin, 'en');
        let enInvoice = null, enForm = null, enOptions = null, enNames = null;
        await check('invoice-create-en-fallback-unchanged', async () => {
            await admin.goto(base + INVOICE_CREATE);
            enInvoice = await invoiceTexts(admin);
            enForm = await readForm(admin); enOptions = await optionSets(admin); enNames = await formNames(admin);
            for (const l of EN_INVOICE.labels) assert.ok(enInvoice.labels.includes(l), 'label ' + l + ' in ' + enInvoice.labels.join(' | '));
            for (const h of EN_INVOICE.headers) assert.ok(enInvoice.headers.includes(h), 'header ' + h);
            for (const s of EN_INVOICE.sections) assert.ok(enInvoice.sections.includes(s), 'section ' + s);
            for (const h of EN_INVOICE.help) assert.ok(enInvoice.text.includes(h), 'help ' + h.slice(0, 40));
            assert.equal(enInvoice.placeholder, EN_INVOICE.placeholder);
            assert.equal(enInvoice.title, EN_INVOICE.title);
            assert.match(enInvoice.defaultOption, /^Default: /);
            assert.equal(enForm.vat_rate, '15');
            return {fields: enNames.length, labels: enInvoice.labels.length, headers: enInvoice.headers.length};
        });

        // ---------- Customer Invoice create: Arabic ----------
        await setLocale(admin, 'ar');
        await check('invoice-create-ar-fields-headings-help-translated', async () => {
            await admin.goto(base + INVOICE_CREATE);
            const ar = await invoiceTexts(admin);
            for (const l of AR_INVOICE.labels) assert.ok(ar.labels.includes(l), 'label ' + l + ' in ' + ar.labels.join(' | '));
            for (const h of AR_INVOICE.headers) assert.ok(ar.headers.includes(h), 'header ' + h + ' in ' + ar.headers.join(' | '));
            for (const s of AR_INVOICE.sections) assert.ok(ar.sections.includes(s), 'section ' + s + ' in ' + ar.sections.join(' | '));
            for (const h of AR_INVOICE.help) assert.ok(ar.text.includes(h), 'help ' + h.slice(0, 40));
            assert.equal(ar.placeholder, AR_INVOICE.placeholder);
            assert.equal(ar.title, AR_INVOICE.title);
            assert.match(ar.defaultOption, /^الافتراضي: /);
            const latinLabels = ar.labels.filter(latinOnly), latinHeaders = ar.headers.filter(latinOnly), latinSections = ar.sections.filter(latinOnly);
            assert.deepEqual(latinLabels, [], 'English labels: ' + latinLabels.join(' | '));
            assert.deepEqual(latinHeaders, [], 'English headers: ' + latinHeaders.join(' | '));
            assert.deepEqual(latinSections, [], 'English sections: ' + latinSections.join(' | '));
            for (const l of EN_INVOICE.labels) assert.ok(!ar.labels.includes(l), 'English label still shown: ' + l);
            assert.equal(await admin.locator('.page h1.page-title').innerText().then(t => t.trim()), 'إضافة فاتورة عميل', 'page heading');
            assert.match(await admin.locator('.page p.page-desc').innerText(), /^سجّل فاتورة عميل\./, 'page description');
            assert.match(await admin.locator('.topbar').innerText(), /المحاسبة \/ الحسابات المدينة \/ إضافة فاتورة عميل/, 'breadcrumb');
            return {labels: ar.labels.length, headers: ar.headers.length, sections: ar.sections};
        });
        await check('invoice-create-ar-form-names-values-options-identical', async () => {
            await admin.goto(base + INVOICE_CREATE);
            assert.deepEqual(await formNames(admin), enNames, 'field names differ');
            assert.deepEqual(await readForm(admin), enForm, 'default values differ');
            assert.deepEqual(await optionSets(admin), enOptions, 'option values differ');
            return enNames;
        });
        await check('invoice-create-ar-superadmin-vat-controls-unchanged', async () => {
            await admin.goto(base + INVOICE_CREATE);
            const rate = admin.locator('[name=vat_rate]');
            assert.equal(await rate.inputValue(), '15');
            assert.equal(await rate.getAttribute('readonly'), null, 'Super Admin rate must be editable');
            assert.equal(await admin.locator('[name=vat_override_reason]').count(), 1);
            assert.match(await admin.locator('label[for=vat_override_reason]').innerText(), AR);
        });
        await sweep(admin, 'admin', 'invoice-create-superadmin', INVOICE_CREATE, 'ar');
        await setLocale(admin, 'en');
        await sweep(admin, 'admin', 'invoice-create-superadmin', INVOICE_CREATE, 'en');

        // ---------- Accounts Manager: VAT authorization unchanged in both locales ----------
        await setLocale(zubair, 'en');
        await check('invoice-create-en-accounts-manager-rate-readonly', async () => {
            await zubair.goto(base + INVOICE_CREATE);
            assert.equal(await zubair.locator('[name=vat_rate]').inputValue(), '15');
            assert.equal(await zubair.locator('[name=vat_rate]').getAttribute('readonly'), '', 'non-Super Admin rate must be read-only');
            assert.equal(await zubair.locator('[name=vat_override_reason]').count(), 0);
            assert.match(await pageText(zubair), /Only a Super Admin can override it/);
        });
        await check('invoice-create-en-accounts-manager-forged-5pct-refused', async () => {
            await fillInvoice(zubair, {vat: 5, forceEditable: true});
            assert.match(await pageText(zubair), /Only a Super Admin may override the standard 15% sales VAT rate/);
        });
        await setLocale(zubair, 'ar');
        await check('invoice-create-ar-accounts-manager-rate-readonly', async () => {
            await zubair.goto(base + INVOICE_CREATE);
            assert.equal(await zubair.locator('[name=vat_rate]').inputValue(), '15');
            assert.equal(await zubair.locator('[name=vat_rate]').getAttribute('readonly'), '', 'non-Super Admin rate must be read-only');
            assert.equal(await zubair.locator('[name=vat_override_reason]').count(), 0);
            assert.match(await pageText(zubair), /لا يمكن تجاوزها إلا للمسؤول الأعلى/);
            assert.deepEqual(await formNames(zubair), enNames.filter(n => n !== 'vat_override_reason'), 'field names differ');
        });
        await check('invoice-create-ar-accounts-manager-forged-5pct-refused', async () => {
            await fillInvoice(zubair, {vat: 5, forceEditable: true});
            assert.match(await pageText(zubair), /لا يمكن تجاوز نسبة ضريبة المبيعات القياسية 15% إلا للمسؤول الأعلى/);
        });
        let savedInvoiceUrl = null;
        await check('invoice-create-ar-accounts-manager-standard-15-saves', async () => {
            await fillInvoice(zubair, {vat: 15});
            assert.equal(await zubair.locator('.field-error').count(), 0, (await zubair.locator('.field-error').allInnerTexts()).join(' | '));
            assert.doesNotMatch(await pageText(zubair), /لا يمكن تجاوز نسبة/);
            savedInvoiceUrl = zubair.url();
            return savedInvoiceUrl;
        });
        await sweep(zubair, 'zubair', 'invoice-create-accounts-manager', INVOICE_CREATE, 'ar');

        // ---------- Customer Invoice edit (draft just saved) ----------
        let editPath = null;
        await check('invoice-edit-locate-draft', async () => {
            await zubair.goto(base + '/admin/accounting/accounts-receivable');
            const hrefs = await zubair.locator('.page a[href*="/accounts-receivable/"][href$="/edit"]').evaluateAll(els => els.map(e => e.getAttribute('href')));
            assert.ok(hrefs.length, 'no editable draft invoice in the fixture');
            editPath = new URL(hrefs[0], base).pathname;
            return editPath;
        });
        if (editPath) {
            await setLocale(zubair, 'en');
            let enEdit = null;
            await check('invoice-edit-en-values', async () => { await zubair.goto(base + editPath); enEdit = await readForm(zubair); assert.ok(Object.keys(enEdit).length > 10); return Object.keys(enEdit).length + ' fields'; });
            await setLocale(zubair, 'ar');
            await check('invoice-edit-ar-same-values-and-translated-labels', async () => {
                await zubair.goto(base + editPath);
                assert.deepEqual(await readForm(zubair), enEdit, 'edit form values differ between locales');
                const ar = await invoiceTexts(zubair);
                for (const l of AR_INVOICE.labels) assert.ok(ar.labels.includes(l), 'label ' + l);
                for (const h of AR_INVOICE.headers) assert.ok(ar.headers.includes(h), 'header ' + h);
                assert.equal(await zubair.locator('[name=vat_rate]').getAttribute('readonly'), '', 'rate must stay read-only on edit');
                assert.match(await zubair.locator('.page h1.page-title').innerText(), /^تعديل الفاتورة: INV-/, 'edit heading');
                assert.equal(await zubair.locator('.page p.page-desc').innerText().then(t => t.trim()), 'لا يمكن تعديل الفاتورة إلا وهي مسودة');
                assert.equal(await zubair.locator('.page .page-actions a.btn').innerText().then(t => t.trim()), 'عرض التفاصيل', 'View Details action');
            });
            await sweep(zubair, 'zubair', 'invoice-edit', editPath, 'ar');
            await setLocale(zubair, 'en');
            await sweep(zubair, 'zubair', 'invoice-edit', editPath, 'en');
        }

        // ---------- Equipment asset page: English fallback ----------
        await setLocale(admin, 'en');
        let assetPath = null;
        await check('equipment-locate-asset', async () => {
            await admin.goto(base + '/admin/equipment');
            const hrefs = await admin.locator('.page a[href*="/admin/equipment/"]').evaluateAll(els => els.map(e => e.getAttribute('href')));
            const show = hrefs.map(h => new URL(h, base).pathname).find(p => /\/admin\/equipment\/\d+$/.test(p));
            assert.ok(show, 'no equipment asset in the fixture');
            assetPath = show;
            return assetPath;
        });
        const helpTexts = async page => ({
            subtitles: await page.locator('.page .table-title .small').allInnerTexts().then(t => t.map(x => x.trim())),
            note: await page.locator('.page .note').innerText().then(t => t.trim()),
            tracking: await page.locator('#tracking').innerText().then(t => t.replace(/\s+/g, ' ').trim()),
            costs: await page.locator('#costs').innerText().then(t => t.replace(/\s+/g, ' ').trim()),
            overview: await page.locator('#overview').innerText().then(t => t.replace(/\s+/g, ' ').trim()),
            text: await pageText(page).then(t => t.replace(/\s+/g, ' ')),
        });
        let enAsset = null, enAssetOptions = null, enAssetNames = null;
        await check('equipment-view-en-fallback-unchanged', async () => {
            await admin.goto(base + assetPath);
            enAsset = await helpTexts(admin); enAssetOptions = await optionSets(admin); enAssetNames = await formNames(admin);
            for (const s of EN_EQUIPMENT) assert.ok(enAsset.text.includes(s), 'English help missing: ' + s.slice(0, 50));
            assert.match(enAsset.tracking, /Not connected/);
            return {subtitles: enAsset.subtitles.length, forms: enAssetNames.length};
        });

        // ---------- Equipment asset page: Arabic ----------
        await setLocale(admin, 'ar');
        await check('equipment-view-ar-help-and-gps-limitations-translated', async () => {
            await admin.goto(base + assetPath);
            const ar = await helpTexts(admin);
            for (const s of AR_EQUIPMENT) assert.ok(ar.text.includes(s), 'Arabic help missing: ' + s.slice(0, 50));
            for (const s of EN_EQUIPMENT) assert.ok(!ar.text.includes(s), 'English help still shown: ' + s.slice(0, 50));
            assert.match(ar.tracking, /غير متصل/);
            assert.doesNotMatch(ar.tracking, /Not connected|telematics/);
            assert.match(ar.note, /غير تشغيلية بعد/);
            const latinSubtitles = ar.subtitles.filter(latinOnly);
            assert.deepEqual(latinSubtitles, [], 'English subtitles: ' + latinSubtitles.join(' | '));
            const empties = await admin.locator('.page .table-empty').allInnerTexts().then(t => t.map(x => x.trim()).filter(Boolean));
            const latinEmpties = empties.filter(latinOnly);
            assert.deepEqual(latinEmpties, [], 'English empty-state rows: ' + latinEmpties.join(' | '));
            assert.match(ar.tracking, /لم يتم تسجيل جهاز تتبع\./, 'tracker readiness');
            const typeLabels = await admin.locator('select[name=document_type] option, select[name=maintenance_type] option').evaluateAll(els => els.map(e => e.textContent.trim()));
            const latinTypes = typeLabels.filter(latinOnly);
            assert.deepEqual(latinTypes, [], 'English document / maintenance type labels: ' + latinTypes.join(' | '));
            return {subtitles: ar.subtitles.length, empties: empties.length, typeLabels: typeLabels.length};
        });
        await check('equipment-view-ar-operation-forms-names-and-option-values-identical', async () => {
            await admin.goto(base + assetPath);
            assert.deepEqual(await formNames(admin), enAssetNames, 'form field names differ');
            assert.deepEqual(await optionSets(admin), enAssetOptions, 'option values differ');
            assert.deepEqual(enAssetOptions.payment_type, ['Cash', 'Bank', 'Supplier Credit']);
            return enAssetNames;
        });
        await sweep(admin, 'admin', 'equipment-view', assetPath, 'ar');
        await setLocale(admin, 'en');
        await sweep(admin, 'admin', 'equipment-view', assetPath, 'en');

        // ---------- Equipment permissions unchanged: site user without Financial Reports ----------
        const zafar = await pageFor('zafar@example.com');
        await setLocale(zafar, 'ar');
        await check('equipment-view-ar-site-user-cost-figures-hidden-by-permission', async () => {
            await zafar.goto(base + '/admin/equipment');
            const hrefs = await zafar.locator('.page a[href*="/admin/equipment/"]').evaluateAll(els => els.map(e => e.getAttribute('href')));
            const show = hrefs.map(h => new URL(h, base).pathname).find(p => /\/admin\/equipment\/\d+$/.test(p));
            assert.ok(show, 'site user sees no asset in the register');
            const r = await zafar.goto(base + show);
            assert.equal(r.status(), 200);
            const costs = await zafar.locator('#costs').innerText();
            assert.match(costs, /تظهر قيم التكلفة فقط للمستخدمين المخولين بعرض التقارير المالية/);
            assert.doesNotMatch(costs, /SAR|ر\.س|\d+\.\d\d/);
            return show;
        });
        await setLocale(zafar, 'en');
        await check('equipment-view-en-site-user-cost-figures-hidden-by-permission', async () => {
            await zafar.goto(base + '/admin/equipment');
            const hrefs = await zafar.locator('.page a[href*="/admin/equipment/"]').evaluateAll(els => els.map(e => e.getAttribute('href')));
            const show = hrefs.map(h => new URL(h, base).pathname).find(p => /\/admin\/equipment\/\d+$/.test(p));
            await zafar.goto(base + show);
            assert.match(await zafar.locator('#costs').innerText(), /Cost figures are shown to users with Financial Reports view\./);
        });
        await check('equipment-create-forbidden-for-project-manager-unchanged', async () => {
            const nabeel = await pageFor('nabeel@example.com');
            const r = await nabeel.goto(base + '/admin/equipment');
            assert.equal(r.status(), 403);
        });
    } catch (e) {
        results.push({id: 'harness', status: 'FAIL', detail: e.stack.slice(0, 1200)});
    } finally {
        await browser.close();
    }
    const summary = {
        run_at: new Date().toISOString(),
        branch: 'feature/seera-arabic-gap02-2026-10-28',
        fixture: 'private synthetic UAT fixture (not client data)',
        widths: WIDTHS,
        checks: results.length,
        passed: results.filter(r => r.status === 'PASS').length,
        failed: results.filter(r => r.status === 'FAIL').map(r => r.id),
        page_errors: errors,
        failed_resources: failedResources.filter(r => !/\/login$/.test(r.url)),
        untranslated_text_in_arabic_ui: Object.fromEntries(Object.entries(untranslated).filter(([, v]) => v.length)),
        results,
    };
    fs.writeFileSync(path.join(out, 'arabic-gap02-browser-results.json'), JSON.stringify(summary, null, 2));
    console.log(JSON.stringify({checks: summary.checks, passed: summary.passed, failed: summary.failed, page_errors: errors.length}));
})();
