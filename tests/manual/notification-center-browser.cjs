// Local-only Notification Center QA. Actual read/approval operations use the disposable fixture.
const fs = require('fs'), path = require('path'), os = require('os'), cp = require('child_process');
const { chromium } = require(process.env.SEERA_PLAYWRIGHT || 'D:/laragon2/www/property_handle/node_modules/playwright');
const root = process.argv[2];
if (!root || !/^seera-notifications-qa-[a-f0-9]{32}$/.test(path.basename(root))) throw new Error('Disposable notification fixture required');
const env = JSON.parse(fs.readFileSync(path.join(root, 'process-env.json'), 'utf8').replace(/^\uFEFF/, ''));
if (env.APP_URL !== 'http://127.0.0.1:18772' || env.DB_CONNECTION !== 'sqlite' || !/false|0/i.test(String(env.ZATCA_PRODUCTION_ENABLED))) throw new Error('Unsafe environment');
const BASE = env.APP_URL, facts = JSON.parse(fs.readFileSync(path.join(root, 'fixture.json'), 'utf8'));
const OUT = path.resolve('docs/release-readiness/evidence/notification-center-2026-11-01');
const SHOTS = path.join(os.tmpdir(), 'seera-notification-center-shots');
const results = [], errors = [];
const check = (name, ok, detail = null) => { results.push({ name, ok: !!ok, detail }); console.log(`${ok ? 'PASS' : 'FAIL'} ${name}`); };
const count = async page => {
    const badge = page.locator('.topbar details summary strong');
    return await badge.count() ? Number(await badge.textContent()) : 0;
};
async function login(ctx, name) {
    const page = await ctx.newPage(); page.setDefaultTimeout(90000); page.on('pageerror', e => errors.push(String(e)));
    await page.goto(BASE + '/login'); await page.fill('#email', facts.users[name].email); await page.fill('#password', 'password');
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]); await page.goto(BASE + '/admin/notifications'); return page;
}
async function post(ctx, page, url, data = {}) {
    return ctx.request.post(BASE + url, { form: { _token: await page.locator('input[name=_token]').first().inputValue(), ...data }, maxRedirects: 0 });
}
async function locale(page, lang) {
    await page.evaluate(async loc => { const f = document.querySelector('.locale-switch'); const d = new FormData(f); d.set('locale', loc); await fetch(f.action, { method: 'POST', body: d }); }, lang);
    await page.goto(BASE + '/admin/notifications');
}
(async () => {
    fs.mkdirSync(OUT, { recursive: true }); fs.mkdirSync(SHOTS, { recursive: true });
    const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe' });
    try {
        const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block' });
        await ctx.route('**/*', r => new URL(r.request().url()).origin === BASE ? r.continue() : r.abort());
        const page = await login(ctx, 'first');
        check('First approver sees 13 unread notifications', await count(page) === 13);
        check('Foreign recipient reference is absent', !(await page.locator('.page').innerText()).includes('NQA-FOREIGN-ID'));
        check('Foreign notification ID cannot be marked read', (await post(ctx, page, `/admin/notifications/${facts.foreign_notification_id}/read`)).status() === 404);
        check('Malformed notification ID refused', (await post(ctx, page, '/admin/notifications/not-a-uuid/read')).status() === 404);
        for (const lang of ['en', 'ar']) {
            await locale(page, lang);
            const expected = lang === 'ar' ? 'الإشعارات' : 'Notifications';
            check(`${lang} title and breadcrumb localized`, (await page.title()).startsWith(expected) && (await page.locator('.breadcrumb').innerText()).includes(expected));
            for (const width of [320, 390, 768, 1440]) {
                await page.setViewportSize({ width, height: 900 });
                await page.locator('.topbar details').evaluate(x => { x.open = false; });
                const closed = await page.evaluate(() => ({ width: innerWidth, scroll: document.documentElement.scrollWidth,
                    offenders: [...document.querySelectorAll('.page *')].filter(x => { const r = x.getBoundingClientRect(); return r.width && (r.left < -1 || r.right > innerWidth + 1); }).slice(0, 5).map(x => ({ tag: x.tagName, cls: x.className, text: x.textContent.trim().slice(0, 45) })) }));
                check(`${lang} inbox ${width}px no page overflow`, closed.scroll <= width, closed);
                await page.locator('.topbar details summary').click();
                const dropdown = await page.locator('.topbar details > div').evaluate(x => { const r = x.getBoundingClientRect(); return { left: r.left, right: r.right, width: innerWidth, scroll: document.documentElement.scrollWidth }; });
                check(`${lang} bell dropdown ${width}px stays inside viewport`, dropdown.left >= -1 && dropdown.right <= width + 1 && dropdown.scroll <= width, dropdown);
                if (width === 390) await page.screenshot({ path: path.join(SHOTS, `notification-${lang}-390.png`), fullPage: true });
                await page.locator('.topbar details').evaluate(x => { x.open = false; });
                if (lang === 'ar') {
                    const buttons = await page.locator('.page button, .topbar details a').allInnerTexts();
                    check(`Arabic actions ${width}px translated`, buttons.every(t => !/[A-Za-z]{3}/.test(t)), buttons);
                }
            }
        }
        const ownedRead = page.locator('.page form[action^="'+BASE+'/admin/notifications/"][action$="/read"] button').first();
        await Promise.all([page.waitForNavigation(), ownedRead.click()]);
        check('Individual mark as read reduces unread badge to 12', await count(page) === 12);
        await Promise.all([page.waitForNavigation(), page.locator('.page form[action$="/notifications/read-all"] button').click()]);
        check('Mark all clears unread badge and individual unread controls', await count(page) === 0 && await page.locator('.page form[action$="/read"]').count() === 0);
        const requesterCtx = await browser.newContext(); const requester = await login(requesterCtx, 'requester');
        await post(requesterCtx, requester, '/admin/notifications/read-all');
        const secondCtx = await browser.newContext(); const second = await login(secondCtx, 'second');
        check('Second approver has no premature request', await count(second) === 0);
        const decision = { instance_id: facts.instance_id, step_id: facts.steps[0] };
        const url = `/admin/inventory/purchase-requests/${facts.document_id}/approve`;
        check('First approval succeeds', (await post(ctx, page, url, decision)).status() === 302);
        await post(ctx, page, url, decision);
        await second.reload(); check('Next approver receives exactly one request after replay', await count(second) === 1 && (await second.locator('.page').innerText()).includes('NQA-PR'));
        check('Second approval succeeds', (await post(secondCtx, second, url, { instance_id: facts.instance_id, step_id: facts.steps[1] })).status() === 302);
        await requester.reload(); check('Completion reaches original requester', await count(requester) === 1 && (await requester.locator('.page').innerText()).includes('Approval completed'));
        const fixtureEnv = { ...process.env, ...env };
        cp.execFileSync('D:/laragon2/bin/php/php-8.3.16-Win32-vs16-x64/php.exe', ['tests/manual/notification-center-fixture.php', root, 'revoke'], { env: fixtureEnv });
        await page.reload(); check('Role permission loss hides references and badge', await count(page) === 0 && !(await page.locator('.page').innerText()).includes('NQA-PR'));
        cp.execFileSync('D:/laragon2/bin/php/php-8.3.16-Win32-vs16-x64/php.exe', ['tests/manual/notification-center-fixture.php', root, 'scope'], { env: fixtureEnv });
        await page.reload(); check('Project change hides old accessible references', !(await page.locator('.page').innerText()).includes('NQA-PR') && await count(page) === 0);
        await requesterCtx.close(); await secondCtx.close(); await ctx.close();
    } finally { await browser.close(); }
    const summary = { total: results.length, passed: results.filter(x => x.ok).length, failed: results.filter(x => !x.ok).length, pageErrors: errors.length };
    fs.writeFileSync(path.join(OUT, 'browser-results.json'), JSON.stringify({ base: BASE, summary, results, errors }, null, 2));
    console.log(JSON.stringify(summary)); process.exit(summary.failed || errors.length ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
