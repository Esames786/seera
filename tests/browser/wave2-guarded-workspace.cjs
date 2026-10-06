// Local mocked HTTP only. Runs the production workspace/guard/search scripts.
const fs = require('fs');
const assert = require('assert');
const { chromium } = require(process.argv[2] || 'playwright');
const plain = path => fs.readFileSync(path, 'utf8').replace(/^import .*;\r?\n/gm, '').replace('export function', 'function');
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PREVIEW_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    let checks = 0;
    const check = (value, label) => { assert.ok(value, label); checks++; console.log('PASS ' + label); };
    try {
        for (const kind of ['user', 'site']) {
            const page = await browser.newPage();
            const errors = []; page.on('pageerror', error => errors.push(error.message));
            let posts = 0, fail = false, searches = 0;
            const fixture = `<div data-workspace-identity>Old summary</div><div data-workspace data-workspace-guard-tabs><nav data-workspace-nav><a href="#profile" data-workspace-section="profile">Profile</a><a href="#employee" data-workspace-related="employee" data-related-url="/panel/employee">Related</a></nav><div data-workspace-form><form method="post"><input name="name" value="Original"><button type="submit" data-save-default>Save</button></form></div><div data-workspace-related-host data-close-url="/list" data-loading="Loading" data-error="Failed" data-saved="Saved"></div></div><dialog id="unsaved-changes"><button data-unsaved-stay>Stay</button><button data-unsaved-discard>Leave without saving</button><button data-unsaved-save>Save current</button></dialog>`;
            await page.route('http://batcha.test/**', async route => {
                const url = new URL(route.request().url());
                if (url.pathname === '/search') {
                    check(url.searchParams.get('target_user') === '7', kind + ': employee search preserves authorized target parameter');
                    searches++;
                    return route.fulfill({ json: { data: [{ id: 9, employee_code: 'EMP-9', name: 'Worker', fields: { name: 'Worker' } }] } });
                }
                if (url.pathname === '/panel/employee') return route.fulfill({ json: { html: `<div data-panel-status></div><form method="post" action="/save" data-related-save><div data-related-errors hidden></div><input name="name" value=""><input name="source_employee_id" type="hidden"><input id="employee-search" data-employee-search="/search?target_user=7" data-confirm="Select employee?" data-selected="Selected"><div id="employee-search-results"></div><button type="submit" name="_save_action" value="stay" data-save-default>Save</button><button type="submit" name="_save_action" value="close">Save close</button><button type="button" data-workspace-previous>Previous</button></form>` } });
                if (url.pathname === '/save') { posts++; return route.fulfill(fail ? { status: 422, json: { errors: { name: ['Invalid name'] } } } : { json: { panel_url: '/panel/employee', identity_html: '<h2>Updated summary</h2>' } }); }
                return route.fulfill({ contentType: 'text/html', body: fixture });
            });
            await page.goto('http://batcha.test/workspace');
            await page.addScriptTag({ content: plain('resources/js/unsaved-changes.js') });
            await page.addScriptTag({ content: plain('resources/js/workspace-related-panels.js') + '\n' + plain('resources/js/workspace.js') });
            await page.addScriptTag({ content: plain('resources/js/employee-user-search.js') });
            await page.locator('[data-workspace-form] input').fill('Unsaved');
            await page.locator('[data-workspace-related]').click();
            check(await page.locator('#unsaved-changes').isVisible(), kind + ': dirty profile tab transition prompts');
            await page.locator('[data-unsaved-stay]').click();
            check(await page.locator('[data-workspace-form]').isVisible(), kind + ': Stay retains current panel');
            check(posts === 0, kind + ': tab click never saves implicitly');
            await page.locator('[data-workspace-related]').click();
            await page.locator('[data-unsaved-discard]').click();
            await page.locator('#employee-search').fill('EMP');
            page.on('dialog', dialog => dialog.accept());
            await page.locator('#employee-search-results button').click();
            check(await page.locator('[data-related-save] [name=name]').inputValue() === 'Worker', kind + ': lazy employee search initializes and fills preview');
            await page.locator('[data-workspace-previous]').click();
            check(await page.locator('#unsaved-changes').isVisible(), kind + ': dirty related Previous prompts');
            await page.locator('[data-unsaved-stay]').click();
            fail = true;
            await page.locator('[data-related-save] [data-save-default]').click();
            await page.locator('[data-related-errors]').waitFor({ state: 'visible' });
            check(await page.locator('[data-related-save] [name=name]').inputValue() === 'Worker', kind + ': failed save retains input');
            fail = false;
            await page.locator('[data-related-save] [data-save-default]').click();
            await page.waitForFunction(() => document.querySelector('[data-related-save] [name=name]')?.value === '');
            check(new URL(page.url()).hash === '#employee', kind + ': successful Save stays');
            check(await page.locator('[data-workspace-identity]').innerText() === 'Updated summary', kind + ': successful save refreshes identity header');
            await page.locator('#employee-search').fill('EMP');
            await page.locator('#employee-search-results button').waitFor();
            check(searches === 2, kind + ': search reinitializes after panel refresh');
            await page.locator('[data-related-save] [value=close]').click();
            await page.waitForURL('http://batcha.test/list');
            check(posts === 3, kind + ': Save close writes once and leaves');
            check(errors.length === 0, kind + ': no browser errors: ' + errors.join(', '));
            await page.close();
        }
        console.log(`${checks} checks passed`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
