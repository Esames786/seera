// Project Reports overview verification (scope, permissions, filters, EN/AR, widths).
// Real Laravel browser run on the private, synthetic UAT fixture only; not client sign-off.
// node tests/manual/project-reports-browser.cjs <playwright-module> <fixture-root> [screenshot-dir]
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
const shots = path.resolve(process.argv[4] || path.join(root, 'project-reports-shots'));
fs.mkdirSync(shots, {recursive: true});
const out = path.resolve('docs/release-readiness/evidence/project-reports-2026-10-30');
fs.mkdirSync(out, {recursive: true});
const WIDTHS = [320, 390, 768, 1440];
const results = [], errors = [], failedResources = [], untranslated = {};
const AR = /[\u0600-\u06ff]/;
const allow = /^(SAR|GPS|IBAN|VAT|ZATCA|QR|KSA|PR|PO|GRN|CAT|km|h|KM|ID|PDF|CSV|XML|OK|UTC)$|^[A-Z]{1,3}-?[\d-]+$|^\d|^PRJ-/;
const latinOnly = t => /[A-Za-z]{3}/.test(t) && !AR.test(t) && !allow.test(t.trim());
const dict = JSON.parse(fs.readFileSync(path.resolve('lang/ar.json'), 'utf8'));
const ar = key => { assert.ok(dict[key], 'missing Arabic entry for "' + key + '"'); return dict[key]; };
const REPORT = '/admin/reports/projects';
const COST_REPORT = '/admin/accounting/reports/project-cost-report';
const EN = {
    title: 'Project Reports', breadcrumb: 'Reports / Project Reports',
    desc: 'Read-only project portfolio from permitted project records. Financial cost and margin remain in the posted-ledger report.',
    metrics: ['Projects in scope', 'Active projects in scope'], placeholder: 'Project code or name...', allStatus: 'All Status',
    statuses: ['Active', 'Planning', 'On hold', 'Completed', 'Inactive'], table: 'Project portfolio',
    headers: ['Project Code', 'Project Name', 'Customer', 'Project Manager', 'Status', 'Start', 'End', 'Sites', 'Actions'],
    empty: 'No projects match your access scope and selected filters.',
    financeNote: 'Financial cost, revenue and margin are calculated from posted journals in the existing Project Cost Report, not from this operational listing.',
    financeLink: 'Open Project Cost Report', hiddenNote: 'Financial figures are hidden because you do not have Financial Reports permission.',
};
// Synthetic fixture expectations (seeded data; see the fixture facts in the report).
const USERS = {
    admin: {email: 'admin@example.com', projects: ['PRJ-001', 'PRJ-002', 'PRJ-003'], finance: true, sites: true},
    nabeel: {email: 'nabeel@example.com', projects: ['PRJ-001', 'PRJ-002', 'PRJ-003'], finance: true, sites: true},   // Project Manager: home project 1 + manager of all three
    zafar: {email: 'zafar@example.com', projects: ['PRJ-003'], finance: false, sites: true},                           // Site In-Charge: project 3 / site 4
    abdullah: {email: 'abdullah@example.com', projects: ['PRJ-001', 'PRJ-002', 'PRJ-003'], finance: false, sites: false}, // Marketing Manager: company level, Projects view only
};
const STATUS = {'PRJ-001': 'active', 'PRJ-002': 'planning', 'PRJ-003': 'active'};

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
        const texts = await page.locator('.page label:visible, .page th:visible, .page button:visible, .page .btn:visible, .page h2:visible, .page h3:visible, .page .page-title:visible, .page .page-desc:visible, .page .table-title:visible, .page .small:visible, .page p:visible, .page .help-box:visible, .page .table-empty:visible, .page .metric .label:visible, .page option, .sidebar a:visible, .topbar .btn:visible').evaluateAll(els =>
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
    const rows = page => page.locator('.page table').first().locator('tbody tr').evaluateAll(trs => trs.map(tr => [...tr.querySelectorAll('td')].map(td => td.innerText.trim())).filter(r => r.length > 1));
    const codes = async page => (await rows(page)).map(r => r[0]);
    const metrics = page => page.locator('.page .metric .value').allInnerTexts().then(t => t.map(x => Number(x.trim())));
    const headers = page => page.locator('.page table').first().locator('thead th').allInnerTexts().then(t => t.map(x => x.trim()));
    const statusOptions = page => page.locator('select[name=status] option').evaluateAll(els => els.map(o => ({value: o.value, label: o.textContent.trim()})));
    const financeLink = page => page.locator(`.page .help-box a[href$="${COST_REPORT}"]`).count();
    const viewLinks = page => page.locator('.page table').first().locator('tbody a.btn').evaluateAll(as => as.map(a => new URL(a.getAttribute('href'), location.origin).pathname));
    const sidebarLink = page => page.locator(`.sidebar a[href$="${REPORT}"]`).count();

    try {
        // ---------- Per-user scope, metrics, finance link, sidebar ----------
        for (const [name, u] of Object.entries(USERS)) {
            const page = await pageFor(u.email);
            await setLocale(page, 'en');
            await check(`scope-${name}-listing-metrics-links`, async () => {
                const r = await page.goto(base + REPORT);
                assert.equal(r.status(), 200);
                const seen = await codes(page);
                assert.deepEqual(seen, u.projects, 'visible projects');
                const m = await metrics(page);
                assert.deepEqual(m, [u.projects.length, u.projects.filter(c => STATUS[c] === 'active').length], 'metrics');
                assert.equal(await financeLink(page), u.finance ? 1 : 0, 'Project Cost Report link');
                const text = await pageText(page);
                assert.ok(u.finance ? text.includes(EN.financeNote) : text.includes(EN.hiddenNote), 'finance note');
                assert.ok(!text.includes(u.finance ? EN.hiddenNote : EN.financeNote), 'no contradictory note');
                const h = await headers(page);
                assert.deepEqual(h, u.sites ? EN.headers : EN.headers.filter(x => x !== 'Sites'), 'headers (Sites column follows the Sites permission)');
                const links = await viewLinks(page);
                assert.equal(links.length, u.projects.length, 'one View link per row');
                assert.ok(links.every(l => /\/admin\/master\/projects\/\d+$/.test(l)), 'View links target the project record');
                assert.equal(await sidebarLink(page), 1, 'sidebar entry present');
                return {projects: seen, metrics: m, financeLink: !!u.finance, headers: h.length};
            });
            await check(`scope-${name}-search-out-of-scope-reveals-nothing`, async () => {
                const hidden = Object.keys(STATUS).filter(c => !u.projects.includes(c));
                for (const c of hidden.length ? hidden : ['PRJ-404']) {
                    await page.goto(base + REPORT + '?search=' + c);
                    assert.deepEqual(await codes(page), [], 'forged search ' + c);
                    assert.ok((await pageText(page)).includes(EN.empty), 'empty-state message');
                    assert.deepEqual(await metrics(page), [0, 0], 'metrics follow the filter');
                }
                for (const c of u.projects) { await page.goto(base + REPORT + '?search=' + c); assert.deepEqual(await codes(page), [c], 'own project search ' + c); }
                return hidden;
            });
        }

        // ---------- Super Admin: filters, English baseline ----------
        const admin = await pageFor(USERS.admin.email);
        await setLocale(admin, 'en');
        await check('filters-search-status-and-forged-status', async () => {
            await admin.goto(base + REPORT + '?status=planning');
            assert.deepEqual(await codes(admin), ['PRJ-002']);
            assert.deepEqual(await metrics(admin), [1, 0]);
            await admin.goto(base + REPORT + '?status=active');
            assert.deepEqual(await codes(admin), ['PRJ-001', 'PRJ-003']);
            assert.deepEqual(await metrics(admin), [2, 2]);
            await admin.goto(base + REPORT + '?search=jeddah');
            assert.deepEqual(await codes(admin), ['PRJ-002'], 'search by name');
            await admin.goto(base + REPORT + '?search=PRJ-00&status=active');
            assert.deepEqual(await codes(admin), ['PRJ-001', 'PRJ-003'], 'combined filters');
            assert.equal(await admin.locator('[name=search]').inputValue(), 'PRJ-00', 'search term kept');
            assert.equal(await admin.locator('[name=status]').inputValue(), 'active', 'status kept');
            const r = await admin.goto(base + REPORT + '?status=forged');
            assert.equal(r.status(), 200, 'forged status is answered without a server error');
            assert.ok(await admin.locator('.alert.flash').count(), 'validation message shown for a forged status');
            assert.match(admin.url(), /\/admin\/(reports\/projects|dashboard)/, 'redirected back');
            await admin.goto(base + REPORT);
            assert.deepEqual(await codes(admin), USERS.admin.projects, 'Reset shows everything');
            return 'ok';
        });
        await check('english-baseline-exact', async () => {
            await admin.goto(base + REPORT);
            assert.equal(await admin.locator('.page h1.page-title').innerText().then(t => t.trim()), EN.title);
            assert.equal(await admin.locator('.page p.page-desc').innerText().then(t => t.trim()), EN.desc);
            assert.match(await admin.locator('.topbar').innerText(), new RegExp(EN.breadcrumb));
            assert.deepEqual(await admin.locator('.page .metric .label').allInnerTexts().then(t => t.map(x => x.trim())), EN.metrics);
            assert.equal(await admin.locator('[name=search]').getAttribute('placeholder'), EN.placeholder);
            assert.deepEqual((await statusOptions(admin)).map(o => o.label), [EN.allStatus, ...EN.statuses]);
            assert.deepEqual((await statusOptions(admin)).map(o => o.value), ['', 'active', 'planning', 'on hold', 'completed', 'inactive']);
            assert.deepEqual(await headers(admin), EN.headers);
            assert.match(await admin.locator('.page .table-title').innerText(), new RegExp(EN.table));
            assert.equal(await admin.locator('.page .help-box a.btn').innerText().then(t => t.trim()), EN.financeLink);
        });
        await check('project-cost-report-link-opens-existing-report', async () => {
            await admin.goto(base + REPORT);
            await Promise.all([admin.waitForNavigation(), admin.locator(`.page .help-box a[href$="${COST_REPORT}"]`).click()]);
            assert.match(admin.url(), new RegExp(COST_REPORT + '$'));
            const text = await pageText(admin);
            assert.match(text, /Project Cost Report/i);
            const numbers = (text.match(/\d[\d,]*\.\d{2}/g) || []);
            assert.ok(numbers.length >= 3, 'ledger figures rendered: ' + numbers.length);
            return {figures: numbers.length};
        });

        // ---------- Arabic: exact labels from lang/ar.json, option values unchanged ----------
        await setLocale(admin, 'ar');
        await check('arabic-labels-exact-and-values-unchanged', async () => {
            await admin.goto(base + REPORT);
            assert.equal(await admin.locator('.page h1.page-title').innerText().then(t => t.trim()), ar(EN.title));
            assert.equal(await admin.locator('.page p.page-desc').innerText().then(t => t.trim()), ar(EN.desc));
            assert.match(await admin.locator('.topbar').innerText(), new RegExp(ar(EN.breadcrumb)));
            assert.deepEqual(await admin.locator('.page .metric .label').allInnerTexts().then(t => t.map(x => x.trim())), EN.metrics.map(ar));
            assert.equal(await admin.locator('[name=search]').getAttribute('placeholder'), ar(EN.placeholder));
            const opts = await statusOptions(admin);
            assert.deepEqual(opts.map(o => o.label), [ar(EN.allStatus), ...EN.statuses.map(ar)]);
            assert.deepEqual(opts.map(o => o.value), ['', 'active', 'planning', 'on hold', 'completed', 'inactive'], 'status values unchanged');
            assert.deepEqual(await headers(admin), EN.headers.map(ar));
            assert.match(await admin.locator('.page .table-title').innerText(), new RegExp(ar(EN.table)));
            assert.equal(await admin.locator('.page .help-box a.btn').innerText().then(t => t.trim()), ar(EN.financeLink));
            assert.ok((await pageText(admin)).includes(ar(EN.financeNote)), 'finance note Arabic');
            assert.deepEqual(await codes(admin), USERS.admin.projects, 'same rows');
            assert.match(await admin.locator('.sidebar a[href$="' + REPORT + '"]').innerText(), AR, 'sidebar label Arabic');
            await admin.goto(base + REPORT + '?search=PRJ-404');
            assert.ok((await pageText(admin)).includes(ar(EN.empty)), 'empty state Arabic');
            return opts.map(o => o.label);
        });
        const zafar = await pageFor(USERS.zafar.email);
        await setLocale(zafar, 'ar');
        await check('arabic-hidden-figures-note-for-site-user', async () => {
            await zafar.goto(base + REPORT);
            assert.ok((await pageText(zafar)).includes(ar(EN.hiddenNote)), 'hidden-figures note Arabic');
            assert.equal(await financeLink(zafar), 0);
            assert.deepEqual(await codes(zafar), USERS.zafar.projects);
        });
        await sweep(admin, 'admin', 'project-reports', REPORT, 'ar');
        await sweep(zafar, 'zafar', 'project-reports', REPORT, 'ar');
        await setLocale(admin, 'en');
        await setLocale(zafar, 'en');
        await sweep(admin, 'admin', 'project-reports', REPORT, 'en');
        await sweep(zafar, 'zafar', 'project-reports', REPORT, 'en');

        // ---------- Restricted: site user cannot open an out-of-scope project record from a forged link ----------
        await check('site-user-forged-project-record-refused', async () => {
            await zafar.goto(base + REPORT);
            const own = (await viewLinks(zafar))[0];
            const r1 = await zafar.goto(base + own);
            assert.equal(r1.status(), 200, 'own project record opens');
            const r2 = await zafar.goto(base + '/admin/master/projects/1');
            assert.ok([403, 404].includes(r2.status()), 'out-of-scope project record refused, got ' + r2.status());
            return {own, forged: r2.status()};
        });

        // ---------- No Projects view: 403 and no sidebar entry ----------
        await check('hr-manager-without-projects-view-gets-403-and-no-sidebar-entry', async () => {
            const waleed = await pageFor('waleed@example.com');
            await waleed.goto(base + '/admin/dashboard');
            assert.equal(await sidebarLink(waleed), 0, 'no sidebar entry');
            const r = await waleed.goto(base + REPORT);
            assert.equal(r.status(), 403);
            await setLocale(waleed, 'ar');
            const r2 = await waleed.goto(base + REPORT);
            assert.equal(r2.status(), 403);
            await setLocale(waleed, 'en');
        });
    } catch (e) {
        results.push({id: 'harness', status: 'FAIL', detail: e.stack.slice(0, 1200)});
    } finally {
        await browser.close();
    }
    const summary = {
        run_at: new Date().toISOString(),
        branch: 'feature/seera-project-reports-mvp-2026-10-29',
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
    fs.writeFileSync(path.join(out, 'project-reports-browser-results.json'), JSON.stringify(summary, null, 2));
    console.log(JSON.stringify({checks: summary.checks, passed: summary.passed, failed: summary.failed, page_errors: errors.length}));
})();
