// HR dashboard browser verification (headless Edge via Playwright) against the isolated synthetic UAT fixture.
// Usage: node tests/manual/hr-dashboard-browser.cjs <phase>   phase = phase1 | phase2 | phase3
//   phase1: fixture as seeded plus today's attendance rows (Super Admin, HR Manager, 403 users, links, locales, sweeps)
//   phase2: SITE_SUPERVISOR temporarily granted HR view (project/site scope)
//   phase3: HR_MANAGER temporarily without Attendance / Payroll permissions
// Never run against production. Screenshots go to a temp folder and are not committed.
const fs = require('fs');
const os = require('os');
const path = require('path');
const { chromium } = require('D:/laragon2/www/property_handle/node_modules/playwright');

const BASE = process.env.SEERA_BASE || 'http://127.0.0.1:18769';
const EDGE = process.env.SEERA_EDGE || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const PHASE = process.argv[2] || 'phase1';
const OUT = path.resolve(__dirname, '../../docs/release-readiness/evidence/hr-dashboard-2026-10-31/hr-dashboard-browser-results.json');
const SHOTS = process.env.SEERA_SHOTS || path.join(os.tmpdir(), 'seera-hr-dashboard-shots');
const AR = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../../lang/ar.json'), 'utf8'));
const WIDTHS = [320, 390, 768, 1440];
const DASH = '/admin/hr/dashboard';
const PASSWORD = 'password';

const EXPECT_COMPANY = { 'Total Employees': '20', 'Active Employees': '19', 'Present Today': '3', 'Late Today': '1', 'Absent Today': '1', 'On Leave Today': '0', 'Pending Leaves': '3', 'Pending Overtime': '3', 'Current Month Payroll': 'SAR 262,588', 'Expiring IQAMAs (60 days)': '2', 'Expiring Documents (60 days)': '5', 'Pending EOSB': '2' };
const EXPECT_SITE = { 'Total Employees': '3', 'Active Employees': '3', 'Present Today': '1', 'Late Today': '0', 'Absent Today': '1' };
const ATTENDANCE_LABELS = ['Present Today', 'Late Today', 'Absent Today'];
const PAYROLL_LABELS = ['Pending Overtime', 'Current Month Payroll', 'Pending EOSB'];
const PAYROLL_ROWS = ['Overtime Claims', 'Payroll Approval', 'End of Service'];
const NOTE_REAL = 'Recorded attendance and approved leave counts for today; not a historical trend or payroll calculation.';
const NOTE_HIDDEN = 'Attendance figures are hidden without Attendance view permission.';

const results = [];
let pageErrors = [];
const check = (name, ok, detail) => { results.push({ phase: PHASE, name, ok: !!ok, detail: detail === undefined ? null : detail }); console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${detail !== undefined ? ' :: ' + JSON.stringify(detail) : ''}`); };
const ar = (key) => { if (!(key in AR)) { throw new Error('missing Arabic entry for ' + key); } return AR[key]; };

async function login(context, email) {
    const page = await context.newPage();
    page.on('pageerror', (e) => pageErrors.push({ email, message: String(e) }));
    await page.goto(BASE + '/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
    return page;
}

async function setLocale(page, locale) {
    await page.goto(BASE + DASH, { waitUntil: 'domcontentloaded' }).catch(() => {});
    const status = await page.evaluate(async (loc) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value;
        const form = document.querySelector('form[action*="locale"]');
        const action = form ? form.action : location.origin + '/admin/locale';
        const body = new URLSearchParams({ _token: token, locale: loc });
        const r = await fetch(action, { method: 'POST', body, redirect: 'manual', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        return r.status + ':' + r.type;
    }, locale);
    await page.goto(BASE + DASH);
    const dir = await page.evaluate(() => document.documentElement.getAttribute('dir'));
    return { status, dir };
}

async function snapshot(page) {
    return page.evaluate(() => {
        const text = (el) => (el ? el.textContent.replace(/\s+/g, ' ').trim() : '');
        const metrics = [...document.querySelectorAll('.card.metric')].map((c) => ({ label: text(c.querySelector('.label')), value: text(c.querySelector('.value')) }));
        const tables = [...document.querySelectorAll('.table-card')].map((t) => ({ title: text(t.querySelector('.table-title > span')), actions: [...t.querySelectorAll('.table-title a')].map((a) => text(a)), rows: [...t.querySelectorAll('tbody tr')].map((r) => [...r.querySelectorAll('td')].map((d) => text(d))) }));
        const headerButtons = [...document.querySelectorAll('.page-header a.btn, .page-head a.btn, h1 ~ * a.btn')].map((a) => ({ text: text(a), href: a.getAttribute('href') }));
        const allButtons = [...document.querySelectorAll('main a.btn, .content a.btn, a.btn')].map((a) => ({ text: text(a), href: a.getAttribute('href') }));
        const helpBoxes = [...document.querySelectorAll('.help-box')].map((h) => text(h));
        const sidebar = [...document.querySelectorAll('aside a[href], nav a[href], .sidebar a[href]')].map((a) => new URL(a.getAttribute('href'), location.origin).pathname);
        const contentLinks = [...document.querySelectorAll('a[href]')].map((a) => new URL(a.getAttribute('href'), location.origin)).filter((u) => u.origin === location.origin && u.pathname.startsWith('/admin/')).map((u) => u.pathname + u.search);
        return { title: text(document.querySelector('h1.page-title')), metrics, tables, headerButtons, allButtons, helpBoxes, sidebar: [...new Set(sidebar)], contentLinks: [...new Set(contentLinks)], hasChartPlaceholder: !!document.querySelector('.chart-placeholder'), body: document.body.innerText };
    });
}

const metricMap = (snap) => Object.fromEntries(snap.metrics.map((m) => [m.label, m.value]));

async function sweep(page, who, locale) {
    for (const width of WIDTHS) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(BASE + DASH);
        const info = await page.evaluate(() => {
            const latinButtons = [...document.querySelectorAll('a.btn, button')].map((b) => b.textContent.replace(/\s+/g, ' ').trim()).filter((t) => t && /[A-Za-z]{3,}/.test(t) && !/[\u0600-\u06FF]/.test(t));
            const latinOther = [...document.querySelectorAll('.card.metric .label, .table-title > span, th, .help-box')].map((b) => b.textContent.replace(/\s+/g, ' ').trim()).filter((t) => t && /[A-Za-z]{3,}/.test(t) && !/[\u0600-\u06FF]/.test(t));
            return { scrollWidth: document.documentElement.scrollWidth, innerWidth: window.innerWidth, latinButtons: [...new Set(latinButtons)], latinOther: [...new Set(latinOther)] };
        });
        await page.screenshot({ path: path.join(SHOTS, `${PHASE}-${who}-${locale}-${width}.png`), fullPage: true });
        check(`${who} ${locale} ${width}px no horizontal overflow`, info.scrollWidth <= info.innerWidth + 1, { scrollWidth: info.scrollWidth, innerWidth: info.innerWidth });
        if (locale === 'ar') {
            check(`${who} ar ${width}px no untranslated action buttons`, info.latinButtons.length === 0, info.latinButtons);
            results.push({ phase: PHASE, name: `${who} ar ${width}px remaining Latin labels (recorded, not failed)`, ok: true, detail: info.latinOther });
        }
    }
    await page.setViewportSize({ width: 1440, height: 900 });
}

async function expectStatus(context, email, url, expected, label) {
    const page = await login(context, email);
    const resp = await page.goto(BASE + url);
    check(label, resp.status() === expected, { url, status: resp.status(), expected });
    await page.close();
}

async function checkLinks(context, snap, who) {
    const broken = [];
    for (const link of snap.contentLinks) {
        const r = await context.request.get(BASE + link, { maxRedirects: 0 });
        if (![200, 302].includes(r.status())) { broken.push({ link, status: r.status() }); }
    }
    check(`${who} every /admin/ link on the dashboard answers 200 or 302`, broken.length === 0, { total: snap.contentLinks.length, broken });
}

async function phase1(browser) {
    // Super Admin, English baseline
    const adminCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const admin = await login(adminCtx, 'admin@example.com');
    await setLocale(admin, 'en');
    const resp = await admin.goto(BASE + DASH);
    check('super admin opens HR dashboard', resp.status() === 200, resp.status());
    let snap = await snapshot(admin);
    check('super admin page title', snap.title === 'HR Dashboard', snap.title);
    check('super admin metrics exact', Object.entries(EXPECT_COMPANY).every(([k, v]) => metricMap(snap)[k] === v), metricMap(snap));
    check('super admin attendance summary table is real data', (() => { const t = snap.tables.find((x) => x.title === "Today's Attendance Summary"); return t && JSON.stringify(t.rows.map((r) => r[1])) === JSON.stringify(['3', '1', '1', '0']); })(), snap.tables[0]);
    check('super admin fake chart placeholder gone', !snap.hasChartPlaceholder && !snap.body.includes('Chart Placeholder'), snap.hasChartPlaceholder);
    check('super admin truthful note shown', snap.helpBoxes.includes(NOTE_REAL), snap.helpBoxes);
    check('super admin pending approvals rows', (() => { const t = snap.tables.find((x) => x.title === 'Pending HR Approvals'); return t && JSON.stringify(t.rows.map((r) => r[0] + '=' + r[1])) === JSON.stringify(['Leave Requests=3', 'Overtime Claims=3', 'Document Expiry=5', 'Payroll Approval=1', 'End of Service=2']); })(), snap.tables.find((x) => x.title === 'Pending HR Approvals')?.rows);
    check('super admin header actions', JSON.stringify(snap.headerButtons.map((b) => b.text)) === JSON.stringify(['+ Manual Attendance', '+ Add Employee']), snap.headerButtons);
    check('super admin sidebar has attendance and payroll entries', ['/admin/hr/attendance', '/admin/hr/payroll', '/admin/hr/overtime', '/admin/hr/eosb'].every((p) => snap.sidebar.includes(p)), snap.sidebar.filter((s) => s.startsWith('/admin/hr')));
    await checkLinks(adminCtx, snap, 'super admin');
    const enMetrics = snap.metrics.map((m) => m.value);

    // Super Admin, Arabic
    const loc = await setLocale(admin, 'ar');
    check('super admin Arabic locale applied', loc.dir === 'rtl', loc);
    snap = await snapshot(admin);
    check('super admin Arabic title', snap.title === ar('HR Dashboard'), snap.title);
    check('super admin Arabic header actions', JSON.stringify(snap.headerButtons.map((b) => b.text)) === JSON.stringify([ar('+ Manual Attendance'), ar('+ Add Employee')]), snap.headerButtons);
    check('super admin Arabic attendance action and pending Open links', (() => { const t = snap.tables.find((x) => x.actions.includes(ar('Open Attendance'))); const p = snap.tables.find((x) => x.rows.length === 5); return !!t && p.rows.every((r) => r[2] === ar('Open')); })(), snap.tables.map((t) => t.actions));
    check('super admin Arabic truthful note', snap.helpBoxes.includes(ar(NOTE_REAL)), snap.helpBoxes);
    check('super admin Arabic metric values identical to English', JSON.stringify(snap.metrics.map((m) => m.value)) === JSON.stringify(enMetrics), snap.metrics.map((m) => m.value));
    check('super admin Arabic View All', snap.allButtons.some((b) => b.text === ar('View All')), snap.allButtons.map((b) => b.text));
    await sweep(admin, 'super-admin', 'ar');
    await setLocale(admin, 'en');
    await sweep(admin, 'super-admin', 'en');
    await adminCtx.close();

    // HR Manager with full HR / Attendance / Payroll permissions
    const hrCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const hr = await login(hrCtx, 'waleed@example.com');
    await setLocale(hr, 'en');
    const r2 = await hr.goto(BASE + DASH);
    check('HR manager (full) opens HR dashboard', r2.status() === 200, r2.status());
    snap = await snapshot(hr);
    check('HR manager (full) metrics exact', Object.entries(EXPECT_COMPANY).every(([k, v]) => metricMap(snap)[k] === v), metricMap(snap));
    check('HR manager (full) sees Open Attendance and payroll rows', snap.allButtons.some((b) => b.text === 'Open Attendance') && PAYROLL_ROWS.every((r) => snap.body.includes(r)), snap.allButtons.map((b) => b.text));
    check('HR manager (full) header actions: Add Employee only (no Attendance create)', JSON.stringify(snap.headerButtons.map((b) => b.text)) === JSON.stringify(['+ Add Employee']), snap.headerButtons);
    await checkLinks(hrCtx, snap, 'HR manager (full)');
    await hrCtx.close();

    // Users without HR view are refused regardless of Attendance / Payroll permissions
    for (const [email, who] of [['nabeel@example.com', 'project manager (Attendance view, no HR view)'], ['zubair@example.com', 'finance manager (Payroll view, no HR view)'], ['zafar@example.com', 'site in-charge before HR view grant']]) {
        const ctx = await browser.newContext();
        await expectStatus(ctx, email, DASH, 403, `${who} gets 403 on HR dashboard`);
        await ctx.close();
    }
}

async function phase2(browser) {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await login(ctx, 'zafar@example.com');
    await setLocale(page, 'en');
    const resp = await page.goto(BASE + DASH);
    check('site in-charge (HR view granted) opens HR dashboard', resp.status() === 200, resp.status());
    const snap = await snapshot(page);
    const m = metricMap(snap);
    check('site in-charge metrics scoped to project 3 / site 4', Object.entries(EXPECT_SITE).every(([k, v]) => m[k] === v), m);
    check('site in-charge attendance cards visible (Attendance view)', ATTENDANCE_LABELS.every((l) => l in m), Object.keys(m));
    check('site in-charge payroll cards hidden (no Payroll view)', PAYROLL_LABELS.every((l) => !(l in m)) && !snap.body.includes('SAR '), Object.keys(m));
    check('site in-charge payroll rows hidden', PAYROLL_ROWS.every((r) => !snap.body.includes(r)), snap.tables.find((t) => t.title === 'Pending HR Approvals')?.rows);
    check('site in-charge attendance summary scoped', (() => { const t = snap.tables.find((x) => x.title === "Today's Attendance Summary"); return t && JSON.stringify(t.rows.map((r) => r[1]).slice(0, 3)) === JSON.stringify(['1', '0', '1']); })(), snap.tables[0]?.rows);
    check('site in-charge header: Manual Attendance yes, Add Employee no', JSON.stringify(snap.headerButtons.map((b) => b.text)) === JSON.stringify(['+ Manual Attendance']), snap.headerButtons);
    check('site in-charge sidebar has no payroll entries', ['/admin/hr/payroll', '/admin/hr/overtime', '/admin/hr/eosb'].every((p) => !snap.sidebar.includes(p)), snap.sidebar.filter((s) => s.startsWith('/admin/hr')));
    const own = await page.goto(BASE + '/admin/hr/employees/12');
    check('site in-charge opens own-site employee record', own.status() === 200, own.status());
    const forged = await page.goto(BASE + '/admin/hr/employees/7');
    check('site in-charge forged other-project employee record refused', forged.status() === 404, forged.status());
    const forgedAtt = await page.goto(BASE + '/admin/hr/attendance?employee_id=7');
    const attBody = await page.evaluate(() => document.body.innerText);
    check('site in-charge attendance register shows no project-1 rows', forgedAtt.status() === 200 && !/Nabeel|PRJ-001/.test(attBody) , forgedAtt.status());
    await ctx.close();
}

async function phase3(browser) {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await login(ctx, 'waleed@example.com');
    await setLocale(page, 'en');
    const resp = await page.goto(BASE + DASH);
    check('HR manager (restricted) opens HR dashboard', resp.status() === 200, resp.status());
    let snap = await snapshot(page);
    const m = metricMap(snap);
    check('HR manager (restricted) no attendance counts', ATTENDANCE_LABELS.every((l) => !(l in m)) && !snap.body.includes('Open Attendance'), Object.keys(m));
    check('HR manager (restricted) no payroll figures', PAYROLL_LABELS.every((l) => !(l in m)) && !snap.body.includes('SAR ') && PAYROLL_ROWS.every((r) => !snap.body.includes(r)), Object.keys(m));
    check('HR manager (restricted) non-sensitive cards still shown', ['Total Employees', 'Active Employees', 'On Leave Today', 'Pending Leaves', 'Expiring IQAMAs (60 days)', 'Expiring Documents (60 days)'].every((l) => m[l] === EXPECT_COMPANY[l]), m);
    check('HR manager (restricted) hidden-figures note shown, truthful note absent', snap.helpBoxes.includes(NOTE_HIDDEN) && !snap.helpBoxes.includes(NOTE_REAL), snap.helpBoxes);
    check('HR manager (restricted) pending rows', (() => { const t = snap.tables.find((x) => x.title === 'Pending HR Approvals'); return t && JSON.stringify(t.rows.map((r) => r[0])) === JSON.stringify(['Leave Requests', 'Document Expiry']); })(), snap.tables.find((x) => x.title === 'Pending HR Approvals')?.rows);
    check('HR manager (restricted) sidebar without attendance / payroll entries', ['/admin/hr/attendance', '/admin/hr/payroll', '/admin/hr/overtime', '/admin/hr/eosb'].every((p) => !snap.sidebar.includes(p)), snap.sidebar.filter((s) => s.startsWith('/admin/hr')));
    check('HR manager (restricted) no chart placeholder', !snap.hasChartPlaceholder, snap.hasChartPlaceholder);
    for (const [url, label] of [['/admin/hr/attendance', 'attendance register'], ['/admin/hr/payroll', 'payroll runs'], ['/admin/hr/overtime', 'overtime'], ['/admin/hr/eosb', 'end of service']]) {
        const r = await page.goto(BASE + url);
        check(`HR manager (restricted) direct ${label} URL refused`, r.status() === 403, { url, status: r.status() });
    }
    await checkLinks(ctx, snap, 'HR manager (restricted)');
    const loc = await setLocale(page, 'ar');
    check('HR manager (restricted) Arabic locale applied', loc.dir === 'rtl', loc);
    snap = await snapshot(page);
    check('HR manager (restricted) Arabic hidden-figures note', snap.helpBoxes.includes(ar(NOTE_HIDDEN)), snap.helpBoxes);
    check('HR manager (restricted) Arabic still no SAR figure or attendance counts', !snap.body.includes('SAR ') && ATTENDANCE_LABELS.every((l) => !(l in metricMap(snap))), Object.keys(metricMap(snap)));
    await sweep(page, 'hr-restricted', 'ar');
    await setLocale(page, 'en');
    await sweep(page, 'hr-restricted', 'en');
    await ctx.close();
}

(async () => {
    fs.mkdirSync(SHOTS, { recursive: true });
    fs.mkdirSync(path.dirname(OUT), { recursive: true });
    const browser = await chromium.launch({ executablePath: EDGE, headless: true });
    try {
        if (PHASE === 'phase1') { await phase1(browser); }
        else if (PHASE === 'phase2') { await phase2(browser); }
        else if (PHASE === 'phase3') { await phase3(browser); }
        else { throw new Error('unknown phase ' + PHASE); }
    } finally {
        await browser.close();
    }
    const previous = PHASE !== 'phase1' && fs.existsSync(OUT) ? JSON.parse(fs.readFileSync(OUT, 'utf8')) : { base: BASE, generatedAt: new Date().toISOString(), checks: [], pageErrors: [] };
    previous.checks.push(...results);
    previous.pageErrors.push(...pageErrors);
    previous.generatedAt = new Date().toISOString();
    const passed = previous.checks.filter((c) => c.ok && !c.name.includes('recorded, not failed')).length;
    const total = previous.checks.filter((c) => !c.name.includes('recorded, not failed')).length;
    previous.summary = { total, passed, failed: total - passed, pageErrors: previous.pageErrors.length };
    fs.writeFileSync(OUT, JSON.stringify(previous, null, 2));
    console.log(`\n${PHASE}: ${results.filter((r) => r.ok).length}/${results.filter((r) => !r.name.includes('recorded')).length} passed this phase; cumulative ${passed}/${total}; page errors ${pageErrors.length}`);
    process.exit(results.filter((r) => !r.ok).length ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
