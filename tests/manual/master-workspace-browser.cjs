// Customer / Supplier / Employee connected-workspace closure browser verification (headless Edge via Playwright)
// against the isolated synthetic UAT fixture. Usage: node tests/manual/master-workspace-browser.cjs
// Never run against production. Business records are read-only; login/session and locale preferences change in the fixture.
// Screenshots go to a temp folder and are not committed.
const fs = require('fs');
const os = require('os');
const path = require('path');
const { chromium } = require(process.env.SEERA_PLAYWRIGHT || 'D:/laragon2/www/property_handle/node_modules/playwright');

const BASE = process.env.SEERA_BASE || 'http://127.0.0.1:18769';
const target = new URL(BASE);
if (target.protocol !== 'http:' || !['127.0.0.1', 'localhost', '[::1]'].includes(target.hostname) || target.username || target.password) {
    throw new Error('Use an isolated loopback fixture only. Remote targets are forbidden.');
}
const EDGE = process.env.SEERA_EDGE || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const OUT = process.env.SEERA_OUT || path.resolve(__dirname, '../../docs/release-readiness/evidence/master-workspace-ux-closure-2026-11-01/master-workspace-browser-results.json');
const SHOTS = process.env.SEERA_SHOTS || path.join(os.tmpdir(), 'seera-master-workspace-shots');
const AR = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../../lang/ar.json'), 'utf8'));
const WIDTHS = [320, 390, 768, 1440];
const PASSWORD = 'password';
const SUP = { id: 1, code: 'SUP-001', name: 'Saudi Cement Supplier' };
const CUS = { id: 1, code: 'CUS-001', name: 'ABC Holdings' };
const EMP = { id: 1 };
const supShow = `/admin/master/suppliers/${SUP.id}`;
const supEdit = `/admin/master/suppliers/${SUP.id}/edit`;
const supPanel = (k) => `/admin/master/suppliers/${SUP.id}/workspace/${k}`;
const cusShow = `/admin/master/customers/${CUS.id}`;
const empShow = `/admin/hr/employees/${EMP.id}`;
const SUP_PANELS = { projects: 'Projects', 'purchase-orders': 'Purchase Orders', 'goods-receipts': 'Goods Receipts', bills: 'Supplier Bills', payments: 'Payments & Balance', accounting: 'Accounting', activity: 'Activity' };
const PROFILE_ROWS = ['Supplier Name', 'Supplier Code', 'Category', 'City / Location', 'Rating', 'VAT Number', 'CR Number', 'Contact Person', 'Phone', 'Email', 'Payment Terms', 'Accepted Payment Types', 'Bank', 'IBAN', 'Linked Payable Account', 'Opening Balance (master data)', 'Address', 'Status'];
const HEADER_DT = ['VAT number', 'CR number', 'Contact', 'Payment terms', 'Payable account', 'Projects', 'Outstanding payable', 'Paid to date'];

const results = [];
const pageErrors = [];
const check = (name, ok, detail) => { results.push({ name, ok: !!ok, detail: detail === undefined ? null : detail }); console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${detail !== undefined ? ' :: ' + JSON.stringify(detail) : ''}`); };
const record = (name, detail) => { results.push({ name: name + ' (recorded, not failed)', ok: true, detail }); };
const ar = (key) => { if (!(key in AR)) { throw new Error('missing Arabic entry for ' + key); } return AR[key]; };
const latin = (s) => !!s && /[A-Za-z]{3,}/.test(s) && !/[\u0600-\u06FF]/.test(s);
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
const nums = (s) => (s || '').match(/\d[\d,.]*/g) || [];

async function login(context, email) {
    const page = await context.newPage();
    page.setDefaultNavigationTimeout(90000);
    page.setDefaultTimeout(90000);
    page.on('pageerror', (e) => pageErrors.push({ email, message: String(e) }));
    await page.goto(BASE + '/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
    return page;
}

async function setLocale(page, locale) {
    await page.goto(BASE + '/admin/dashboard', { waitUntil: 'domcontentloaded' }).catch(() => {});
    await page.evaluate(async (loc) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value;
        const form = document.querySelector('form[action*="locale"]');
        const action = form ? form.action : location.origin + '/admin/locale';
        await fetch(action, { method: 'POST', body: new URLSearchParams({ _token: token, locale: loc }), redirect: 'manual', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    }, locale);
}

async function snap(page, url) {
    const r = await page.goto(BASE + url);
    if (r.status() !== 200) return { status: r.status() };
    const data = await page.evaluate(() => {
        const text = (el) => (el ? el.textContent.replace(/\s+/g, ' ').trim() : '');
        const latin = (s) => !!s && /[A-Za-z]{3,}/.test(s) && !/[\u0600-\u06FF]/.test(s);
        const profile = document.querySelector('#profile');
        return {
            dir: document.documentElement.dir,
            docTitle: document.title,
            h1: text(document.querySelector('h1.page-title')),
            breadcrumb: text(document.querySelector('.breadcrumb')),
            desc: text(document.querySelector('.page-desc')),
            headerButtons: [...document.querySelectorAll('.page-header a.btn, .page-head a.btn, h1 ~ * a.btn')].map((a) => ({ text: text(a), href: new URL(a.getAttribute('href'), location.origin).pathname })),
            dl: [...document.querySelectorAll('dl dt')].map((dt) => [text(dt), text(dt.nextElementSibling)]),
            tabs: [...document.querySelectorAll('nav.workspace-nav a.tab')].map(text),
            navAria: document.querySelector('nav.workspace-nav')?.getAttribute('aria-label'),
            navHidden: document.querySelector('nav.workspace-nav')?.hasAttribute('hidden'),
            profileTitle: text((profile?.closest('.table-card') || profile)?.querySelector('.table-title > span')),
            profileRows: profile ? [...profile.querySelectorAll('tbody tr')].map((tr) => [text(tr.querySelector('th')), text(tr.querySelector('td'))]) : [],
            hasSaveAction: !!document.querySelector('[name="_save_action"]'),
            profileFormAction: document.querySelector('form[data-workspace-form], [data-workspace-form] form')?.getAttribute('action') || null,
            formFields: [...document.querySelectorAll('[data-workspace-form] input[name], [data-workspace-form] select[name], [data-workspace-form] textarea[name]')].map((f) => f.name).filter((n) => !['_token', '_method'].includes(n)),
            relatedTabs: [...document.querySelectorAll('[data-workspace-related]')].map((a) => ({ key: a.getAttribute('data-workspace-related'), text: text(a), url: new URL(a.getAttribute('data-related-url'), location.origin).pathname })),
            panels: [...document.querySelectorAll('[data-panel-content]')].map((c) => ({ id: c.id, h2: text(c.querySelector('h2')), ths: [...c.querySelectorAll('th')].map(text), buttons: [...c.querySelectorAll('a.btn, button.btn')].map(text), rows: [...c.querySelectorAll('tbody tr')].map((tr) => [...tr.querySelectorAll('td')].map(text)), small: [...c.querySelectorAll('p.small')].map(text), metrics: [...c.querySelectorAll('.card.metric')].map((m) => [text(m.querySelector('.label')), text(m.querySelector('.value'))]) })),
            latinButtons: [...new Set([...document.querySelectorAll('a.btn, button')].map(text).filter(latin))],
            latinLabels: [...new Set([...document.querySelectorAll('th, dt, .table-title > span, .page-desc, .breadcrumb, h2, .table-empty, .card.metric .label, label')].map(text).filter(latin))],
            body: document.body.innerText,
            links: [...new Set([...document.querySelectorAll('a[href]')].map((a) => new URL(a.getAttribute('href'), location.origin)).filter((u) => u.origin === location.origin && u.pathname.startsWith('/admin/') && !u.pathname.includes('/workspace/') && !u.search.includes('return_to')).map((u) => u.pathname + u.search))],
        };
    });
    return { status: 200, ...data };
}

async function panelJson(page, url) {
    return page.evaluate(async (u) => {
        const r = await fetch(u, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        let body = null; try { body = await r.json(); } catch (e) { body = null; }
        if (r.status !== 200 || !body || typeof body.html !== 'string') return { status: r.status };
        const doc = new DOMParser().parseFromString(body.html, 'text/html');
        const text = (el) => (el ? el.textContent.replace(/\s+/g, ' ').trim() : '');
        const latin = (s) => !!s && /[A-Za-z]{3,}/.test(s) && !/[\u0600-\u06FF]/.test(s);
        const c = doc.querySelector('[data-panel-content]');
        return { status: 200, id: c?.id, h2: text(c?.querySelector('h2')), ths: [...c.querySelectorAll('th')].map(text), buttons: [...c.querySelectorAll('a.btn, button.btn')].map(text), rows: [...c.querySelectorAll('tbody tr')].map((tr) => [...tr.querySelectorAll('td')].map(text)), metrics: [...c.querySelectorAll('.card.metric')].map((m) => [text(m.querySelector('.label')), text(m.querySelector('.value'))]), small: [...c.querySelectorAll('p.small')].map(text), latinButtons: [...c.querySelectorAll('a.btn, button.btn')].map(text).filter(latin), latinThs: [...c.querySelectorAll('th')].map(text).filter(latin), hasForm: !!c.querySelector('form[data-related-save]') };
    }, BASE + url);
}

async function checkLinks(context, links, who) {
    const broken = [];
    for (const link of links) {
        const r = await context.request.get(BASE + link, { maxRedirects: 0 });
        if (![200, 302].includes(r.status())) broken.push({ link, status: r.status() });
    }
    check(`${who}: every /admin/ link answers 200 or 302`, broken.length === 0, { total: links.length, broken });
}

async function sweep(page, who, locale, url, tag) {
    for (const width of WIDTHS) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(BASE + url);
        const info = await page.evaluate(() => {
            const t = (b) => b.textContent.replace(/\s+/g, ' ').trim();
            const latin = (s) => s && /[A-Za-z]{3,}/.test(s) && !/[\u0600-\u06FF]/.test(s);
            return {
                scrollWidth: document.documentElement.scrollWidth, innerWidth: window.innerWidth,
                latinButtons: [...new Set([...document.querySelectorAll('a.btn, button')].map(t).filter(latin))],
                latinLabels: [...new Set([...document.querySelectorAll('th, dt, .table-title > span, .page-desc, .breadcrumb, h2, .table-empty, .card.metric .label')].map(t).filter(latin))],
            };
        });
        await page.screenshot({ path: path.join(SHOTS, `${who}${tag}-${locale}-${width}.png`), fullPage: true });
        check(`${who}${tag} ${locale} ${width}px no horizontal overflow`, info.scrollWidth <= info.innerWidth + 1, { scrollWidth: info.scrollWidth, innerWidth: info.innerWidth });
        if (locale === 'ar') {
            check(`${who}${tag} ar ${width}px no untranslated priority action`, info.latinButtons.length === 0, info.latinButtons);
            record(`${who}${tag} ar ${width}px remaining Latin headings / labels`, info.latinLabels);
        }
    }
    await page.setViewportSize({ width: 1440, height: 900 });
}

async function status(page, url) { const r = await page.goto(BASE + url); return r.status(); }

(async () => {
    fs.mkdirSync(SHOTS, { recursive: true });
    fs.mkdirSync(path.dirname(OUT), { recursive: true });
    const browser = await chromium.launch({ executablePath: EDGE, headless: true });
    try {
        const adminCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const admin = await login(adminCtx, 'admin@example.com');
        await setLocale(admin, 'en');

        // ---- Supplier View (English): read-only
        let v = await snap(admin, supShow);
        check('EN supplier view: title, breadcrumb, description, h1', v.status === 200 && v.docTitle.startsWith('Supplier Details') && v.breadcrumb.endsWith('Master Setup / Suppliers / Supplier Details') && v.desc === 'Read-only supplier view. Nothing on this page changes data.' && v.h1 === SUP.name, { docTitle: v.docTitle, breadcrumb: v.breadcrumb, desc: v.desc, h1: v.h1 });
        check('EN supplier view: header actions', same(v.headerButtons.map((b) => b.text), ['Edit / Manage', 'Back to Suppliers']) && v.headerButtons[0].href === supEdit, v.headerButtons);
        check('EN supplier view: stays read-only (no save control, no profile form, no related-save form)', !v.hasSaveAction && v.profileFormAction === null && v.formFields.length === 0 && !v.body.includes('Link project'), { hasSaveAction: v.hasSaveAction, fields: v.formFields });
        check('EN supplier view: profile rows and header dl labels exact', same(v.profileRows.map((r) => r[0]), PROFILE_ROWS) && v.profileTitle === 'Supplier Information' && same(v.dl.map((d) => d[0]), HEADER_DT), { rows: v.profileRows.map((r) => r[0]), dl: v.dl.map((d) => d[0]) });
        check('EN supplier view: tabs Profile + 7 panels, inline read-only panels', same(v.tabs, ['Profile', ...Object.values(SUP_PANELS)]) && v.navAria === 'Supplier sections' && same(v.panels.map((p) => p.id), Object.keys(SUP_PANELS)), { tabs: v.tabs, panels: v.panels.map((p) => p.id) });
        const openBills = v.dl.find((d) => d[0] === 'Outstanding payable')[1];
        check('EN supplier view: open bill count pluralised ("1 open bill" / "n open bills", never "bill(s)")', /\(\d+ open bills?\)$/.test(openBills) && !openBills.includes('bill(s)') && !/\(1 open bills\)/.test(openBills), openBills);
        const enView = v;
        await checkLinks(adminCtx, v.links, 'EN supplier view');

        // ---- Supplier Edit / Manage (English): form + related panels
        let e = await snap(admin, supEdit);
        check('EN supplier edit: title, breadcrumb, description, h1', e.status === 200 && e.docTitle.startsWith('Supplier Workspace') && e.breadcrumb.endsWith('Master Setup / Suppliers / Supplier Workspace') && e.h1 === 'Supplier Workspace: ' + SUP.name && e.desc.startsWith('Profile and related records of this supplier'), { docTitle: e.docTitle, breadcrumb: e.breadcrumb, h1: e.h1 });
        check('EN supplier edit: header actions View (read-only) + Back', same(e.headerButtons.map((b) => b.text), ['View (read-only)', 'Back to Suppliers']) && e.headerButtons[0].href === supShow, e.headerButtons);
        check('EN supplier edit: profile form with save action and fields, nav tabs with 7 related panels', e.hasSaveAction && e.formFields.includes('name') && same(e.relatedTabs.map((t) => t.key), Object.keys(SUP_PANELS)) && e.relatedTabs.every((t) => t.text === SUP_PANELS[t.key]), { fields: e.formFields.slice(0, 8), tabs: e.relatedTabs.map((t) => t.text) });
        const enPanels = {};
        for (const key of Object.keys(SUP_PANELS)) {
            const p = await panelJson(admin, supPanel(key));
            enPanels[key] = p;
            check(`EN supplier edit panel ${key} loads with its title`, p.status === 200 && p.id === key && p.h2 === `${SUP_PANELS[key]} — ${SUP.code} / ${SUP.name}`, { status: p.status, h2: p.h2, rows: p.rows ? p.rows.length : null, buttons: p.buttons });
        }
        check('EN supplier edit: projects panel keeps its link form (edit) while the view has none', enPanels.projects.hasForm === true, enPanels.projects.buttons);
        const enEditDl = e.dl;

        // ---- Customer and Employee detail (English)
        let c = await snap(admin, cusShow);
        check('EN customer view: title and breadcrumb, code present, read-only', c.status === 200 && c.docTitle.startsWith('Customer Details') && c.breadcrumb.endsWith('Master Setup / Customers / Customer Details') && c.body.includes(CUS.code) && !c.hasSaveAction, { docTitle: c.docTitle, breadcrumb: c.breadcrumb });
        let m = await snap(admin, empShow);
        const empCode = (m.body.match(/EMP-\d+/) || [null])[0];
        check('EN employee view: title and breadcrumb, employee code present', m.status === 200 && m.docTitle.startsWith('Employee Details') && m.breadcrumb.endsWith('HR & Payroll / Employees / Employee Details') && !!empCode, { docTitle: m.docTitle, breadcrumb: m.breadcrumb, empCode });
        const enCus = c, enEmp = m;

        // ---- Arabic
        await setLocale(admin, 'ar');
        v = await snap(admin, supShow);
        check('AR supplier view: rtl, title, breadcrumb, description exact', v.dir === 'rtl' && v.docTitle.startsWith(ar('Supplier Details')) && v.breadcrumb.endsWith(ar('Master Setup / Suppliers / Supplier Details')) && v.desc === ar('Read-only supplier view. Nothing on this page changes data.') && v.h1 === SUP.name, { docTitle: v.docTitle, breadcrumb: v.breadcrumb, desc: v.desc });
        check('AR supplier view: header actions exact, read-only', same(v.headerButtons.map((b) => b.text), [ar('Edit / Manage'), ar('Back to Suppliers')]) && !v.hasSaveAction && v.formFields.length === 0, v.headerButtons);
        check('AR supplier view: profile labels exact and values identical to English', v.profileTitle === ar('Supplier Information') && same(v.profileRows.map((r) => r[0]), PROFILE_ROWS.map(ar)) && v.profileRows.every((r, i) => r[0] === ar('Accepted Payment Types') ? r[1] === ar(enView.profileRows[i][1]) : r[0] === ar('Status') ? true : r[1] === enView.profileRows[i][1]), { ar: v.profileRows, en: enView.profileRows });
        check('AR supplier view: header dl labels exact, amounts and counts identical', same(v.dl.map((d) => d[0]), HEADER_DT.map(ar)) && v.dl.every((d, i) => same(nums(d[1]), nums(enView.dl[i][1]))) && v.dl.find((d) => d[0] === ar('Outstanding payable'))[1].includes(ar(':count open bill|:count open bills').replace(':count', nums(openBills)[nums(openBills).length - 1])), { ar: v.dl, en: enView.dl });
        check('AR supplier view: tabs exact and nav label exact', same(v.tabs, ['Profile', ...Object.values(SUP_PANELS)].map(ar)) && v.navAria === ar('Supplier sections'), v.tabs);
        for (const p of v.panels) {
            const en = enView.panels.find((x) => x.id === p.id);
            check(`AR supplier view panel ${p.id}: title exact, no Latin heading or button, amounts identical, same row count`, p.h2 === `${ar(SUP_PANELS[p.id])} — ${SUP.code} / ${SUP.name}` && p.ths.every((t) => !latin(t)) && p.buttons.every((b) => !latin(b)) && p.rows.length === en.rows.length && same(p.rows.map((r) => r.map(nums)), en.rows.map((r) => r.map(nums))) && same(p.metrics.map((x) => x[1]), en.metrics.map((x) => x[1])), { h2: p.h2, ths: p.ths, buttons: [...new Set(p.buttons)], rows: p.rows.length, metrics: p.metrics });
        }
        check('AR supplier view: no untranslated priority action anywhere on the page', v.latinButtons.length === 0, v.latinButtons);
        record('AR supplier view: remaining Latin headings / labels', v.latinLabels);
        await checkLinks(adminCtx, v.links, 'AR supplier view');

        e = await snap(admin, supEdit);
        check('AR supplier edit: title, breadcrumb, h1, description exact; form and tabs preserved', e.docTitle.startsWith(ar('Supplier Workspace')) && e.breadcrumb.endsWith(ar('Master Setup / Suppliers / Supplier Workspace')) && e.h1 === ar('Supplier Workspace:') + ' ' + SUP.name && e.desc === ar('Profile and related records of this supplier in one place. Each section saves independently; approvals and payments stay on their own documents.') && e.hasSaveAction && e.formFields.includes('name') && e.relatedTabs.every((t) => t.text === ar(SUP_PANELS[t.key])) && e.navAria === ar('Supplier sections'), { h1: e.h1, breadcrumb: e.breadcrumb, tabs: e.relatedTabs.map((t) => t.text), hasSaveAction: e.hasSaveAction });
        check('AR supplier edit: header actions exact', same(e.headerButtons.map((b) => b.text), [ar('View (read-only)'), ar('Back to Suppliers')]), e.headerButtons);
        check('AR supplier edit: header dl identical amounts to English edit', e.dl.every((d, i) => same(nums(d[1]), nums(enEditDl[i][1]))), e.dl);
        for (const key of Object.keys(SUP_PANELS)) {
            const p = await panelJson(admin, supPanel(key));
            const en = enPanels[key];
            check(`AR supplier edit panel ${key}: Arabic title, no Latin button or heading, same rows and identical amounts`, p.status === 200 && p.h2 === `${ar(SUP_PANELS[key])} — ${SUP.code} / ${SUP.name}` && p.latinButtons.length === 0 && p.latinThs.length === 0 && p.rows.length === en.rows.length && same(p.rows.map((r) => r.map(nums)), en.rows.map((r) => r.map(nums))) && same(p.metrics.map((x) => x[1]), en.metrics.map((x) => x[1])) && p.hasForm === en.hasForm, { h2: p.h2, latinButtons: p.latinButtons, latinThs: p.latinThs, buttons: [...new Set(p.buttons)], metrics: p.metrics });
            if (key === 'payments') check('AR payments panel: metric labels exact', same(p.metrics.map((x) => x[0]), ['Approved bills', 'Paid', 'Outstanding', 'Last payment'].map(ar)), p.metrics);
        }
        c = await snap(admin, cusShow);
        check('AR customer view: title and breadcrumb exact, code and name unchanged, read-only', c.docTitle.startsWith(ar('Customer Details')) && c.breadcrumb.endsWith(ar('Master Setup / Customers / Customer Details')) && c.desc === ar('Read-only customer view. Nothing on this page changes data.') && c.body.includes(CUS.code) && c.h1 === enCus.h1 && !c.hasSaveAction, { docTitle: c.docTitle, breadcrumb: c.breadcrumb, h1: c.h1 });
        check('AR customer view: no untranslated priority action', c.latinButtons.length === 0, c.latinButtons);
        check('AR customer view: decimal financial amounts identical to English', same(nums(c.body).filter((n) => n.includes('.')), nums(enCus.body).filter((n) => n.includes('.'))));
        record('AR customer view: remaining Latin headings / labels', c.latinLabels);
        m = await snap(admin, empShow);
        check('AR employee view: title and breadcrumb exact, employee code and name unchanged', m.docTitle.startsWith(ar('Employee Details')) && m.breadcrumb.endsWith(ar('HR & Payroll / Employees / Employee Details')) && m.body.includes(empCode) && m.h1 === enEmp.h1, { docTitle: m.docTitle, breadcrumb: m.breadcrumb, h1: m.h1 });
        check('AR employee view: no untranslated priority action', m.latinButtons.length === 0, m.latinButtons);
        record('AR employee view: remaining Latin headings / labels', m.latinLabels);
        check('AR employee view: salary / document amounts identical to English', same(nums(m.body.replace(/[\u0600-\u06FF]+/g, '')).filter((n) => n.includes('.')), nums(enEmp.body.replace(/[A-Za-z]+/g, '')).filter((n) => n.includes('.'))), { ar: nums(m.body).filter((n) => n.includes('.')).length, en: nums(enEmp.body).filter((n) => n.includes('.')).length });

        for (const [url, tag] of [[supShow, '-supplier-view'], [supEdit, '-supplier-edit'], [cusShow, '-customer-view'], [empShow, '-employee-view']]) await sweep(admin, 'super-admin', 'ar', url, tag);
        await setLocale(admin, 'en');
        for (const [url, tag] of [[supShow, '-supplier-view'], [supEdit, '-supplier-edit'], [cusShow, '-customer-view'], [empShow, '-employee-view']]) await sweep(admin, 'super-admin', 'en', url, tag);
        await adminCtx.close();

        // ---- Roles: permissions and routes unchanged
        const roles = [
            ['abdullah@example.com', 'marketing manager (Customers view/edit, no Suppliers, no HR)', { [cusShow]: 200, [supShow]: 403, [supEdit]: 403, [empShow]: 403 }],
            ['waleed@example.com', 'HR manager (HR, no Suppliers, no Customers)', { [empShow]: 200, [supShow]: 403, [cusShow]: 403 }],
            ['zubair@example.com', 'finance manager (Accounts Payable, no Suppliers view)', { [supShow]: 403, [supEdit]: 403, [supPanel('bills')]: 403, [cusShow]: 403, [empShow]: 403 }],
            ['nabeel@example.com', 'project manager (no Suppliers / Customers / HR)', { [supShow]: 403, [cusShow]: 403, [empShow]: 404 }], // employee binding is scope-filtered before the HR permission check, so an out-of-scope employee answers 404 (pre-existing ordering, still a refusal)
        ];
        for (const [email, who, expect] of roles) {
            const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
            const p = await login(ctx, email);
            await setLocale(p, 'ar');
            const got = {};
            for (const url of Object.keys(expect)) got[url] = await status(p, url);
            check(`${who}: expected responses`, same(got, expect), { expected: expect, got });
            if (expect[cusShow] === 200) { const s = await snap(p, cusShow); check(`${who}: Arabic customer view without Edit for view-only? (records buttons)`, s.latinButtons.length === 0, s.headerButtons); }
            if (expect[empShow] === 200) { const s = await snap(p, empShow); check(`${who}: Arabic employee view has no untranslated priority action`, s.latinButtons.length === 0, s.latinButtons); }
            await ctx.close();
        }
    } finally {
        await browser.close();
    }
    const counted = results.filter((r) => !r.name.includes('recorded, not failed'));
    const summary = { total: counted.length, passed: counted.filter((r) => r.ok).length, failed: counted.filter((r) => !r.ok).length, pageErrors: pageErrors.length };
    fs.writeFileSync(OUT, JSON.stringify({ base: BASE, generatedAt: new Date().toISOString(), summary, checks: results, pageErrors }, null, 2));
    console.log(`\n${summary.passed}/${summary.total} passed; page errors ${summary.pageErrors}`);
    process.exit(summary.failed || summary.pageErrors ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
