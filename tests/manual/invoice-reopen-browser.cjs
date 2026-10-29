// Invoice reopening dialog + bilingual ZATCA validation labels verification.
// Real Laravel browser run on the private, synthetic UAT fixture only; not client sign-off.
// node tests/manual/invoice-reopen-browser.cjs <playwright-module> <fixture-root> [screenshot-dir]
// Expects (synthetic fixture): invoices 9 (AR flow), 8 (EN flow), 7 (sweep + non-admin), all posted, unpaid,
// no receipts, no ZATCA lock; invoice 3 locked (zatca_locked_at set by the runner); ZATCA record of invoice 1
// validation_status = passed and of invoice 2 = failed (set by the runner, restored afterwards).
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
const shots = path.resolve(process.argv[4] || path.join(root, 'reopen-shots'));
fs.mkdirSync(shots, {recursive: true});
const out = path.resolve('docs/release-readiness/evidence/invoice-reopen-2026-10-29');
fs.mkdirSync(out, {recursive: true});
const WIDTHS = [320, 390, 768, 1440];
const results = [], errors = [], failedResources = [];
const AR = /[\u0600-\u06ff]/;
const INDEX = '/admin/accounting/accounts-receivable';
const show = n => `${INDEX}/${n}`;
const T = {
    en: {button: 'Reopen for Correction', title: 'Confirm invoice reopening', help: 'A reason is required and will be preserved in the invoice audit history.', label: 'Reason for reopening this invoice (kept on the record):', required: 'Enter a reopening reason before continuing.', cancel: 'Cancel', locked: 'This invoice has a stamped or submitted e-invoice and cannot be reopened. Issue a credit note instead.', passed: 'Passed', failed: 'Failed'},
    ar: {button: 'إعادة فتح للتصحيح', title: 'تأكيد إعادة فتح الفاتورة', help: 'يجب إدخال سبب وسيُحفظ في سجل تدقيق الفاتورة.', label: 'سبب إعادة فتح هذه الفاتورة (يُحفظ في السجل):', required: 'أدخل سبب إعادة الفتح قبل المتابعة.', cancel: 'إلغاء', locked: 'لهذه الفاتورة فاتورة إلكترونية مختومة أو مرسلة ولا يمكن إعادة فتحها. أصدر إشعاراً دائناً بدلاً من ذلك.', passed: 'اجتاز التحقق', failed: 'فشل التحقق'},
};

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
    const dialog = page => page.locator('dialog#invoice-reopen-dialog');
    const reopenForm = page => page.locator('form[data-invoice-reopen-form]');
    const isOpen = page => dialog(page).evaluate(d => d.open);
    const activeId = page => page.evaluate(() => document.activeElement && (document.activeElement.id || document.activeElement.getAttribute('data-invoice-reopen-cancel') !== null && 'cancel' || document.activeElement.getAttribute('data-invoice-reopen-confirm') !== null && 'confirm' || document.activeElement.tagName));
    const dialogBox = async page => { const b = await dialog(page).boundingBox(); const s = await size(page); return {box: b, viewport: s.viewport}; };
    const csrf = page => page.locator('input[name=_token]').first().inputValue();
    const forgedPost = (page, url, token, reason) => page.evaluate(async ({url, token, reason}) => {
        const r = await fetch(url, {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'text/html'}, body: new URLSearchParams({_token: token, reason}), redirect: 'manual'});
        return {status: r.status, type: r.type};
    }, {url, token, reason});

    // The full dialog flow on one eligible invoice: open, Escape, Cancel, empty reason, keyboard confirm.
    const dialogFlow = async (page, locale, invoice, width) => {
        const t = T[locale];
        await setLocale(page, locale);
        await page.setViewportSize({width, height: 900});
        await check(`dialog-${locale}-${invoice}-closed-by-default-and-button-translated`, async () => {
            await page.goto(base + show(invoice));
            assert.equal(await reopenForm(page).count(), 1, 'reopen form');
            assert.equal(await reopenForm(page).locator('button').innerText().then(x => x.trim()), t.button);
            assert.equal(await dialog(page).count(), 1, 'dialog element');
            assert.equal(await isOpen(page), false, 'dialog must start closed');
            assert.equal(await reopenForm(page).locator('input[name=reason]').inputValue(), '', 'hidden reason empty');
            assert.equal(await page.locator('form[onsubmit]').count(), 0, 'no inline prompt() handler left');
        });
        await check(`dialog-${locale}-${invoice}-opens-focused-translated-within-viewport`, async () => {
            await reopenForm(page).locator('button').click();
            assert.equal(await isOpen(page), true, 'dialog open');
            assert.equal(await activeId(page), 'invoice-reopen-reason', 'textarea focused');
            assert.equal(await dialog(page).locator('h2').innerText().then(x => x.trim()), t.title);
            assert.equal(await dialog(page).locator('p').innerText().then(x => x.trim()), t.help);
            assert.equal(await dialog(page).locator('label').innerText().then(x => x.trim()), t.label);
            assert.equal(await dialog(page).locator('[data-invoice-reopen-cancel]').innerText().then(x => x.trim()), t.cancel);
            assert.equal(await dialog(page).locator('[data-invoice-reopen-confirm]').innerText().then(x => x.trim()), t.button);
            const {box, viewport} = await dialogBox(page);
            assert.ok(box.x >= 0 && box.x + box.width <= viewport + 1, 'dialog inside viewport ' + JSON.stringify(box));
            await page.screenshot({path: path.join(shots, `dialog-${locale}-${invoice}-${width}.png`)});
            return box;
        });
        await check(`dialog-${locale}-${invoice}-escape-closes-without-submit`, async () => {
            const url = page.url();
            await page.keyboard.press('Escape');
            assert.equal(await isOpen(page), false, 'closed by Escape');
            assert.equal(page.url(), url, 'no navigation');
            assert.equal(await reopenForm(page).locator('input[name=reason]').inputValue(), '');
        });
        await check(`dialog-${locale}-${invoice}-cancel-closes-without-submit`, async () => {
            await reopenForm(page).locator('button').click();
            assert.equal(await isOpen(page), true);
            await dialog(page).locator('[data-invoice-reopen-cancel]').click();
            assert.equal(await isOpen(page), false, 'closed by Cancel');
            assert.equal(await reopenForm(page).count(), 1, 'still unpaid: reopen action still shown');
        });
        await check(`dialog-${locale}-${invoice}-empty-reason-refused-client-side`, async () => {
            const url = page.url();
            await reopenForm(page).locator('button').click();
            await dialog(page).locator('[data-invoice-reopen-confirm]').click();
            await page.waitForTimeout(300);
            assert.equal(await isOpen(page), true, 'dialog stays open');
            assert.equal(page.url(), url, 'no navigation');
            assert.equal(await page.locator('#invoice-reopen-reason').evaluate(e => e.validationMessage), t.required);
            await page.locator('#invoice-reopen-reason').fill('   ');
            await dialog(page).locator('[data-invoice-reopen-confirm]').click();
            await page.waitForTimeout(300);
            assert.equal(await isOpen(page), true, 'whitespace-only reason stays open');
            assert.equal(page.url(), url);
        });
        await check(`dialog-${locale}-${invoice}-keyboard-tab-order-and-enter-confirms`, async () => {
            await page.locator('#invoice-reopen-reason').fill('Browser QA reopen (' + locale + ', synthetic fixture)');
            await page.keyboard.press('Tab');
            assert.equal(await activeId(page), 'cancel', 'Tab reaches Cancel');
            await page.keyboard.press('Tab');
            assert.equal(await activeId(page), 'confirm', 'Tab reaches Confirm');
            await Promise.all([page.waitForNavigation(), page.keyboard.press('Enter')]);
            assert.match(page.url(), new RegExp(show(invoice) + '$'), 'back on the invoice');
            assert.ok(await page.locator('.alert.success.flash').count(), 'success flash');
            assert.equal(await reopenForm(page).count(), 0, 'reopen action gone');
            assert.equal(await page.locator('.page-actions form[action$="/approve"]').count(), 1, 'approve action back (draft)');
            const text = await pageText(page);
            assert.match(text, /Browser QA reopen \(/, 'reason kept in notes');
            assert.match(text, /\(reversal JV-\d{4}-\d{4}\)/, 'reversal journal referenced in notes');
            assert.match(text, /Reopened customer invoice/, 'activity entry');
            const flash = await page.locator('.alert.success.flash').innerText();
            return {flash: flash.trim().slice(0, 160)};
        });
        await page.setViewportSize({width: 1440, height: 1000});
    };

    try {
        const admin = await pageFor('admin@example.com');
        const zubair = await pageFor('zubair@example.com');

        // ---------- ZATCA validation labels (records adjusted by the runner) ----------
        for (const locale of ['ar', 'en']) {
            await setLocale(admin, locale);
            await check(`zatca-validation-label-${locale}-passed-and-failed`, async () => {
                await admin.goto(base + show(1));
                const row1 = await admin.locator('#zatca tr', {hasText: locale === 'ar' ? 'التحقق المحلي' : 'Local validation'}).first().innerText();
                assert.ok(row1.includes(T[locale].passed), 'passed label: ' + row1);
                await admin.goto(base + show(2));
                const row2 = await admin.locator('#zatca tr', {hasText: locale === 'ar' ? 'التحقق المحلي' : 'Local validation'}).first().innerText();
                assert.ok(row2.includes(T[locale].failed), 'failed label: ' + row2);
                return {row1: row1.trim(), row2: row2.trim()};
            });
        }

        // ---------- Dialog flows ----------
        await dialogFlow(admin, 'ar', 9, 1440);
        await dialogFlow(admin, 'en', 8, 390);

        // ---------- Dialog open at every width, both locales (invoice 7, never submitted) ----------
        for (const locale of ['ar', 'en']) {
            await setLocale(admin, locale);
            for (const width of WIDTHS) {
                await check(`dialog-open-${locale}-${width}`, async () => {
                    await admin.setViewportSize({width, height: 900});
                    await admin.goto(base + show(7));
                    await admin.waitForLoadState('networkidle');
                    await noOverflow(admin);
                    await reopenForm(admin).locator('button').click();
                    assert.equal(await isOpen(admin), true);
                    const {box, viewport} = await dialogBox(admin);
                    assert.ok(box.x >= 0 && box.x + box.width <= viewport + 1 && box.width >= Math.min(300, viewport - 40), 'dialog box ' + JSON.stringify(box));
                    await noOverflow(admin);
                    await admin.screenshot({path: path.join(shots, `dialog-open-${locale}-${width}.png`)});
                    await admin.keyboard.press('Escape');
                    assert.equal(await isOpen(admin), false);
                    return box;
                });
            }
            await admin.setViewportSize({width: 1440, height: 1000});
        }

        // ---------- Non-Super-Admin: no action, forged POST refused ----------
        for (const locale of ['ar', 'en']) {
            await setLocale(zubair, locale);
            await check(`non-super-admin-${locale}-no-action-and-forged-post-403`, async () => {
                await zubair.goto(base + show(7));
                assert.equal(await reopenForm(zubair).count(), 0, 'no reopen form');
                assert.equal(await dialog(zubair).count(), 0, 'no dialog markup');
                const r = await forgedPost(zubair, base + show(7) + '/reopen', await csrf(zubair), 'Forged by Accounts Manager');
                assert.equal(r.status, 403, 'forged reopen must be 403, got ' + JSON.stringify(r));
            });
        }
        await setLocale(admin, 'en');
        await check('invoice-7-still-unpaid-after-forged-attempt', async () => {
            await admin.goto(base + show(7));
            assert.equal(await reopenForm(admin).count(), 1, 'still eligible / unpaid');
            assert.doesNotMatch(await pageText(admin), /Forged by Accounts Manager/);
        });

        // ---------- ZATCA-locked invoice (3): action hidden, server refuses a forged reopen ----------
        for (const locale of ['ar', 'en']) {
            await setLocale(admin, locale);
            await check(`zatca-locked-${locale}-action-hidden-and-server-refuses`, async () => {
                await admin.goto(base + show(3));
                assert.equal(await reopenForm(admin).count(), 0, 'reopen action hidden for a locked invoice');
                const r = await forgedPost(admin, base + show(3) + '/reopen', await csrf(admin), 'Forged on a locked invoice');
                assert.ok(r.type === 'opaqueredirect' || r.status === 302, 'expected a redirect with errors, got ' + JSON.stringify(r));
                await admin.goto(base + show(3));
                const flash = await admin.locator('.alert.flash').first().innerText().catch(() => '');
                assert.ok(flash.includes(T[locale].locked), 'locked message: ' + flash);
                assert.equal(await reopenForm(admin).count(), 0);
                assert.doesNotMatch(await pageText(admin), /Forged on a locked invoice/, 'nothing written');
                return flash.trim();
            });
        }
    } catch (e) {
        results.push({id: 'harness', status: 'FAIL', detail: e.stack.slice(0, 1200)});
    } finally {
        await browser.close();
    }
    const summary = {
        run_at: new Date().toISOString(),
        branch: 'feature/seera-invoice-localization-final-2026-10-29',
        fixture: 'private synthetic UAT fixture (not client data)',
        widths: WIDTHS,
        checks: results.length,
        passed: results.filter(r => r.status === 'PASS').length,
        failed: results.filter(r => r.status === 'FAIL').map(r => r.id),
        page_errors: errors,
        failed_resources: failedResources.filter(r => !/\/login$/.test(r.url)),
        results,
    };
    fs.writeFileSync(path.join(out, 'invoice-reopen-browser-results.json'), JSON.stringify(summary, null, 2));
    console.log(JSON.stringify({checks: summary.checks, passed: summary.passed, failed: summary.failed, page_errors: errors.length}));
})();
