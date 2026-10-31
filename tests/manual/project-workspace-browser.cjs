// Project workspace closure-UX browser verification (headless Edge via Playwright) against the isolated synthetic UAT fixture.
// Usage: node tests/manual/project-workspace-browser.cjs
// Never run against production. Screenshots go to a temp folder and are not committed. No fixture data is changed:
// the only write is a no-op save of the project profile with its current values by the Super Admin.
const fs = require('fs');
const os = require('os');
const path = require('path');
const { chromium } = require('D:/laragon2/www/property_handle/node_modules/playwright');

const BASE = process.env.SEERA_BASE || 'http://127.0.0.1:18769';
const EDGE = process.env.SEERA_EDGE || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const OUT = process.env.SEERA_OUT || path.resolve(__dirname, '../../docs/release-readiness/evidence/closure-ux-projects-2026-10-31/project-workspace-browser-results.json');
const SHOTS = process.env.SEERA_SHOTS || path.join(os.tmpdir(), 'seera-project-workspace-shots');
const AR = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../../lang/ar.json'), 'utf8'));
const WIDTHS = [320, 390, 768, 1440];
const PASSWORD = 'password';
const P1 = { id: 1, code: 'PRJ-001', name: 'Riyadh Tower', budget: 'SAR 2,500,000.00' };
const P3 = { id: 3, code: 'PRJ-003', name: 'Dammam Road Extension', budget: 'SAR 3,800,000.00' };
const show = (p) => `/admin/master/projects/${p.id}`;
const edit = (p) => `/admin/master/projects/${p.id}/edit`;
const budget = (p) => `/admin/master/projects/${p.id}/budget`;
const reports = (p) => `/admin/reports/projects?search=${p.code}`;
const IDENTITY_LABELS = ['Customer', 'Manager', 'Classification', 'Branch', 'Start', 'End', 'Master budget', 'Detailed current budget'];
const TOOLTIP = 'Detailed budget / BOQ, versions and Budget vs Actual';

const results = [];
const pageErrors = [];
const check = (name, ok, detail) => { results.push({ name, ok: !!ok, detail: detail === undefined ? null : detail }); console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${detail !== undefined ? ' :: ' + JSON.stringify(detail) : ''}`); };
const record = (name, detail) => { results.push({ name: name + ' (recorded, not failed)', ok: true, detail }); };
const ar = (key) => { if (!(key in AR)) { throw new Error('missing Arabic entry for ' + key); } return AR[key]; };

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

async function snap(page) {
    return page.evaluate(() => {
        const text = (el) => (el ? el.textContent.replace(/\s+/g, ' ').trim() : '');
        const identity = document.querySelector('[data-project-identity]');
        const spans = identity ? [...identity.querySelectorAll(':scope > div:not(.table-title) > span')].map(text) : [];
        const identityLinks = identity ? [...identity.querySelectorAll('a.btn')].map((a) => ({ text: text(a), href: new URL(a.getAttribute('href'), location.origin).pathname + new URL(a.getAttribute('href'), location.origin).search })) : [];
        const tabs = [...document.querySelectorAll('[data-workspace-nav] a.tab')].map((a) => ({ text: text(a), href: a.getAttribute('href'), title: a.getAttribute('title'), colonTitle: a.getAttribute(':title') }));
        const overview = [...document.querySelectorAll('.table-card')].map((t) => ({ title: text(t.querySelector('.table-title > span')), rows: [...t.querySelectorAll('tbody tr')].map((r) => [text(r.querySelector('th')), text(r.querySelector('td'))]) }));
        return {
            docTitle: document.title,
            title: text(document.querySelector('h1.page-title')),
            desc: text(document.querySelector('.page-desc')),
            breadcrumb: text(document.querySelector('.breadcrumb')),
            headerButtons: [...document.querySelectorAll('.page-header a.btn, .page-head a.btn, h1 ~ * a.btn')].map((a) => ({ text: text(a), href: new URL(a.getAttribute('href'), location.origin).pathname })),
            identityAria: identity?.getAttribute('aria-label'),
            identityTitle: text(identity?.querySelector('.table-title strong')),
            identitySpans: spans,
            identityLinks,
            tabs,
            navAria: document.querySelector('[data-workspace-nav]')?.getAttribute('aria-label'),
            overview,
            hasSaveAction: !!document.querySelector('[name="_save_action"]'),
            formAction: document.querySelector('form[action*="/projects/"]')?.getAttribute('action') || null,
            formFields: [...document.querySelectorAll('form[action*="/projects/"] input[name], form[action*="/projects/"] select[name], form[action*="/projects/"] textarea[name]')].map((f) => f.name).filter((n) => n !== '_token' && n !== '_method'),
            flash: text(document.querySelector('.alert.flash')),
            body: document.body.innerText,
            rawHasColonTitle: document.documentElement.outerHTML.includes(':title='),
            links: [...new Set([...document.querySelectorAll('a[href]')].map((a) => new URL(a.getAttribute('href'), location.origin)).filter((u) => u.origin === location.origin && u.pathname.startsWith('/admin/') && !u.pathname.includes('/workspace/')).map((u) => u.pathname + u.search))],
        };
    });
}

async function checkLinks(context, links, who) {
    const broken = [];
    for (const link of links) {
        const r = await context.request.get(BASE + link, { maxRedirects: 0 });
        if (![200, 302].includes(r.status())) { broken.push({ link, status: r.status() }); }
    }
    check(`${who} every /admin/ link answers 200 or 302`, broken.length === 0, { total: links.length, broken });
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
                latinTabs: [...new Set([...document.querySelectorAll('[data-workspace-nav] a.tab')].map(t).filter(latin))],
                latinLabels: [...new Set([...document.querySelectorAll('.table-title > span, th, .page-desc, .breadcrumb, label, [data-project-identity] strong')].map(t).filter(latin))],
            };
        });
        await page.screenshot({ path: path.join(SHOTS, `${who}${tag}-${locale}-${width}.png`), fullPage: true });
        check(`${who}${tag} ${locale} ${width}px no horizontal overflow`, info.scrollWidth <= info.innerWidth + 1, { scrollWidth: info.scrollWidth, innerWidth: info.innerWidth });
        if (locale === 'ar') {
            check(`${who}${tag} ar ${width}px no untranslated action buttons`, info.latinButtons.length === 0, info.latinButtons);
            record(`${who}${tag} ar ${width}px remaining Latin tabs and labels (shared workspace panels, CU-03)`, { tabs: info.latinTabs, labels: info.latinLabels });
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
        // ---- Super Admin, English: read-only view
        const adminCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const admin = await login(adminCtx, 'admin@example.com');
        await setLocale(admin, 'en');
        check('super admin opens project view', await status(admin, show(P1)) === 200);
        let s = await snap(admin);
        check('view: title, breadcrumb, description', s.title === 'Project: ' + P1.name && s.docTitle.startsWith('Project View') && s.breadcrumb.endsWith('Master Setup / Projects / View') && s.desc === 'Read-only project context. Open Edit / Manage to update the profile.', { title: s.title, docTitle: s.docTitle, breadcrumb: s.breadcrumb, desc: s.desc });
        check('view: header actions Edit / Manage and Back to Projects', JSON.stringify(s.headerButtons.map((b) => b.text)) === JSON.stringify(['Edit / Manage', 'Back to Projects']) && s.headerButtons[0].href === edit(P1), s.headerButtons);
        check('view: identity labels and master budget', s.identityTitle === `${P1.code} / ${P1.name}` && IDENTITY_LABELS.every((l) => s.identitySpans.some((x) => x.startsWith(l + ':'))) && s.identitySpans.includes('Master budget: ' + P1.budget) && s.identitySpans.includes('Detailed current budget: none yet'), s.identitySpans);
        check('view: direct links to Project Reports (searched by code) and Budget / BOQ', JSON.stringify(s.identityLinks) === JSON.stringify([{ text: 'Open Project Reports', href: reports(P1) }, { text: 'Budget / BOQ', href: budget(P1) }]), s.identityLinks);
        const boqTab = s.tabs.find((t) => t.text === 'Budget / BOQ');
        check('view: Budget / BOQ tab carries a real title attribute (CU-01)', !!boqTab && boqTab.title === TOOLTIP && boqTab.colonTitle === null && !s.rawHasColonTitle, boqTab);
        check('view: Overview tab and sections nav label', s.tabs[0]?.text === 'Overview' && s.navAria === 'Project sections', { tabs: s.tabs.map((t) => t.text), navAria: s.navAria });
        const ov = s.overview.find((t) => t.title === 'Project Overview (read-only)');
        check('view: read-only overview rows, no save control', !!ov && JSON.stringify(ov.rows.map((r) => r[0])) === JSON.stringify(['Location', 'Description', 'Workflow']) && ov.rows[0][1] === 'Al Olaya District, Riyadh' && !s.hasSaveAction && s.formAction === null, { rows: ov?.rows, hasSaveAction: s.hasSaveAction, formAction: s.formAction });
        const viewLinks = s.links;
        await checkLinks(adminCtx, viewLinks, 'super admin project view');
        // Follow the connected links
        check('super admin: Open Project Reports from the project', await status(admin, reports(P1)) === 200);
        let rep = await admin.evaluate(() => ({ codes: [...document.querySelectorAll('tbody tr td:first-child')].map((d) => d.textContent.trim()), search: document.querySelector('input[name="search"]')?.value }));
        check('project reports page is pre-filtered to the project', JSON.stringify(rep.codes) === JSON.stringify([P1.code]) && rep.search === P1.code, rep);
        check('super admin: Budget / BOQ from the project', await status(admin, budget(P1)) === 200);
        const budgetBody = await admin.evaluate(() => document.body.innerText);
        check('budget page belongs to the project', budgetBody.includes(P1.code) && budgetBody.includes(P1.name), null);
        const enIdentity = s.identitySpans;
        const enSummary = s.identitySpans.length;

        // ---- Super Admin, English: edit workspace stays functional
        check('super admin opens project edit', await status(admin, edit(P1)) === 200);
        s = await snap(admin);
        check('edit: title, breadcrumb, description', s.title === 'Project Workspace: ' + P1.name && s.docTitle.startsWith('Project Workspace') && s.breadcrumb.endsWith('Master Setup / Projects / Edit Manage') && s.desc === 'Save the profile here. Related records remain independent; no Save All or automatic business actions.', { title: s.title, breadcrumb: s.breadcrumb, desc: s.desc });
        check('edit: header actions View (read-only) and Back to Projects', JSON.stringify(s.headerButtons.map((b) => b.text)) === JSON.stringify(['View (read-only)', 'Back to Projects']) && s.headerButtons[0].href === show(P1), s.headerButtons);
        check('edit: form with save action and identity links', s.hasSaveAction && s.formAction && s.formAction.endsWith('/projects/1') && s.formFields.includes('name') && JSON.stringify(s.identityLinks.map((l) => l.href)) === JSON.stringify([reports(P1), budget(P1)]), { formAction: s.formAction, fields: s.formFields });
        const beforeName = await admin.inputValue('form[action*="/projects/1"] input[name="name"]');
        await Promise.all([admin.waitForNavigation(), admin.evaluate(() => { const f = document.querySelector('form[action*="/projects/1"]'); const b = f.querySelector('button[name="_save_action"]') || f.querySelector('button[type="submit"]'); b.click(); })]);
        const afterSave = await snap(admin);
        check('edit: saving the unchanged profile succeeds and keeps the data', afterSave.flash === 'Project saved successfully.' && afterSave.body.includes(P1.name) && beforeName === P1.name, { flash: afterSave.flash, url: admin.url() });
        const savedIdentity = await admin.evaluate(() => [...document.querySelectorAll('[data-project-identity] > div:not(.table-title) > span')].map((x) => x.textContent.replace(/\s+/g, ' ').trim()));
        check('edit: master budget and summary unchanged after save', savedIdentity.includes('Master budget: ' + P1.budget) && savedIdentity.length === enSummary, savedIdentity);

        // ---- Super Admin, Arabic
        await setLocale(admin, 'ar');
        await admin.goto(BASE + show(P1));
        s = await snap(admin);
        check('Arabic view: rtl, title, breadcrumb, description', s.body.length > 0 && (await admin.evaluate(() => document.documentElement.dir)) === 'rtl' && s.title === ar('Project:') + ' ' + P1.name && s.docTitle.startsWith(ar('Project View')) && s.breadcrumb.endsWith(ar('Master Setup / Projects / View')) && s.desc === ar('Read-only project context. Open Edit / Manage to update the profile.'), { title: s.title, docTitle: s.docTitle, breadcrumb: s.breadcrumb, desc: s.desc });
        check('Arabic view: header actions', JSON.stringify(s.headerButtons.map((b) => b.text)) === JSON.stringify([ar('Edit / Manage'), ar('Back to Projects')]), s.headerButtons);
        check('Arabic view: identity labels exact and values identical', IDENTITY_LABELS.every((l, i) => s.identitySpans[i] && s.identitySpans[i].startsWith(ar(l) + ':') && s.identitySpans[i].slice(s.identitySpans[i].indexOf(':') + 1).replace(ar('Not assigned'), 'Not assigned').replace(ar('Not set'), 'Not set').replace(ar('none yet'), 'none yet') === enIdentity[i].slice(enIdentity[i].indexOf(':') + 1)), { ar: s.identitySpans.slice(0, 8), en: enIdentity.slice(0, 8) });
        check('Arabic view: identity aria-label, links and tabs exact', s.identityAria === ar('Project identity') && JSON.stringify(s.identityLinks.map((l) => l.text)) === JSON.stringify([ar('Open Project Reports'), ar('Budget / BOQ')]) && s.tabs[0]?.text === ar('Overview') && s.tabs[1]?.text === ar('Budget / BOQ') && s.tabs[1]?.title === ar(TOOLTIP) && s.navAria === ar('Project sections'), { aria: s.identityAria, links: s.identityLinks, tabs: s.tabs.slice(0, 2), navAria: s.navAria });
        const ovAr = s.overview.find((t) => t.title === ar('Project Overview (read-only)'));
        check('Arabic view: overview table exact', !!ovAr && JSON.stringify(ovAr.rows.map((r) => r[0])) === JSON.stringify([ar('Location'), ar('Description'), ar('Workflow')]) && ovAr.rows[2][1] === ar('Related records save independently. Posting, receiving and payment remain explicit actions in their business screens. No project progress percentage is calculated.') && !s.hasSaveAction, ovAr?.rows.map((r) => r[0]));
        await sweep(admin, 'super-admin', 'ar', show(P1), '-view');
        await admin.goto(BASE + edit(P1));
        s = await snap(admin);
        check('Arabic edit: title, breadcrumb, description, actions, save control', s.title === ar('Project Workspace:') + ' ' + P1.name && s.docTitle.startsWith(ar('Project Workspace')) && s.breadcrumb.endsWith(ar('Master Setup / Projects / Edit Manage')) && s.desc === ar('Save the profile here. Related records remain independent; no Save All or automatic business actions.') && JSON.stringify(s.headerButtons.map((b) => b.text)) === JSON.stringify([ar('View (read-only)'), ar('Back to Projects')]) && s.hasSaveAction, { title: s.title, breadcrumb: s.breadcrumb, buttons: s.headerButtons, hasSaveAction: s.hasSaveAction });
        await sweep(admin, 'super-admin', 'ar', edit(P1), '-edit');
        await setLocale(admin, 'en');
        await sweep(admin, 'super-admin', 'en', show(P1), '-view');
        await sweep(admin, 'super-admin', 'en', edit(P1), '-edit');
        await adminCtx.close();

        // ---- Project Manager (Projects view + edit, project 1)
        const pmCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const pm = await login(pmCtx, 'nabeel@example.com');
        await setLocale(pm, 'en');
        check('project manager opens project view', await status(pm, show(P1)) === 200);
        s = await snap(pm);
        check('project manager sees Edit / Manage and the connected links', s.headerButtons.some((b) => b.text === 'Edit / Manage') && s.identityLinks.length === 2, { buttons: s.headerButtons, links: s.identityLinks });
        check('project manager opens edit', await status(pm, edit(P1)) === 200);
        check('project manager: Project Reports pre-filtered', await status(pm, reports(P1)) === 200 && JSON.stringify(await pm.evaluate(() => [...document.querySelectorAll('tbody tr td:first-child')].map((d) => d.textContent.trim()))) === JSON.stringify([P1.code]));
        check('project manager: Budget / BOQ opens', await status(pm, budget(P1)) === 200);
        await pmCtx.close();

        // ---- Marketing Manager (Projects view only, company level): view yes, edit no
        const mkCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const mk = await login(mkCtx, 'abdullah@example.com');
        await setLocale(mk, 'en');
        check('view-only role opens project view', await status(mk, show(P1)) === 200);
        s = await snap(mk);
        check('view-only role: no Edit / Manage, links present, no save control', !s.headerButtons.some((b) => b.text === 'Edit / Manage') && s.identityLinks.length === 2 && !s.hasSaveAction, { buttons: s.headerButtons, links: s.identityLinks });
        check('view-only role: edit URL refused', await status(mk, edit(P1)) === 403);
        check('view-only role: Project Reports and Budget / BOQ open', await status(mk, reports(P1)) === 200 && await status(mk, budget(P1)) === 200);
        await mkCtx.close();

        // ---- Site In-Charge (Projects view, project 3 / site 4): scope
        const siteCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const site = await login(siteCtx, 'zafar@example.com');
        await setLocale(site, 'en');
        check('site in-charge opens own project view', await status(site, show(P3)) === 200);
        s = await snap(site);
        check('site in-charge: own project identity, links and master budget', s.identityTitle === `${P3.code} / ${P3.name}` && s.identitySpans.includes('Master budget: ' + P3.budget) && JSON.stringify(s.identityLinks.map((l) => l.href)) === JSON.stringify([reports(P3), budget(P3)]) && !s.headerButtons.some((b) => b.text === 'Edit / Manage'), { spans: s.identitySpans.slice(0, 8), links: s.identityLinks });
        check('site in-charge: Project Reports pre-filtered to own project only', await status(site, reports(P3)) === 200 && JSON.stringify(await site.evaluate(() => [...document.querySelectorAll('tbody tr td:first-child')].map((d) => d.textContent.trim()))) === JSON.stringify([P3.code]));
        check('site in-charge: forged search for another project returns no row', await status(site, reports(P1)) === 200 && (await site.evaluate(() => [...document.querySelectorAll('tbody tr td:first-child')].map((d) => d.textContent.trim()))).every((c) => c !== P1.code));
        check('site in-charge: own Budget / BOQ opens', await status(site, budget(P3)) === 200);
        for (const [url, label] of [[show(P1), 'other project view'], [edit(P1), 'other project edit'], [budget(P1), 'other project budget']]) {
            check(`site in-charge: forged ${label} refused`, await status(site, url) === 404, url);
        }
        check('site in-charge: own project edit refused without Projects edit', await status(site, edit(P3)) === 403);
        await siteCtx.close();

        // ---- Roles without Projects view
        for (const [email, who] of [['waleed@example.com', 'HR manager (Financial Reports view, no Projects view)'], ['zubair@example.com', 'finance manager (no Projects view)']]) {
            const ctx = await browser.newContext();
            const p = await login(ctx, email);
            for (const [url, label] of [[show(P1), 'project view'], [edit(P1), 'project edit'], [budget(P1), 'budget'], [reports(P1), 'project reports']]) {
                check(`${who}: ${label} refused`, await status(p, url) === 403, url);
            }
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
