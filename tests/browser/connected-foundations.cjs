// Isolated browser fixtures use the REAL application scripts; no server or DB.
// node tests/browser/connected-foundations.cjs <installed-playwright-path>
const { chromium } = require(process.argv[2] || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const script = fs.readFileSync(path.join(__dirname, '../../resources/js/unsaved-changes.js'), 'utf8');
const employeeScript = fs.readFileSync(path.join(__dirname, '../../resources/js/employee-user-search.js'), 'utf8');
const workspaceScript = fs.readFileSync(path.join(__dirname, '../../resources/js/employee-workspace.js'), 'utf8');

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PREVIEW_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    const page = await browser.newPage();
    let checks = 0;
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const check = (condition, name) => { assert.ok(condition, name); checks++; console.log('PASS ' + name); };
    const fixture = `<!doctype html><html><body>
        <a id="leave" href="/elsewhere">Leave</a><a id="anchor" href="#section">Section</a><a id="newtab" href="/elsewhere" target="_blank">New tab</a>
        <form id="locale" method="post" data-no-dirty-guard><select name="locale"><option>en</option><option>ar</option></select><button>Change language</button></form>
        <section class="page"><form id="main" method="post"><input name="name" value="Original" required><input type="checkbox" name="check"><input type="file" name="attachment"><textarea name="notes"></textarea><button type="submit">Save</button></form></section>
        <form id="child" method="post"><input name="child"><button type="submit">Save child</button></form>
        <form id="filter" method="get"><input name="search"></form>
        <form id="logout" method="post"><button type="submit">Logout</button></form>
        <dialog id="unsaved-changes" data-validation-errors="0"><button data-unsaved-stay>Stay</button><button data-unsaved-discard>Discard</button><button data-unsaved-save>Save current</button></dialog>
        <script>document.querySelector('#child').addEventListener('submit', event => {event.preventDefault(); window.childSubmitted = true;});</script>
        </body></html>`;
    await page.route('http://seera-fixture.test/**', route => route.fulfill({ contentType: 'text/html', body: fixture }));
    const load = async () => { await page.goto('http://seera-fixture.test/'); await page.addScriptTag({ content: script }); };
    const unloadBlocked = () => page.evaluate(() => !window.dispatchEvent(new Event('beforeunload', { cancelable: true })));
    try {
        await load();
        check(!await unloadBlocked(), 'clean form does not warn');
        await page.locator('#filter input').fill('filter');
        check(!await unloadBlocked(), 'GET filters excluded');
        await page.locator('#locale select').selectOption('ar');
        check(!await unloadBlocked(), 'language action is not a dirty data form');
        await page.locator('#main [name=name]').fill('Changed');
        check(await unloadBlocked(), 'changed text warns before unload');
        await page.locator('#leave').click();
        check(await page.locator('#unsaved-changes').isVisible(), 'in-app navigation opens choices');
        await page.locator('[data-unsaved-stay]').click();
        check(await page.locator('#main [name=name]').inputValue() === 'Changed', 'Stay preserves input');
        await page.locator('#anchor').click();
        check(!await page.locator('#unsaved-changes').isVisible(), 'same-page anchor is not blocked');
        await page.locator('#main [name=name]').fill('Original');
        check(!await unloadBlocked(), 'reverting values becomes clean');
        await page.locator('#main [name=check]').check();
        check(await unloadBlocked(), 'checkbox changes protected');
        await page.locator('#main [name=check]').uncheck();
        await page.locator('#main [name=attachment]').setInputFiles({ name: 'demo.txt', mimeType: 'text/plain', buffer: Buffer.from('demo') });
        check(await unloadBlocked(), 'file selection protected');
        await page.locator('#main [name=attachment]').setInputFiles([]);
        await page.locator('#main textarea').fill('Unsaved note');
        await page.locator('#logout button').click();
        check(await page.locator('#unsaved-changes').isVisible(), 'logout guards other unsaved forms');
        await page.locator('[data-unsaved-stay]').click();
        await page.locator('#child input').fill('Child draft');
        await page.locator('#leave').click();
        check(!await page.locator('[data-unsaved-save]').isVisible(), 'multiple dirty forms are not silently bulk-saved');
        await page.locator('[data-unsaved-stay]').click();
        await page.locator('#main textarea').fill('');
        await page.locator('#leave').click();
        await page.locator('[data-unsaved-save]').click();
        check(await page.evaluate(() => window.childSubmitted === true), 'Save invokes existing form submit handler');
        check(await unloadBlocked(), 'AJAX submit alone does not clear dirty state');
        await page.evaluate(() => document.querySelector('#child').dispatchEvent(new CustomEvent('seera:form-saved', { bubbles: true })));
        check(!await unloadBlocked(), 'successful AJAX signal resets only saved form');
        await page.locator('#child input').fill('Changed again');
        await page.evaluate(() => document.querySelector('#child').dispatchEvent(new CustomEvent('seera:before-form-close', { bubbles: true, cancelable: true, detail: { close: () => { window.childClosed = true; } } })));
        check(await page.locator('#unsaved-changes').isVisible(), 'modal close protected');
        await page.locator('[data-unsaved-discard]').click();
        check(await page.evaluate(() => window.childClosed === true), 'discard closes requested child only');
        await page.locator('#main [name=name]').fill('New');
        await page.locator('#leave').click();
        await page.locator('[data-unsaved-discard]').click();
        await page.waitForURL('**/elsewhere');
        check(page.url().endsWith('/elsewhere'), 'discard navigates to original target');

        await load();
        await page.locator('#main [name=name]').fill('');
        await page.locator('#leave').click();
        await page.locator('[data-unsaved-save]').click();
        check(await page.evaluate(() => !document.querySelector('#main').checkValidity()), 'Save retains native required validation');
        check(page.url().endsWith('/'), 'invalid save does not navigate');
        // Clear dirty fixture without triggering an actual browser native dialog.
        await page.locator('#main [name=name]').fill('Original');
        await page.setContent(`<form method="post"><input data-employee-search="http://seera-fixture.test/lookup" data-confirm="Replace?" data-selected="Selected" data-empty="Empty" data-error="Error" data-cleared="Cleared"><input name="source_employee_id"><input name="name"><input name="employee_id"><input name="email"><select name="role_id"><option value="explicit">Explicit role</option></select><input name="password" value="unchanged"><div id="employee-search-results"></div><button type="button" data-clear-employee>Clear</button></form>`);
        await page.route('**/lookup?*', route => route.fulfill({ json: { data: [{ id: 12, employee_code: 'SP-12', name: 'Test Person', fields: { name: 'Test Person', employee_id: 'SP-12', email: 'test@example.test' } }] } }));
        await page.addScriptTag({ content: employeeScript });
        page.on('dialog', modal => modal.accept());
        await page.locator('[data-employee-search]').fill('Test');
        await page.locator('#employee-search-results button').click();
        check(await page.locator('[name=source_employee_id]').inputValue() === '12', 'lookup stores employee link id');
        check(await page.locator('[name=name]').inputValue() === 'Test Person', 'lookup fills identity');
        check(await page.locator('[name=role_id]').inputValue() === 'explicit' && await page.locator('[name=password]').inputValue() === 'unchanged', 'lookup does not overwrite role/password');
        await page.locator('[data-clear-employee]').click();
        check(await page.locator('[name=source_employee_id]').inputValue() === '', 'employee link can be cleared');
        await page.goto('http://seera-fixture.test/workspace');
        const sections = ['personal', 'employment', 'payroll', 'documents', 'access'];
        await page.setContent(`<nav class="employee-workspace-nav" hidden>${sections.map(section => `<a href="#${section}" data-employee-section="${section}">${section}</a>`).join('')}<button type="button" data-employee-all>Show all</button></nav>
            <form method="post" data-employee-workspace="edit"><input type="hidden" name="_workspace_section" value="personal" data-dirty-ignore>
            ${sections.map(section => `<div class="form-section"><input name="${section}" value="Saved ${section}" required></div>`).join('')}<button type="submit">Save</button></form>
            <dialog id="unsaved-changes" data-validation-errors="0"><button data-unsaved-stay>Stay</button><button data-unsaved-discard>Discard</button><button data-unsaved-save>Save current</button></dialog>`);
        await page.addScriptTag({ content: script });
        await page.addScriptTag({ content: workspaceScript });
        check(await page.locator('.form-section:visible').count() === 1, 'employee edit opens one section');
        await page.locator('[data-employee-section=documents]').click();
        check(await page.locator('#documents').isVisible(), 'employee documents tab opens in place');
        check(!await unloadBlocked(), 'section navigation alone does not mark data dirty');
        await page.locator('[data-employee-section=personal]').click();
        await page.locator('[name=personal]').fill('Unsaved personal');
        await page.locator('[data-employee-section=payroll]').click();
        check(await page.locator('[name=personal]').inputValue() === 'Unsaved personal', 'employee section switch preserves unsaved data');
        check(await unloadBlocked(), 'employee unsaved edits remain guarded across sections');
        await page.locator('[name=payroll]').fill('');
        await page.locator('[data-employee-section=access]').click();
        await page.locator('button[type=submit]').click();
        check(await page.locator('#payroll').isVisible(), 'validation reveals hidden invalid section');
        check(await page.locator('.form-section:visible').count() === 5, 'invalid form shows every section for correction');
        await page.locator('[data-employee-section=personal]').click();
        await page.locator('[data-employee-all]').click();
        check(await page.locator('.form-section:visible').count() === 5, 'Show all restores the original full form');
        check(errors.length === 0, 'no browser script errors: ' + errors.join(';'));
        console.log(JSON.stringify({ result: 'passed', checks }));
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
