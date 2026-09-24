// Actual application scripts, mocked local HTTP; no live application writes.
const { chromium } = require(process.argv[2] || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const read = file => fs.readFileSync(path.join(__dirname, '../../', file), 'utf8');
const workspace = read('resources/js/employee-related-panels.js').replace('export function', 'function') + '\n'
    + read('resources/js/employee-workspace.js').replace(/^import .*;\r?\n/m, '');
const quickCreate = read('resources/views/components/admin/quick-create.blade.php').match(/<script>([\s\S]*?)<\/script>/)[1];
const dependentSelect = read('resources/views/components/admin/dependent-select.blade.php').match(/<script>([\s\S]*?)<\/script>/)[1];
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PREVIEW_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    let checks = 0;
    const check = (condition, name) => { assert.ok(condition, name); checks++; console.log('PASS ' + name); };
    const core = ['personal', 'employment', 'payroll', 'documents', 'access'];
    const panels = ['shifts', 'attendance', 'leaves', 'eosb', 'account'];
    const fixture = `<!doctype html><html><head><style>.quick-create-modal{display:none}.quick-create-modal.open{display:block}</style></head><body>
        <nav class="employee-workspace-nav" hidden>${core.map(key => `<a href="#${key}" data-employee-section="${key}">${key}</a>`).join('')}
        ${panels.map(key => `<a href="#${key}" data-employee-related="${key}" data-related-url="/panel/${key}">${key}</a>`).join('')}
        <button data-employee-all>Show all</button></nav>
        <form method="post" data-employee-workspace="edit"><input name="_workspace_section" value="personal" type="hidden" data-dirty-ignore>
        ${core.map(key => `<div class="form-section"><input name="${key}" value="${key}"></div>`).join('')}<button type="submit" data-save-default>Save & stay</button></form>
        <div data-employee-related-host data-close-url="/employees" data-token="csrf" data-loading="Loading" data-error="Failed" data-saved="Saved" data-confirm="Confirm?" data-reason="Reason"></div>
        ${['shift','leave-type'].map(key => `<div class="modal-overlay quick-create-modal" id="employee-master-${key}" data-target-selector="[data-employee-master=${key}]" data-url="/master/${key}">
          <span class="qc-mode-label">New</span><form method="post" class="quick-create-form" novalidate><input name="_token" value="csrf" type="hidden"><div class="qc-error" hidden></div><input name="name"><button type="button" class="js-qc-close">Cancel</button><button type="submit">Save & select</button></form></div>`).join('')}
        <dialog id="unsaved-changes"><button data-unsaved-stay>Stay</button><button data-unsaved-discard>Discard</button><button data-unsaved-save>Save current</button></dialog>
        </body></html>`;
    const catalog = { shift: [], 'leave-type': [] };
    const records = {};
    const posts = [];
    let masterFailure = false, masterNetworkFailure = false, saveFailure = false;
    const panelHtml = (key, editing) => {
        const master = ['shifts','attendance'].includes(key) ? 'shift' : key === 'leaves' ? 'leave-type' : null;
        const options = master ? catalog[master].map(row => `<option value="${row.id}">${row.label}</option>`).join('') : '';
        return `<div data-panel-content><div data-panel-status></div><form method="post" action="/save/${key}" data-related-save>
            <input name="_token" value="csrf" type="hidden">${editing ? '<input name="record_id" value="1" type="hidden">' : ''}<div data-related-errors hidden></div>
            ${master ? `<button type="button" data-quick-create="employee-master-${master}" data-quick-target="${key}-master">+ New</button>
            <select id="${key}-master" name="master_id" data-employee-master="${master}"><option value="">Select</option>${key === 'attendance' ? '<option value="10" selected>Existing Shift</option>' : ''}${options}</select>
            <small data-master-empty-for="${key}-master">No options yet</small>` : ''}
            <input name="reason" value="${editing ? records[key] || '' : ''}"><div data-field-error="reason"></div><input name="effective_from" type="date" value="2026-09-24">
            <button type="submit" name="_save_action" value="stay" data-save-default>Save & stay</button>
            <button type="submit" name="_save_action" value="next">Save & next</button>
            <button type="submit" name="_save_action" value="close">Save & close</button></form></div>`;
    };
    await page.route('http://workspace-followup.test/**', async route => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.pathname.startsWith('/panel/')) return route.fulfill({ json: { html: panelHtml(url.pathname.split('/').pop(), url.searchParams.has('record')) } });
        if (url.pathname.startsWith('/master/')) {
            if (masterNetworkFailure) return route.abort();
            if (masterFailure) return route.fulfill({ status: 422, json: { message: 'Duplicate master', errors: { name: ['Choose another name'] } } });
            const key = url.pathname.split('/').pop();
            if (key === 'site') return route.fulfill({ status: 201, json: { id: 32, label: 'New Site', parent: 20 } });
            const row = { id: 11, label: key === 'shift' ? 'Night Shift' : 'Study Leave' };
            catalog[key].push(row);
            return route.fulfill({ status: 201, json: row });
        }
        if (url.pathname.startsWith('/save/')) {
            const key = url.pathname.split('/').pop();
            posts.push(request.postData());
            if (saveFailure) return route.fulfill({ status: 422, json: { errors: { reason: ['Fix reason'] } } });
            records[key] = 'Saved reason';
            return route.fulfill({ json: { message: 'Saved', panel_url: `/panel/${key}?record=1` } });
        }
        return route.fulfill({ contentType: 'text/html', body: url.pathname === '/employees' ? '<h1>Employees</h1>' : fixture });
    });
    try {
        await page.goto('http://workspace-followup.test/workspace');
        await page.addScriptTag({ content: read('resources/js/unsaved-changes.js') });
        await page.addScriptTag({ content: quickCreate });
        await page.addScriptTag({ content: workspace });
        await page.locator('[name=personal]').fill('Profile draft');
        await page.locator('[data-employee-related=attendance]').click();
        const attendance = page.locator('[data-related-panel=attendance]');
        await attendance.locator('[name=reason]').fill('Attendance draft');
        await page.locator('[data-employee-related=shifts]').click();
        const shifts = page.locator('[data-related-panel=shifts]');
        await shifts.locator('[name=effective_from]').fill('2026-10-01');
        await shifts.locator('[data-quick-create]').click();
        const modal = page.locator('#employee-master-shift');
        check(await modal.isVisible(), 'master modal opens from dynamically loaded section');
        await modal.locator('[name=name]').fill('Draft Shift');
        masterFailure = true;
        await modal.locator('[type=submit]').click();
        await modal.locator('.qc-error').waitFor();
        check(await modal.locator('[name=name]').inputValue() === 'Draft Shift', 'invalid master preserves input');
        check(!await page.locator('#unsaved-changes').isVisible(), 'AJAX master save does not navigate or prompt for unrelated drafts');
        await page.keyboard.press('Escape');
        check(await page.locator('#unsaved-changes').isVisible(), 'Escape guards an edited master dialog');
        await page.locator('[data-unsaved-stay]').click();
        check(await modal.isVisible(), 'Stay keeps the master dialog open');
        masterFailure = false; masterNetworkFailure = true;
        await modal.locator('[type=submit]').click();
        await page.waitForFunction(() => document.querySelector('#employee-master-shift .qc-error').textContent.includes('Network'));
        check(await modal.locator('[name=name]').inputValue() === 'Draft Shift', 'network failure preserves master input');
        masterNetworkFailure = false;
        await modal.locator('[type=submit]').click();
        await modal.waitFor({ state: 'hidden' });
        check(await shifts.locator('[data-employee-master]').inputValue() === '11', 'new master is selected in initiating field');
        check(await attendance.locator('[data-employee-master]').inputValue() === '10', 'other cached selector keeps its previous selection');
        check(await attendance.locator('option[value="11"]').count() === 1, 'other cached selector gains new master option');
        check(await shifts.locator('[data-master-empty-for]').count() === 0, 'empty-master hint is removed after creation');
        check(await shifts.locator('[name=effective_from]').inputValue() === '2026-10-01', 'master creation preserves assignment dates');
        check(await page.locator('[name=personal]').inputValue() === 'Profile draft', 'master creation preserves profile draft');
        await shifts.locator('button[value=stay]').click();
        await shifts.locator('[name=record_id]').waitFor({ state: 'attached' });
        check(await shifts.locator('[name=reason]').inputValue() === 'Saved reason', 'Save and stay shows saved record, not another blank entry');
        check(posts.at(-1).includes('stay'), 'submitter save intent is sent to backend');
        await shifts.locator('[name=reason]').fill('Changed');
        saveFailure = true;
        await shifts.locator('button[value=next]').click();
        await shifts.locator('[data-related-errors]').waitFor();
        check(await shifts.isVisible() && page.url().endsWith('#shifts'), 'failed Save and next stays in current section');
        saveFailure = false;
        await shifts.locator('button[value=next]').click();
        await page.waitForURL('**/workspace#attendance');
        check(await attendance.locator('[name=reason]').inputValue() === 'Attendance draft', 'Save and next preserves next section draft');
        await page.locator('[data-employee-related=leaves]').click();
        const leaves = page.locator('[data-related-panel=leaves]');
        await leaves.locator('[data-quick-create]').click();
        const leaveModal = page.locator('#employee-master-leave-type');
        await leaveModal.locator('[name=name]').fill('Study Leave');
        await leaveModal.locator('[type=submit]').click();
        await leaveModal.waitFor({ state: 'hidden' });
        check(await leaves.locator('[data-employee-master]').inputValue() === '11', 'leave type is created and selected in place');
        // The same generic dialog code must synchronize a site's chosen project
        // before the real dependent-selector cache receives the new site option.
        await page.evaluate(() => {
            document.body.insertAdjacentHTML('beforeend', `<form method="post" id="parent-fixture"><select id="project-test" name="project_id"><option value="10">First</option><option value="20">Second</option></select><select id="site-test" name="site_id"><option value="">Select</option><option value="31" data-parent="10">Old site</option></select></form>
            <button id="site-trigger" data-quick-create="site-modal">+ New Site</button><div class="quick-create-modal" id="site-modal" data-target="site-test" data-parent-target="project-test" data-url="/master/site"><span class="qc-mode-label"></span><form method="post" class="quick-create-form"><input name="_token" value="csrf" type="hidden"><div class="qc-error" hidden></div><input name="name"><button type="submit">Save & select</button></form></div>`);
        });
        await page.addScriptTag({ content: dependentSelect });
        await page.evaluate(() => window.seeraDependentSelect('project-test', 'site-test', 'sites'));
        await page.locator('#site-trigger').click();
        await page.locator('#site-modal [name=name]').fill('New site');
        await page.locator('#site-modal [type=submit]').click();
        await page.locator('#site-modal').waitFor({ state: 'hidden' });
        check(await page.locator('#project-test').inputValue() === '20' && await page.locator('#site-test').inputValue() === '32', 'new site selection synchronizes its project');
        await page.locator('#project-test').selectOption('10');
        check(await page.locator('#site-test option[value="32"]').count() === 0, 'new site is filtered out for the wrong project');
        await page.locator('#project-test').selectOption('20');
        check(await page.locator('#site-test option[value="32"]').count() === 1, 'dependent cache retains new site when returning to its project');
        await page.locator('[data-employee-related=eosb]').click();
        const eosb = page.locator('[data-related-panel=eosb]');
        saveFailure = true;
        await eosb.locator('button[value=close]').click();
        await eosb.locator('[data-related-errors]').waitFor();
        check(page.url().includes('/workspace'), 'failed Save and close never exits');
        saveFailure = false;
        await eosb.locator('button[value=close]').click();
        await page.locator('#unsaved-changes').waitFor();
        check(page.url().includes('/workspace'), 'successful Save and close guards other unsaved forms');
        await page.locator('[data-unsaved-stay]').click();
        check(await page.locator('[name=personal]').inputValue() === 'Profile draft', 'Keep editing after close preserves profile draft');
        check(await page.locator('form form').count() === 0, 'master dialogs and child forms are not nested');
        await eosb.locator('button[value=close]').click();
        await page.locator('#unsaved-changes').waitFor();
        await page.locator('[data-unsaved-discard]').click();
        await page.waitForURL('**/employees');
        check(page.url().endsWith('/employees'), 'explicit discard allows save and close to Employees');
        check(errors.length === 0, 'no JavaScript errors: ' + errors.join('; '));
        console.log(JSON.stringify({ result: 'passed', checks }));
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
