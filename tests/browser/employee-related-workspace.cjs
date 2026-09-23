// Real application JS, isolated HTTP fixtures; no production server or database.
const { chromium } = require(process.argv[2] || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const read = name => fs.readFileSync(path.join(__dirname, '../../resources/js/', name), 'utf8');
const source = read('employee-related-panels.js').replace('export function', 'function') + '\n'
    + read('employee-workspace.js').replace(/^import .*;\r?\n/m, '');
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PREVIEW_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    let checks = 0;
    const check = (condition, name) => { assert.ok(condition, name); checks++; console.log('PASS ' + name); };
    const keys = ['personal', 'employment', 'payroll', 'documents', 'access'];
    const fixture = `<!doctype html><html><body>
        <a href="/leave" id="leave">Leave</a>
        <nav class="employee-workspace-nav" hidden>${keys.map(key => `<a href="#${key}" data-employee-section="${key}">${key}</a>`).join('')}
        <a href="#leaves" data-employee-related="leaves" data-related-url="/panel/leaves">Leaves</a>
        <a href="#salary" data-employee-related="salary" data-related-url="/panel/salary">Salary</a><button data-employee-all>Show all</button></nav>
        <form method="post" data-employee-workspace="edit"><input name="_workspace_section" value="personal" type="hidden" data-dirty-ignore>
        ${keys.map(key => `<div class="form-section"><input name="${key}" value="${key}" required></div>`).join('')}<button type="submit">Profile save</button></form>
        <div data-employee-related-host data-token="test-csrf" data-loading="Loading" data-error="Failed; input kept" data-saved="Saved" data-confirm="Confirm?" data-reason="Reason"></div>
        <dialog id="unsaved-changes"><button data-unsaved-stay>Stay</button><button data-unsaved-discard>Discard</button><button data-unsaved-save>Save current</button></dialog>
        </body></html>`;
    const panelHtml = (key, saved = false) => `<div data-panel-content><div role="status" data-panel-status></div>
        <form method="post" action="/save/${key}" data-related-save><input name="_token" value="test-csrf" type="hidden"><input name="employee_id" value="12" type="hidden">
        <div role="alert" data-related-errors hidden></div><label for="${key}-reason">Reason</label><input id="${key}-reason" name="reason"><div data-field-error="reason"></div>
        ${key === 'leaves' ? '<input name="start_date" type="date"><input name="end_date" type="date"><select name="total_days_override"><option value="0">Auto</option><option value="1">Override</option></select><input name="total_days" type="number" step="any"><input name="attachment" type="file">' : ''}
        <button type="submit">Save here</button></form><button type="button" data-panel-load="/panel/${key}?record=1">Edit here</button>
        <button type="button" data-related-action="/action/${key}" data-action="approve">Approve</button>${saved ? '<p data-saved-record>Saved row</p>' : ''}</div>`;
    let failure = false;
    let networkFailure = false;
    let getFailure = false;
    const saved = {};
    const requests = [];
    await page.route('http://employee-fixture.test/**', async route => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.pathname.startsWith('/panel/')) {
            if (getFailure) return route.abort();
            const key = url.pathname.split('/').pop();
            return route.fulfill({ json: { html: panelHtml(key, saved[key]) } });
        }
        if (url.pathname.startsWith('/save/')) {
            requests.push({ path: url.pathname, body: request.postData() });
            if (networkFailure) return route.abort();
            if (failure) return route.fulfill({ status: 422, json: { errors: { reason: ['Review this reason.'] } } });
            saved[url.pathname.split('/').pop()] = true;
            return route.fulfill({ json: { message: 'Saved' } });
        }
        if (url.pathname.startsWith('/action/')) return route.fulfill({ json: { message: 'Approved' } });
        return route.fulfill({ contentType: 'text/html', body: fixture });
    });
    const blocked = () => page.evaluate(() => !window.dispatchEvent(new Event('beforeunload', { cancelable: true })));
    try {
        await page.goto('http://employee-fixture.test/workspace');
        await page.addScriptTag({ content: read('unsaved-changes.js') });
        await page.addScriptTag({ content: source });
        await page.locator('[name=personal]').fill('Unsaved profile');
        await page.locator('[data-employee-related=leaves]').click();
        const leave = page.locator('[data-related-panel=leaves]');
        await leave.locator('[name=reason]').fill('Leave draft');
        await leave.locator('[name=start_date]').fill('2026-09-01');
        await leave.locator('[name=end_date]').fill('2026-09-03');
        await leave.locator('[name=attachment]').setInputFiles({ name: 'proof.pdf', mimeType: 'application/pdf', buffer: Buffer.from('proof') });
        check(await leave.locator('[name=total_days]').inputValue() === '3', 'inclusive leave days calculate in panel');
        await page.locator('[data-employee-related=salary]').click();
        const salary = page.locator('[data-related-panel=salary]');
        await salary.locator('[name=reason]').fill('Salary draft');
        await salary.locator('button[type=submit]').click();
        await salary.locator('[data-saved-record]').waitFor();
        check(!await page.locator('#unsaved-changes').isVisible(), 'AJAX save does not trigger navigation guard for other drafts');
        check(await page.locator('[name=personal]').inputValue() === 'Unsaved profile', 'saving salary preserves profile draft');
        await page.locator('[data-employee-related=leaves]').click();
        check(await leave.locator('[name=reason]').inputValue() === 'Leave draft', 'saving another panel preserves leave draft');
        check(await leave.locator('[name=attachment]').evaluate(input => input.files[0].name) === 'proof.pdf', 'selected file survives tab switches and another save');
        check(await blocked(), 'remaining drafts are still guarded');
        failure = true;
        await leave.locator('button[type=submit]').click();
        await leave.locator('[data-related-errors]').waitFor();
        check(await leave.locator('[data-field-error=reason]').textContent() === 'Review this reason.', '422 errors appear on their fields');
        check(await leave.locator('[name=reason]').inputValue() === 'Leave draft', '422 retains entered values');
        check(await leave.locator('[name=attachment]').evaluate(input => input.files.length) === 1, '422 retains attachment');
        failure = false; networkFailure = true;
        await leave.locator('button[type=submit]').click();
        await page.waitForFunction(() => document.querySelector('[data-related-panel=leaves] [data-related-errors]').textContent.includes('Failed'));
        check(await leave.locator('[name=reason]').inputValue() === 'Leave draft', 'network failure retains input');
        networkFailure = false;
        await leave.locator('[data-panel-load]').click();
        check(await page.locator('#unsaved-changes').isVisible(), 'replacing edited form is guarded');
        await page.locator('[data-unsaved-stay]').click();
        check(await leave.locator('[name=reason]').inputValue() === 'Leave draft', 'Stay cancels form replacement');
        await leave.locator('button[type=submit]').click();
        await leave.locator('[data-saved-record]').waitFor();
        check(requests.at(-1).body.includes('proof.pdf') && requests.at(-1).body.includes('test-csrf'), 'multipart save sends attachment and CSRF');
        check(await blocked(), 'saving last child does not clear unsaved profile');
        await page.locator('[data-employee-section=personal]').click();
        await page.locator('[name=personal]').fill('personal');
        check(!await blocked(), 'all restored or saved sections become clean');
        check(page.url().includes('/workspace'), 'all related saves stayed on employee workspace URL');
        check(await page.locator('form form').count() === 0, 'related forms are not nested');
        check(await page.evaluate(() => { const ids = [...document.querySelectorAll('[id]')].map(node => node.id); return ids.length === new Set(ids).size; }), 'panel control IDs are unique');
        await page.locator('[data-employee-related=salary]').click();
        await salary.locator('[name=reason]').fill('Saved but refresh fails');
        getFailure = true;
        const previousPosts = requests.length;
        await salary.locator('button[type=submit]').click();
        await page.waitForFunction(() => document.querySelector('[data-related-panel=salary] [data-panel-status]').textContent.includes('Failed'));
        check(!await salary.locator('form').isVisible() && requests.length === previousPosts + 1, 'successful POST with failed refresh cannot accidentally submit twice');
        check(errors.length === 0, 'no script errors: ' + errors.join('; '));
        console.log(JSON.stringify({ result: 'passed', checks }));
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
