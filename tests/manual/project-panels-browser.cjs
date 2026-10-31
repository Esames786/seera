// Project workspace connected-panel Arabic (CU-03) browser verification (headless Edge via Playwright)
// against the isolated synthetic UAT fixture. Usage: node tests/manual/project-panels-browser.cjs
// Never run against production. Read-only: no fixture data is changed. Screenshots go to a temp folder and are not committed.
const fs = require('fs');
const os = require('os');
const path = require('path');
const { chromium } = require('D:/laragon2/www/property_handle/node_modules/playwright');

const BASE = process.env.SEERA_BASE || 'http://127.0.0.1:18769';
const EDGE = process.env.SEERA_EDGE || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const OUT = process.env.SEERA_OUT || path.resolve(__dirname, '../../docs/release-readiness/evidence/closure-project-panel-arabic-2026-10-31/project-panels-browser-results.json');
const SHOTS = process.env.SEERA_SHOTS || path.join(os.tmpdir(), 'seera-project-panels-shots');
const AR = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../../lang/ar.json'), 'utf8'));
const WIDTHS = [320, 390, 768, 1440];
const PASSWORD = 'password';
const P1 = { id: 1, code: 'PRJ-001' };
const P3 = { id: 3, code: 'PRJ-003', site: 'SITE-D1' };
const show = (p) => `/admin/master/projects/${p.id}`;
const panelUrl = (p, key) => `/admin/master/projects/${p.id}/workspace/${key}`;

// Mirrors App\Support\Workspace\ProjectWorkspacePanels (titles) and ProjectPanelTable (columns).
const PANELS = {
    'site-expenses': 'Site Expenses', customer: 'Customer', sites: 'Sites / Locations', staff: 'Project Team / Staff', equipment: 'Equipment',
    warehouses: 'Warehouses', stock: 'Stock On Hand', suppliers: 'Suppliers', requests: 'Purchase Requests', orders: 'Project Purchases',
    receipts: 'Goods Receipts', materials: 'Material Used', invoices: 'Customer Invoices', payments: 'Customer Receipts',
    finance: 'Financial / Cost Summary', activity: 'Activity',
};
const COLUMNS = {
    'site-expenses': ['Expense', 'Date', 'Site', 'Category', 'Submitted by', 'Supplier', 'Payment type', 'Total (SAR)', 'Approval status', 'Accounting'],
    customer: ['Code', 'Customer', 'VAT / CR', 'Contact'],
    sites: ['Code', 'Site', 'Supervisor', 'Address', 'Status', 'Geofence / radius', 'Inside only / offline allowed'],
    staff: ['Code', 'Employee', 'Department', 'Designation', 'Site', 'Manager', 'Status', 'Mobile access'],
    equipment: ['Asset', 'Type', 'Site', 'Operator', 'Status', 'Meter', 'Posted fuel (SAR)', 'Posted maintenance (SAR)'],
    warehouses: ['Code', 'Warehouse', 'Site', 'Incharge', 'Valuation', 'Status', 'Stocked items', 'On hand (by unit)', 'Stock value (SAR)'],
    stock: ['Warehouse', 'Item', 'Unit', 'On hand', 'Average cost', 'Current value (SAR)'],
    suppliers: ['Code', 'Supplier', 'Category', 'Contact', 'Status', 'Project POs (not cancelled)', 'Purchase value (SAR)'],
    requests: ['Request', 'Date', 'Required date', 'Status', 'Linked project orders'],
    orders: ['Order', 'Supplier', 'Date', 'Total (SAR)', 'Status / receiving', 'Billing state (visible receipts)'],
    receipts: ['Receipt', 'PO', 'Supplier', 'Warehouse', 'Date', 'Status', 'Stock updated', 'Invoicing state'],
    materials: ['Issue', 'Date', 'Site', 'Warehouse', 'Item', 'Unit', 'Issued quantity', 'Material cost (SAR)', 'Status'],
    invoices: ['Invoice', 'Date', 'Customer', 'Taxable', 'VAT', 'Total', 'Received', 'Outstanding', 'Status', 'Local ZATCA'],
    payments: ['Invoice', 'Date', 'Amount (SAR)', 'Method', 'Reference'],
    activity: ['Date', 'User', 'Action', 'Reference', 'Status'],
};
const EMPTY = {
    'site-expenses': 'No visible Site Expenses for this project. Add an expense and submit its receipt for approval.',
    customer: 'No customer is linked. Choose the customer on the Project profile.',
    sites: 'No visible locations belong to this project yet. Add a location when authorized.',
    staff: 'No visible employees are assigned. Set the current Project on the employee form; each employee has one current project.',
    equipment: 'No equipment is currently assigned to this project. Open Equipment & Vehicles → View → Current Assignment to assign an asset.',
    warehouses: 'No visible warehouses or positive stock balances for this project. Assign a warehouse to the project; receive stock through Inventory documents.',
    stock: 'No visible warehouses or positive stock balances for this project. Assign a warehouse to the project; receive stock through Inventory documents.',
    suppliers: 'No visible suppliers are linked. Link this project from Supplier Manage or create an authorized project purchase document.',
    requests: 'No visible project documents yet. Use the existing Inventory purchase workflow and choose this project; a GRN follows its PO.',
    orders: 'No visible project documents yet. Use the existing Inventory purchase workflow and choose this project; a GRN follows its PO.',
    receipts: 'No visible project documents yet. Use the existing Inventory purchase workflow and choose this project; a GRN follows its PO.',
    materials: 'No materials have been issued to this project yet. Create a Stock Issue from Inventory when materials are consumed.',
    invoices: 'No visible project invoices or receipts yet. Create an invoice in Accounts Receivable and select this project; receipt entry remains a separate action.',
    payments: 'No visible project invoices or receipts yet. Create an invoice in Accounts Receivable and select this project; receipt entry remains a separate action.',
    activity: 'No securely linked activity yet. New project and location saves appear here. Older name-only logs cannot be reliably assigned and are omitted.',
};
const NOTES = {
    equipment: 'Assets whose active assignment is this project. Posted fuel / maintenance are the Finance-posted amounts (information only); the Project Cost Report and Budget vs Actual remain the cost truth. Live GPS tracking is not connected.',
    invoices: 'ZATCA state is local foundation information, not a claim of live clearance.',
    activity: 'Only exact Project entity-token activity visible to your role is included. Legacy free-text/name-only history is omitted to avoid false matches.',
};
const FINANCE_LABELS = ['Master budget', 'Posted cost', 'Budget used (%)', 'Posted revenue', 'Supplier billed', 'Customer invoiced', 'Margin (posted revenue minus posted cost)'];
const DETAILED_LABELS = ['Detailed current budget', 'Committed (open purchase orders)', 'Actual (posted cost, same as above)', 'Remaining detailed budget'];
const FINANCE_BUTTONS = ['Open Project Cost Report', 'Open Budget / BOQ'];
const BILL_COLUMNS = ['Bill / Supplier', 'Approval', 'Payment status', 'Accounting'];
const NO_DETAILED = 'No detailed budget has been set as current for this project yet. Open Budget / BOQ to create one.';

const results = [];
const pageErrors = [];
const check = (name, ok, detail) => { results.push({ name, ok: !!ok, detail: detail === undefined ? null : detail }); console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${detail !== undefined ? ' :: ' + JSON.stringify(detail) : ''}`); };
const record = (name, detail) => { results.push({ name: name + ' (recorded, not failed)', ok: true, detail }); };
const ar = (key) => { if (!(key in AR)) { throw new Error('missing Arabic entry for ' + key); } return AR[key]; };
const latin = (s) => !!s && /[A-Za-z]{3,}/.test(s) && !/[\u0600-\u06FF]/.test(s);
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

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

// The same extraction runs on the AJAX panel fragment (JSON html, as the workspace tabs load it) and on the full "View all (paged)" page.
function extract(root) {
    const text = (el) => (el ? el.textContent.replace(/\s+/g, ' ').trim() : '');
    const card = root.querySelector('[data-project-panel]');
    if (!card) return null;
    const key = card.getAttribute('data-project-panel');
    const tables = [...card.querySelectorAll('table')];
    const listTable = card.querySelector('div[style*="overflow-x"] table');
    const rows = (t) => (t ? [...t.querySelectorAll('tbody tr')].map((r) => [text(r.querySelector('th')), text(r.querySelector('td'))]) : null);
    const bills = tables.length ? tables[tables.length - 1] : null;
    return {
        panel: key,
        title: text(card.querySelector('.table-title strong')),
        titleButtons: [...card.querySelectorAll('.table-title a.btn')].map(text),
        topButtons: [...card.querySelectorAll(':scope > a.btn.sm')].map(text),
        notes: [...card.querySelectorAll(':scope > p.note')].map(text),
        columns: listTable ? [...listTable.querySelectorAll('thead th')].map(text) : null,
        rowCount: listTable ? [...listTable.querySelectorAll('tbody tr')].filter((r) => !r.querySelector('.table-empty')).length : null,
        firstRowCells: listTable ? [...(listTable.querySelector('tbody tr') ? listTable.querySelector('tbody tr').querySelectorAll('td') : [])].map(text) : null,
        rowActions: listTable ? [...listTable.querySelectorAll('tbody td .actions a')].map(text) : null,
        empty: text(card.querySelector('.table-empty')) || null,
        footer: text(card.querySelector('.table-footer > span')) || null,
        pager: [...card.querySelectorAll('.table-footer button[data-panel-load]')].map(text),
        financeRows: key === 'finance' ? rows(tables[0]) : null,
        detailedRows: key === 'finance' && tables.length > 2 ? rows(tables[1]) : null,
        h3: [...card.querySelectorAll('h3')].map(text),
        billColumns: key === 'finance' && bills ? [...bills.querySelectorAll('thead th')].map(text) : null,
        billCells: key === 'finance' && bills ? [...bills.querySelectorAll('tbody tr')].map((r) => [...r.querySelectorAll('td')].map(text)) : null,
        bottomButtons: [...card.querySelectorAll(':scope > a.btn.outline:not(.sm)')].map(text),
    };
}
const EXTRACT = extract.toString();

async function panelJson(page, url) {
    return page.evaluate(async ([u, src]) => {
        const r = await fetch(u, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const status = r.status;
        let body = null;
        try { body = await r.json(); } catch (e) { body = null; }
        if (status !== 200 || !body || typeof body.html !== 'string') return { status, data: null };
        const doc = new DOMParser().parseFromString(body.html, 'text/html');
        const fn = new Function('return (' + src + ')')();
        return { status, data: fn(doc) };
    }, [BASE + url, EXTRACT]);
}

async function panelPage(page, url) {
    const r = await page.goto(BASE + url);
    const status = r.status();
    if (status !== 200) return { status, data: null, head: null };
    const out = await page.evaluate((src) => {
        const text = (el) => (el ? el.textContent.replace(/\s+/g, ' ').trim() : '');
        const fn = new Function('return (' + src + ')')();
        return {
            data: fn(document),
            head: { docTitle: document.title, breadcrumb: text(document.querySelector('.breadcrumb')), title: text(document.querySelector('h1.page-title')), buttons: [...document.querySelectorAll('.page-header a.btn, .page-head a.btn, h1 ~ * a.btn')].map(text), dir: document.documentElement.dir },
        };
    }, EXTRACT);
    return { status, ...out };
}

async function tabs(page, url) {
    await page.goto(BASE + url);
    return page.evaluate(() => [...document.querySelectorAll('[data-workspace-nav] a.tab[data-workspace-related]')].map((a) => ({ key: a.getAttribute('data-workspace-related'), text: a.textContent.replace(/\s+/g, ' ').trim(), url: new URL(a.getAttribute('data-related-url'), location.origin).pathname })));
}

function footerPattern(template) {
    return new RegExp('^' + template.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(':from', '(\\d+)').replace(':to', '(\\d+)').replace(':total', '(\\d+)') + '$');
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
                latinLabels: [...new Set([...document.querySelectorAll('.table-title strong, th, .page-desc, .breadcrumb, h3, .table-empty, .table-footer > span')].map(t).filter(latin))],
                latinNotes: [...new Set([...document.querySelectorAll('[data-project-panel] p.note')].map(t).filter(latin))],
                latinCells: [...new Set([...document.querySelectorAll('[data-project-panel] tbody td')].map(t).filter(latin))],
            };
        });
        await page.screenshot({ path: path.join(SHOTS, `${who}${tag}-${locale}-${width}.png`), fullPage: true });
        check(`${who}${tag} ${locale} ${width}px no horizontal overflow`, info.scrollWidth <= info.innerWidth + 1, { scrollWidth: info.scrollWidth, innerWidth: info.innerWidth });
        if (locale === 'ar') {
            check(`${who}${tag} ar ${width}px no untranslated buttons, headings, column titles or empty states`, info.latinButtons.length === 0 && info.latinLabels.length === 0, { buttons: info.latinButtons, labels: info.latinLabels });
            record(`${who}${tag} ar ${width}px remaining Latin notes and data cells (outside CU-03 scope)`, { notes: info.latinNotes, cells: info.latinCells });
        }
    }
    await page.setViewportSize({ width: 1440, height: 900 });
}

async function status(page, url) { const r = await page.goto(BASE + url); return r.status(); }
async function jsonStatus(page, url) { return page.evaluate(async (u) => (await fetch(u, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })).status, BASE + url); }

(async () => {
    fs.mkdirSync(SHOTS, { recursive: true });
    fs.mkdirSync(path.dirname(OUT), { recursive: true });
    const browser = await chromium.launch({ executablePath: EDGE, headless: true });
    try {
        // ---- Super Admin, English baseline of every panel
        const adminCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const admin = await login(adminCtx, 'admin@example.com');
        await setLocale(admin, 'en');
        const enTabs = await tabs(admin, show(P1));
        check('super admin: every connected panel is a tab in English', same(enTabs.map((t) => t.key), Object.keys(PANELS)) && enTabs.every((t) => t.text === PANELS[t.key]), enTabs.map((t) => t.text));
        const en = {};
        for (const key of Object.keys(PANELS)) {
            const { status: st, data } = await panelJson(admin, panelUrl(P1, key));
            en[key] = data;
            const ok = st === 200 && data && data.panel === key && data.title === `${PANELS[key]} — ${P1.code}` && data.titleButtons[0] === 'View all (paged)'
                && (key === 'finance' ? same(data.financeRows.map((r) => r[0]), FINANCE_LABELS) && same(data.bottomButtons, FINANCE_BUTTONS) : same(data.columns, [...COLUMNS[key], 'Actions']) && (data.rowCount > 0 ? data.empty === null : data.empty === EMPTY[key]));
            check(`EN panel ${key}: title, columns / finance labels, empty state`, ok, data ? { title: data.title, rows: data.rowCount, empty: data.empty, columns: data.columns, finance: data.financeRows } : { status: st });
        }
        const populated = Object.keys(PANELS).filter((k) => en[k] && en[k].rowCount > 0);
        const empties = Object.keys(PANELS).filter((k) => en[k] && en[k].rowCount === 0);
        record('fixture coverage: populated vs empty panels for PRJ-001', { populated, empties });
        // English paged page keeps its breadcrumb spacing (CU-04)
        let pg = await panelPage(admin, panelUrl(P1, 'sites'));
        check('EN paged page: title, breadcrumb "Master Setup / Projects / Sites / Locations", Back to Project', pg.status === 200 && pg.head.docTitle.startsWith('Sites / Locations') && pg.head.breadcrumb.endsWith('Master Setup / Projects / Sites / Locations') && pg.head.buttons.includes('Back to Project') && pg.data.title === `Sites / Locations — ${P1.code}`, pg.head);

        // ---- Super Admin, Arabic: every panel through the AJAX fragment
        await setLocale(admin, 'ar');
        const arTabs = await tabs(admin, show(P1));
        check('Arabic view: every connected tab is the exact dictionary title, same order', same(arTabs.map((t) => t.key), Object.keys(PANELS)) && arTabs.every((t) => t.text === ar(PANELS[t.key])), arTabs.map((t) => t.text));
        const latinCells = {};
        for (const key of Object.keys(PANELS)) {
            const { status: st, data } = await panelJson(admin, panelUrl(P1, key));
            const e = en[key];
            if (st !== 200 || !data || !e) { check(`AR panel ${key} loads`, false, { status: st }); continue; }
            const common = data.panel === key && data.title === `${ar(PANELS[key])} — ${P1.code}` && data.titleButtons[0] === ar('View all (paged)')
                && data.titleButtons.length === e.titleButtons.length && data.titleButtons.every((b) => !latin(b)) && data.topButtons.length === e.topButtons.length && data.topButtons.every((b) => !latin(b));
            check(`AR panel ${key}: title and header buttons exact`, common, { title: data.title, titleButtons: data.titleButtons, topButtons: data.topButtons });
            if (key === 'finance') {
                check('AR finance: summary labels exact and every amount identical to English', same(data.financeRows.map((r) => r[0]), FINANCE_LABELS.map(ar)) && same(data.financeRows.map((r) => r[1]), e.financeRows.map((r) => r[1])), { ar: data.financeRows, en: e.financeRows });
                check('AR finance: detailed budget block or its empty note, and action buttons exact', (e.detailedRows ? same(data.detailedRows.map((r) => r[0]), DETAILED_LABELS.map(ar)) && same(data.detailedRows.map((r) => r[1].replace(ar(' — Over budget'), ' — Over budget')), e.detailedRows.map((r) => r[1])) : data.notes.includes(ar(NO_DETAILED))) && same(data.bottomButtons, FINANCE_BUTTONS.map(ar)), { notes: data.notes, detailed: data.detailedRows, buttons: data.bottomButtons });
                check('AR finance: supplier bill context heading, columns and rows / empty state exact', same(data.h3, [ar('Supplier Bill approval context (latest 10)')]) && same(data.billColumns, BILL_COLUMNS.map(ar)) && (e.billCells.length === 1 && e.billCells[0][0] === 'No visible supplier bills.' ? same(data.billCells, [[ar('No visible supplier bills.')]]) : data.billCells.length === e.billCells.length), { h3: data.h3, columns: data.billColumns, cells: data.billCells });
                record('AR finance: calculation caveat notes still English (remaining gap)', data.notes.filter(latin));
                continue;
            }
            check(`AR panel ${key}: column headings exact`, same(data.columns, [...COLUMNS[key].map(ar), ar('Actions')]), data.columns);
            if (e.rowCount === 0) {
                check(`AR panel ${key}: empty state exact`, data.rowCount === 0 && data.empty === ar(EMPTY[key]), data.empty);
            } else {
                check(`AR panel ${key}: same rows as English, every row action translated`, data.rowCount === e.rowCount && data.rowActions.length === e.rowActions.length && data.rowActions.every((a) => !latin(a)) && e.rowActions.every((a, i) => data.rowActions[i] === ar(a)), { rows: data.rowCount, actions: [...new Set(data.rowActions)], en: [...new Set(e.rowActions)] });
                check(`AR panel ${key}: footer count exact`, footerPattern(ar('Showing :from–:to of :total')).test(data.footer) && same(data.footer.match(/\d+/g), e.footer.match(/\d+/g)), { ar: data.footer, en: e.footer });
                latinCells[key] = data.firstRowCells.filter(latin);
            }
            if (NOTES[key]) { check(`AR panel ${key}: note exact`, data.notes.includes(ar(NOTES[key])), data.notes); }
            if (data.pager.length) { check(`AR panel ${key}: pager buttons exact`, data.pager.every((p) => [ar('Previous'), ar('Next')].includes(p)), data.pager); }
            const otherNotes = data.notes.filter((n) => !NOTES[key] || n !== ar(NOTES[key]));
            if (otherNotes.length) { record(`AR panel ${key}: other notes (amount notes, remaining gap when Latin)`, otherNotes); }
        }
        record('AR populated panels: Latin first-row data cells (stored codes, names, dates and status words; outside CU-03 scope)', latinCells);
        // Arabic "View all (paged)" pages: breadcrumb (CU-04), title, back link
        for (const key of ['sites', 'finance', 'invoices']) {
            pg = await panelPage(admin, panelUrl(P1, key));
            check(`AR paged page ${key}: rtl, document title, breadcrumb and Back to Project exact`, pg.status === 200 && pg.head.dir === 'rtl' && pg.head.docTitle.startsWith(ar(PANELS[key])) && pg.head.breadcrumb.endsWith(ar('Master Setup / Projects /') + ' ' + ar(PANELS[key])) && pg.head.buttons.includes(ar('Back to Project')) && pg.data.title === `${ar(PANELS[key])} — ${P1.code}`, pg.head);
        }
        await sweep(admin, 'super-admin', 'ar', panelUrl(P1, 'sites'), '-sites');
        await sweep(admin, 'super-admin', 'ar', panelUrl(P1, 'finance'), '-finance');
        await sweep(admin, 'super-admin', 'ar', panelUrl(P1, 'orders'), '-orders');
        await setLocale(admin, 'en');
        await sweep(admin, 'super-admin', 'en', panelUrl(P1, 'sites'), '-sites');
        await sweep(admin, 'super-admin', 'en', panelUrl(P1, 'finance'), '-finance');
        await sweep(admin, 'super-admin', 'en', panelUrl(P1, 'orders'), '-orders');
        await adminCtx.close();

        // ---- Project Manager (project 1): own tabs load, other project refused
        const pmCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const pm = await login(pmCtx, 'nabeel@example.com');
        await setLocale(pm, 'ar');
        const pmTabs = await tabs(pm, show(P1));
        let loaded = [];
        for (const t of pmTabs) { const r = await panelJson(pm, t.url); loaded.push({ key: t.key, status: r.status, title: r.data ? r.data.title : null }); }
        check('project manager: every visible tab is Arabic and its panel loads with the Arabic title', pmTabs.length > 0 && pmTabs.every((t) => t.text === ar(PANELS[t.key])) && loaded.every((l) => l.status === 200 && l.title === `${ar(PANELS[l.key])} — ${P1.code}`), loaded);
        // The fixture project manager is manager_id of all three projects, so NR-34 (a project-level user acts in every project they manage) grants PRJ-003 too.
        const pmOther = await panelJson(pm, panelUrl(P3, 'sites'));
        check('project manager: managed other project panel opens scoped to that project (NR-34)', pmOther.status === 200 && pmOther.data.title === `${ar(PANELS.sites)} — ${P3.code}` && pmOther.data.rowCount === 1 && pmOther.data.firstRowCells[0] === P3.site, { status: pmOther.status, title: pmOther.data ? pmOther.data.title : null, rows: pmOther.data ? pmOther.data.rowCount : null });
        record('project manager: visible panel keys', pmTabs.map((t) => t.key));
        await pmCtx.close();

        // ---- Marketing Manager (Projects view only): tabs limited, module-gated panels refused
        const mkCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const mk = await login(mkCtx, 'abdullah@example.com');
        await setLocale(mk, 'ar');
        const mkTabs = await tabs(mk, show(P1));
        const mkHidden = Object.keys(PANELS).filter((k) => !mkTabs.some((t) => t.key === k));
        const refused = [];
        for (const k of mkHidden) { const st = await jsonStatus(mk, panelUrl(P1, k)); if (st !== 403) refused.push({ k, st }); }
        check('view-only role: panels without the module permission are refused (403) even by direct URL', refused.length === 0, { hidden: mkHidden, unexpected: refused });
        loaded = [];
        for (const t of mkTabs) { const r = await panelJson(mk, t.url); loaded.push({ key: t.key, status: r.status, title: r.data ? r.data.title : null }); }
        check('view-only role: visible tabs load in Arabic', mkTabs.every((t) => t.text === ar(PANELS[t.key])) && loaded.every((l) => l.status === 200), { tabs: mkTabs.map((t) => t.key), loaded });
        await mkCtx.close();

        // ---- Site In-Charge (project 3 / site 4): site scope inside the panel, other project refused
        const siteCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const site = await login(siteCtx, 'zafar@example.com');
        await setLocale(site, 'ar');
        const siteTabs = await tabs(site, show(P3));
        const sitesTab = siteTabs.find((t) => t.key === 'sites');
        if (sitesTab) {
            const r = await panelJson(site, sitesTab.url);
            check('site in-charge: sites panel lists only the assigned site, Arabic headings', r.status === 200 && r.data.rowCount === 1 && r.data.firstRowCells[0] === P3.site && same(r.data.columns, [...COLUMNS.sites.map(ar), ar('Actions')]), { rows: r.data ? r.data.rowCount : null, first: r.data ? r.data.firstRowCells.slice(0, 2) : null, columns: r.data ? r.data.columns : null });
        } else {
            check('site in-charge: sites panel visible', false, siteTabs.map((t) => t.key));
        }
        check('site in-charge: forged panel of another project refused (404)', await jsonStatus(site, panelUrl(P1, 'sites')) === 404 && await status(site, panelUrl(P1, 'sites')) === 404);
        await siteCtx.close();

        // ---- Roles without Projects view
        for (const [email, who] of [['waleed@example.com', 'HR manager'], ['zubair@example.com', 'finance manager']]) {
            const ctx = await browser.newContext();
            const p = await login(ctx, email);
            check(`${who}: panel page and fragment refused (403)`, await status(p, panelUrl(P1, 'sites')) === 403 && await jsonStatus(p, panelUrl(P1, 'finance')) === 403);
            await ctx.close();
        }
    } finally {
        await browser.close();
    }
    const counted = results.filter((r) => !r.name.includes('recorded, not failed'));
    const summary = { total: counted.length, passed: counted.filter((r) => r.ok).length, failed: counted.filter((r) => !r.ok).length, pageErrors: pageErrors.length };
    fs.writeFileSync(OUT, JSON.stringify({ base: BASE, generatedAt: new Date().toISOString(), summary, checks: results, pageErrors }, null, 2));
    console.log(`\n${summary.passed}/${summary.total} passed; page errors ${summary.pageErrors}`);
    process.exit(summary.failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
