// Accounts Receivable register + invoice detail Arabic verification.
// Real Laravel browser run on the private, synthetic UAT fixture only; not client sign-off.
// node tests/manual/ar-register-browser.cjs <playwright-module> <fixture-root> [screenshot-dir]
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
const shots = path.resolve(process.argv[4] || path.join(root, 'ar-register-shots'));
fs.mkdirSync(shots, {recursive: true});
const out = path.resolve('docs/release-readiness/evidence/ar-register-2026-10-29');
fs.mkdirSync(out, {recursive: true});
const WIDTHS = [320, 390, 768, 1440];
const results = [], errors = [], failedResources = [], untranslated = {};
const AR = /[\u0600-\u06ff]/;
const allow = /^(SAR|GPS|IBAN|VAT|ZATCA|QR|KSA|PR|PO|GRN|CAT|km|h|KM|ID|PDF|CSV|XML|OK|UTC|ICV|UUID|CSID|CR)$|^[A-Z]{1,3}-?[\d-]+$|^\d|^INV-/;
const latinOnly = t => /[A-Za-z]{3}/.test(t) && !AR.test(t) && !allow.test(t.trim());
const INDEX = '/admin/accounting/accounts-receivable';

// English copy that must stay byte-identical (fallback unchanged).
const EN_INDEX = {
    title: 'Accounts Receivable', desc: 'Customer invoices, output VAT, ZATCA status and receipts', add: '+ Add Customer Invoice',
    metrics: ['Outstanding Receivable', 'Overdue Invoices', 'Draft Invoices', 'Received This Month'],
    placeholder: 'Invoice number or customer...', options: ['All Customers', 'All Payment Status', 'All ZATCA Status'], reset: 'Reset',
    headers: ['Invoice', 'Customer', 'Invoice Date', 'Due Date', 'Taxable', 'VAT', 'Total', 'Received', 'Balance', 'ZATCA', 'Payment', 'Actions'],
    receiptHeaders: ['Date', 'Customer', 'Invoice', 'Amount', 'Reference'],
    note: 'Approving an invoice posts: debit accounts receivable, credit revenue and output VAT, and creates the ZATCA invoice record. Recording a receipt posts: debit cash/bank, credit accounts receivable.',
};
const AR_INDEX = {
    title: 'الحسابات المدينة', desc: 'فواتير العملاء وضريبة المخرجات وحالة الفوترة الإلكترونية والمقبوضات', add: '+ إضافة فاتورة عميل',
    metrics: ['الذمم المدينة المستحقة', 'الفواتير المتأخرة', 'الفواتير المسودة', 'المقبوضات هذا الشهر'],
    placeholder: 'رقم الفاتورة أو العميل...', options: ['كل العملاء', 'كل حالات الدفع', 'كل حالات زاتكا'],
    headers: ['الفاتورة', 'العميل', 'تاريخ الفاتورة', 'تاريخ الاستحقاق', 'الخاضع للضريبة', 'ضريبة القيمة المضافة', 'الإجمالي', 'المحصّل', 'الرصيد', 'زاتكا', 'الدفع', 'الإجراءات'],
    breadcrumb: 'المحاسبة / الحسابات المدينة',
};
const EN_SHOW = ['Invoice Number', 'Customer', 'Invoice Date', 'Due Date', 'Project', 'Cost Center', 'Subtotal (taxable)', 'Total', 'Received', 'Outstanding', 'Status', 'Local e-invoice state', 'Notes',
    'Description', 'Qty', 'Unit Price', 'Revenue Account', 'Taxable', 'VAT %', 'VAT', 'Line Total',
    'VAT Rate', 'Taxable Amount', 'Output VAT', 'Total incl. VAT', 'VAT treatment', 'Recorded in VAT ledger',
    'Invoice Total', 'Payment state', 'Note', 'Type', 'Date', 'Reason', 'ZATCA', 'Actions'];
const AR_SHOW = ['رقم الفاتورة', 'العميل', 'تاريخ الفاتورة', 'تاريخ الاستحقاق', 'المجموع الفرعي (الخاضع للضريبة)', 'المستحق', 'حالة الفاتورة الإلكترونية المحلية',
    'الكمية', 'سعر الوحدة', 'حساب الإيرادات', 'نسبة الضريبة %', 'إجمالي البند',
    'نسبة الضريبة', 'المبلغ الخاضع للضريبة', 'ضريبة المخرجات', 'الإجمالي شامل الضريبة', 'المعالجة الضريبية', 'مسجل في دفتر الضريبة',
    'إجمالي الفاتورة', 'حالة الدفع', 'زاتكا'];

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
    const scanArabic = async (page, key) => {
        const texts = await page.locator('.page label:visible, .page th:visible, .page button:visible, .page .btn:visible, .page h2:visible, .page h3:visible, .page .page-title:visible, .page .page-desc:visible, .page .section-title:visible, .page .table-title:visible, .page .small:visible, .page p:visible, .page .note:visible, .page .table-empty:visible, .page .metric .label:visible, .page dt:visible, .page option, .sidebar a:visible, .topbar .btn:visible').evaluateAll(els =>
            els.map(e => ({tag: e.tagName.toLowerCase() + (e.classList.contains('btn') ? '.btn' : ''), text: (e.tagName === 'OPTION' ? e.textContent : e.innerText).trim().replace(/\s+/g, ' ')})));
        const bad = texts.filter(t => latinOnly(t.text));
        const actions = bad.filter(t => /^button|\.btn$/.test(t.tag));
        untranslated[key] = [...new Set(bad.map(t => t.tag + ': ' + t.text))].slice(0, 60);
        return actions.map(t => t.text);
    };
    const sweep = async (page, user, name, route, locale, extra = async () => {}) => {
        for (const width of WIDTHS) {
            await check(`${name}-${user}-${locale}-${width}`, async () => {
                await page.setViewportSize({width, height: 900});
                const response = await page.goto(base + route);
                assert.equal(response.status(), 200, 'HTTP ' + response.status());
                assert.equal(await page.locator('html').getAttribute('dir'), locale === 'ar' ? 'rtl' : 'ltr');
                await page.waitForLoadState('networkidle');
                const s = await noOverflow(page);
                if (locale === 'ar') {
                    const actions = await scanArabic(page, `${name}-${user}-${locale}-${width}`);
                    assert.deepEqual(actions, [], 'untranslated priority actions: ' + actions.join(' | '));
                }
                await extra(width);
                if (width === 390 || width === 1440) await page.screenshot({path: path.join(shots, `${name}-${user}-${locale}-${width}.png`), fullPage: true});
                return s;
            });
        }
        await page.setViewportSize({width: 1440, height: 1000});
    };
    // Locale-independent view of a page: every table's numeric tokens per row, filter names / option values,
    // metric values, and every action (form action + method, link href) in the record area.
    const numbersOf = t => (t.match(/-?\d[\d,]*(?:\.\d+)?/g) || []).map(x => x.replace(/,/g, ''));
    const snapshot = page => page.evaluate(() => {
        const nums = t => (t.match(/-?\d[\d,]*(?:\.\d+)?/g) || []).map(x => x.replace(/,/g, ''));
        const tables = [...document.querySelectorAll('.page table')].map(t => ({
            id: t.closest('[id]')?.id || null,
            rows: [...t.querySelectorAll('tbody tr')].map(tr => [...tr.querySelectorAll('td')].map(td => nums(td.innerText).join(' '))),
        }));
        const forms = [...document.querySelectorAll('.page form')].map(f => ({action: f.getAttribute('action'), method: (f.querySelector('input[name=_method]')?.value || f.method || 'get').toUpperCase(),
            fields: [...f.querySelectorAll('[name]')].filter(e => !/^_|token/.test(e.name)).map(e => e.name + (e.tagName === 'SELECT' ? '=' + [...e.options].map(o => o.value).join(',') : '')).sort()}));
        const links = [...document.querySelectorAll('.page a.btn, .page .page-actions a, .page td a')].map(a => a.getAttribute('href')).filter(Boolean).map(h => h.replace(/^https?:\/\/[^/]+/, '')).sort();
        const metrics = [...document.querySelectorAll('.page .metric .value')].map(e => nums(e.innerText).join(' '));
        const dl = [...document.querySelectorAll('.page dl dd')].map(e => nums(e.innerText).join(' '));
        return {tables, forms, links, metrics, dl};
    });
    const rowsOf = page => page.locator('.page table').first().locator('tbody tr').evaluateAll(trs => trs.map(tr => tr.querySelector('td')?.innerText.trim()).filter(t => t && /^INV-/.test(t)));
    const showPath = n => `${INDEX}/${n}`;

    try {
        const admin = await pageFor('admin@example.com');
        const zubair = await pageFor('zubair@example.com');

        // ---------- Register: English fallback ----------
        await setLocale(admin, 'en');
        let enIndex = null;
        await check('register-en-fallback-unchanged', async () => {
            await admin.goto(base + INDEX);
            const text = await pageText(admin);
            assert.equal(await admin.locator('.page h1.page-title').innerText().then(t => t.trim()), EN_INDEX.title);
            assert.equal(await admin.locator('.page p.page-desc').innerText().then(t => t.trim()), EN_INDEX.desc);
            assert.equal(await admin.locator('.page-actions a.btn').first().innerText().then(t => t.trim()), EN_INDEX.add);
            assert.deepEqual(await admin.locator('.page .metric .label').allInnerTexts().then(t => t.map(x => x.trim())), EN_INDEX.metrics);
            assert.equal(await admin.locator('[name=search]').getAttribute('placeholder'), EN_INDEX.placeholder);
            for (const o of EN_INDEX.options) assert.ok(await admin.locator(`option:text-is("${o}")`).count(), 'option ' + o);
            const headers = await admin.locator('.page table').first().locator('thead th').allInnerTexts().then(t => t.map(x => x.trim()));
            assert.deepEqual(headers, EN_INDEX.headers);
            const rHeaders = await admin.locator('.page table').nth(1).locator('thead th').allInnerTexts().then(t => t.map(x => x.trim()));
            assert.deepEqual(rHeaders, EN_INDEX.receiptHeaders);
            assert.ok(text.includes(EN_INDEX.note), 'posting note');
            assert.match(text, /Showing \d+-\d+ of \d+/);
            enIndex = await snapshot(admin);
            return {rows: enIndex.tables[0].rows.length, metrics: enIndex.metrics};
        });

        // ---------- Register: Arabic ----------
        await setLocale(admin, 'ar');
        await check('register-ar-navigation-filters-tables-note-translated', async () => {
            await admin.goto(base + INDEX);
            const text = await pageText(admin);
            assert.equal(await admin.locator('.page h1.page-title').innerText().then(t => t.trim()), AR_INDEX.title);
            assert.equal(await admin.locator('.page p.page-desc').innerText().then(t => t.trim()), AR_INDEX.desc);
            assert.match(await admin.locator('.topbar').innerText(), new RegExp(AR_INDEX.breadcrumb), 'breadcrumb');
            assert.equal(await admin.locator('.page-actions a.btn').first().innerText().then(t => t.trim()), AR_INDEX.add);
            assert.deepEqual(await admin.locator('.page .metric .label').allInnerTexts().then(t => t.map(x => x.trim())), AR_INDEX.metrics);
            assert.equal(await admin.locator('[name=search]').getAttribute('placeholder'), AR_INDEX.placeholder);
            for (const o of AR_INDEX.options) assert.ok(await admin.locator(`option:text-is("${o}")`).count(), 'option ' + o);
            const headers = await admin.locator('.page table').first().locator('thead th').allInnerTexts().then(t => t.map(x => x.trim()));
            assert.deepEqual(headers, AR_INDEX.headers);
            const latinHeaders = headers.filter(latinOnly);
            assert.deepEqual(latinHeaders, [], 'English headers: ' + latinHeaders.join(' | '));
            assert.match(text, /اعتماد الفاتورة يُرحّل/, 'posting note');
            assert.match(text, /عرض \d+-\d+ من أصل \d+/, 'pagination footer');
            const statusLabels = await admin.locator('select[name=status] option, select[name=zatca] option').evaluateAll(els => els.map(e => e.textContent.trim()));
            const latinStatus = statusLabels.filter(latinOnly);
            assert.deepEqual(latinStatus, [], 'English filter option labels: ' + latinStatus.join(' | '));
            return {headers, statusLabels: statusLabels.length};
        });
        await check('register-ar-amounts-filters-actions-identical-to-en', async () => {
            await admin.goto(base + INDEX);
            const ar = await snapshot(admin);
            assert.deepEqual(ar.metrics, enIndex.metrics, 'metric values differ');
            assert.deepEqual(ar.tables.map(t => t.rows), enIndex.tables.map(t => t.rows), 'table amounts / dates differ');
            assert.deepEqual(ar.forms, enIndex.forms, 'filter / action forms differ');
            assert.deepEqual(ar.links, enIndex.links, 'action links differ');
            return {rows: ar.tables[0].rows.length, forms: ar.forms.length, links: ar.links.length};
        });
        await check('register-filters-same-results-in-both-locales', async () => {
            const combos = ['?status=draft', '?status=unpaid', '?zatca=not_generated', '?search=INV-2026-0003', '?status=forged-value', '?customer=1&status=draft'];
            const out = {};
            for (const locale of ['en', 'ar']) {
                await setLocale(admin, locale);
                for (const q of combos) { await admin.goto(base + INDEX + q); out[locale + q] = await rowsOf(admin); }
            }
            for (const q of combos) assert.deepEqual(out['ar' + q], out['en' + q], 'rows differ for ' + q);
            assert.deepEqual(out['en?search=INV-2026-0003'], ['INV-2026-0003']);
            assert.ok(out['en?status=draft'].length >= 1 && out['en?status=draft'].every(n => !['INV-2026-0001', 'INV-2026-0002', 'INV-2026-0003'].includes(n)), 'draft filter');
            return Object.fromEntries(combos.map(q => [q, out['en' + q].length]));
        });
        await setLocale(admin, 'ar');
        await sweep(admin, 'admin', 'register', INDEX, 'ar');
        await setLocale(admin, 'en');
        await sweep(admin, 'admin', 'register', INDEX, 'en');

        // ---------- Invoice detail: partially paid (receipts, journal) and draft ----------
        const DETAIL_IDS = {rich: 2, draft: 8};
        const enShow = {};
        await setLocale(admin, 'en');
        for (const [kind, id] of Object.entries(DETAIL_IDS)) {
            await check(`detail-${kind}-en-fallback-unchanged`, async () => {
                await admin.goto(base + showPath(id));
                const ths = await admin.locator('.page th').allInnerTexts().then(t => t.map(x => x.trim()));
                for (const l of EN_SHOW) assert.ok(ths.some(t => t === l || t.startsWith(l + ' (')), 'label ' + l + ' in ' + ths.length + ' headers');
                assert.match(await admin.locator('.page h1.page-title').innerText(), /^Invoice: INV-/);
                assert.match(await pageText(admin), /Amounts in SAR; VAT per line at the invoice rate/);
                enShow[kind] = await snapshot(admin);
                return {tables: enShow[kind].tables.length, forms: enShow[kind].forms.length};
            });
        }
        await setLocale(admin, 'ar');
        for (const [kind, id] of Object.entries(DETAIL_IDS)) {
            await check(`detail-${kind}-ar-labels-translated`, async () => {
                await admin.goto(base + showPath(id));
                const ths = await admin.locator('.page th').allInnerTexts().then(t => t.map(x => x.trim()));
                for (const l of AR_SHOW) assert.ok(ths.some(t => t === l || t.startsWith(l + ' (')), 'label ' + l);
                assert.match(await admin.locator('.page h1.page-title').innerText(), /^الفاتورة: INV-/);
                assert.match(await admin.locator('.topbar').innerText(), /المحاسبة \/ الحسابات المدينة \/ فاتورة العميل/);
                const latinThs = ths.filter(latinOnly);
                assert.deepEqual(latinThs, [], 'English labels: ' + latinThs.join(' | '));
                const tabs = await admin.locator('.page nav.tabs a').allInnerTexts().then(t => t.map(x => x.trim()));
                assert.deepEqual(tabs.filter(latinOnly), [], 'English section tabs: ' + tabs.join(' | '));
                return {labels: ths.length, tabs};
            });
            await check(`detail-${kind}-ar-amounts-actions-identical-to-en`, async () => {
                await admin.goto(base + showPath(id));
                const ar = await snapshot(admin);
                const diffs = ar.tables.flatMap((t, i) => t.rows.flatMap((r, j) => JSON.stringify(r) === JSON.stringify(enShow[kind].tables[i]?.rows[j]) ? [] : [`${t.id || i}#${j}: en=${JSON.stringify(enShow[kind].tables[i]?.rows[j])} ar=${JSON.stringify(r)}`]));
                assert.deepEqual(diffs, [], 'table amounts differ: ' + diffs.join(' | '));
                assert.deepEqual(ar.tables.map(t => t.rows), enShow[kind].tables.map(t => t.rows), 'table amounts differ');
                assert.deepEqual(ar.dl, enShow[kind].dl, 'header amounts differ');
                assert.deepEqual(ar.forms, enShow[kind].forms, 'action forms differ');
                assert.deepEqual(ar.links, enShow[kind].links, 'links differ');
                return {forms: ar.forms.map(f => f.action.replace(/^.*accounts-receivable/, '')), links: ar.links.length};
            });
        }
        await sweep(admin, 'admin', 'detail-rich', showPath(DETAIL_IDS.rich), 'ar');
        await sweep(admin, 'admin', 'detail-draft', showPath(DETAIL_IDS.draft), 'ar');
        await setLocale(admin, 'en');
        await sweep(admin, 'admin', 'detail-rich', showPath(DETAIL_IDS.rich), 'en');
        await sweep(admin, 'admin', 'detail-draft', showPath(DETAIL_IDS.draft), 'en');

        // ---------- Posting behaviour through the Arabic UI: approve a 15 % draft ----------
        await setLocale(admin, 'ar');
        let before = null;
        await check('approve-post-draft-9-in-arabic-posts-balanced-journal', async () => {
            await admin.goto(base + INDEX);
            before = await snapshot(admin);
            // Newest 15 % draft of 2 × 100 (created by the earlier GAP-02 runs; synthetic fixture).
            await admin.goto(base + INDEX + '?status=draft');
            const draft = await admin.locator('.page table').first().locator('tbody tr').evaluateAll(trs => trs.map(tr => ({n: tr.querySelector('td')?.innerText.trim(), href: tr.querySelector('td a')?.getAttribute('href')}))
                .filter(r => r.n && /^INV-/.test(r.n) && r.href)).then(rows => rows.find(r => /INV-2026-00(09|08|07)$/.test(r.n)));
            assert.ok(draft, 'no 230.00 draft left in the fixture');
            const draftPath = new URL(draft.href, base).pathname.replace(/\/edit$/, '');
            await admin.goto(base + draftPath);
            const form = admin.locator('.page-actions form[action$="/approve"]');
            assert.equal(await form.count(), 1, 'approve form');
            assert.equal(await form.locator('button').innerText().then(t => t.trim()), 'اعتماد وترحيل');
            await Promise.all([admin.waitForNavigation(), form.locator('button').click()]);
            assert.equal(await admin.locator('.page-actions form[action$="/approve"]').count(), 0, 'approve action must disappear after posting');
            const snap = await snapshot(admin);
            const info = snap.tables.find(t => t.rows.some(r => r.join(' ').includes('230.00')));
            assert.ok(info, 'information table with the total');
            const acc = snap.tables.find(t => t.id === 'accounting');
            assert.ok(acc && acc.rows.length >= 3, 'journal lines shown');
            const vat = snap.tables.find(t => t.id === 'vat');
            assert.deepEqual(vat.rows.map(r => r[0]).slice(0, 4), ['15.00', '200.00', '30.00', '230.00'], 'VAT section amounts');
            const text = await pageText(admin);
            assert.doesNotMatch(text, /Approve & Post|\bUnpaid\b/, 'English state text');
            // Journal lines: account code | project / cost center | debit | credit (other rows are the journal header / footer).
            const lines = acc.rows.filter(r => r.length === 4 && /^\d{4}$/.test(r[0]));
            assert.ok(lines.length >= 3, 'journal lines: ' + JSON.stringify(acc.rows));
            const debits = lines.map(r => Number(r[2] || 0)).reduce((a, b) => a + b, 0), credits = lines.map(r => Number(r[3] || 0)).reduce((a, b) => a + b, 0);
            assert.equal(debits.toFixed(2), '230.00', 'journal debits ' + JSON.stringify(lines)); assert.equal(credits.toFixed(2), '230.00', 'journal credits ' + JSON.stringify(lines));
            return {vat: vat.rows, accounting: acc.rows};
        });
        await check('register-after-posting-draft-count-and-receivable-moved', async () => {
            await admin.goto(base + INDEX);
            const after = await snapshot(admin);
            const b = before.metrics, a = after.metrics;
            assert.equal(Number(a[2]), Number(b[2]) - 1, 'draft count');
            assert.equal((Number(a[0]) - Number(b[0])).toFixed(2), '230.00', 'outstanding receivable');
            return {before: b, after: a};
        });

        // ---------- Accounts Manager: same actions in both locales ----------
        await setLocale(zubair, 'en');
        let zEn = null;
        await check('detail-rich-accounts-manager-en-actions', async () => { await zubair.goto(base + showPath(DETAIL_IDS.rich)); zEn = await snapshot(zubair); return {forms: zEn.forms.length, links: zEn.links.length}; });
        await setLocale(zubair, 'ar');
        await check('detail-rich-accounts-manager-ar-same-actions-and-amounts', async () => {
            await zubair.goto(base + showPath(DETAIL_IDS.rich));
            const ar = await snapshot(zubair);
            assert.deepEqual(ar.forms, zEn.forms, 'forms differ'); assert.deepEqual(ar.links, zEn.links, 'links differ');
            const diffs = ar.tables.flatMap((t, i) => t.rows.flatMap((r, j) => JSON.stringify(r) === JSON.stringify(zEn.tables[i]?.rows[j]) ? [] : [`${t.id || i}#${j}: en=${JSON.stringify(zEn.tables[i]?.rows[j])} ar=${JSON.stringify(r)}`]));
            assert.deepEqual(diffs, [], 'table amounts differ: ' + diffs.join(' | '));
        });
        await sweep(zubair, 'zubair', 'register', INDEX, 'ar');
        await sweep(zubair, 'zubair', 'detail-rich', showPath(DETAIL_IDS.rich), 'ar');
        await check('detail-forbidden-without-ar-permission-unchanged', async () => {
            const waleed = await pageFor('waleed@example.com');
            const r = await waleed.goto(base + INDEX);
            assert.equal(r.status(), 403);
        });
    } catch (e) {
        results.push({id: 'harness', status: 'FAIL', detail: e.stack.slice(0, 1200)});
    } finally {
        await browser.close();
    }
    const summary = {
        run_at: new Date().toISOString(),
        branch: 'feature/seera-ar-register-arabic-2026-10-29',
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
    fs.writeFileSync(path.join(out, 'ar-register-browser-results.json'), JSON.stringify(summary, null, 2));
    console.log(JSON.stringify({checks: summary.checks, passed: summary.passed, failed: summary.failed, page_errors: errors.length}));
})();
