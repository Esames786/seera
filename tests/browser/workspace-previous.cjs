// Real application navigation/dirty-guard scripts, mocked HTTP, no live writes.
const { chromium } = require(process.argv[2] || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const read = file => fs.readFileSync(path.join(__dirname, '../../', file), 'utf8');
const plain = file => read(file).replace(/^import .*;\r?\n/gm, '').replace('export function', 'function');
const previous = '<button type="button" data-workspace-previous hidden>Back / previous section</button>';
const dialog = '<dialog id="unsaved-changes"><button data-unsaved-stay>Stay</button><button data-unsaved-discard>Discard</button><button data-unsaved-save>Save current</button></dialog>';
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PREVIEW_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    let checks = 0;
    const check = (value, label) => { assert.ok(value, label); checks++; console.log('PASS ' + label); };
    try {
        for (const kind of ['employee', 'customer', 'supplier']) {
            const page = await browser.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            const employee = kind === 'employee';
            const core = employee ? ['personal', 'employment', 'payroll', 'documents', 'access'] : ['profile'];
            const keys = [...core, 'contacts', 'history'];
            const sectionAttr = employee ? 'employee-section' : 'workspace-section';
            const relatedAttr = employee ? 'employee-related' : 'workspace-related';
            const nav = `<nav ${employee ? 'class="employee-workspace-nav"' : 'data-workspace-nav'} hidden>${keys.map(key => `<a href="#${key}" data-${core.includes(key) ? sectionAttr : relatedAttr}="${key}" data-related-url="/panel/${key}">${key}</a>`).join('')}${employee ? '<button data-employee-all>Show all</button>' : ''}</nav>`;
            const form = `<form method="post" ${employee ? 'data-employee-workspace="edit"' : ''}><input type="hidden" name="_workspace_section" value="${core[0]}" data-dirty-ignore>${core.map(key => `<div class="form-section"><input name="${key}" value="${key}"></div>`).join('')}${previous}<button type="submit" data-save-default>Save</button></form>`;
            const host = `<div data-${employee ? 'employee' : 'workspace'}-related-host data-close-url="/list" data-loading="Loading" data-error="Failed" data-saved="Saved"></div>`;
            const fixture = `<!doctype html><html><body>${employee ? nav + form + host : `<div data-workspace>${nav}<div data-workspace-form>${form}</div>${host}</div>`}<a href="/list" id="leave">Leave workspace</a>${dialog}</body></html>`;
            let posts = 0;
            let fail = false;
            await page.route('http://previous.test/**', async route => {
                const url = new URL(route.request().url());
                if (url.pathname.startsWith('/panel/')) {
                    const key = url.pathname.split('/').pop();
                    const html = `<div data-panel-status></div><form method="post" action="/save/${key}" data-related-save><div data-related-errors hidden></div><input name="note"><input type="file" name="document">${previous}<button type="submit" value="next" data-save-default>Save & next</button></form>`;
                    return route.fulfill({ json: { html } });
                }
                if (url.pathname.startsWith('/save/')) {
                    posts++;
                    return route.fulfill(fail ? { status: 422, json: { errors: { note: ['Invalid note'] } } } : { json: { message: 'Saved' } });
                }
                return route.fulfill({ contentType: 'text/html', body: fixture });
            });
            await page.goto('http://previous.test/workspace');
            await page.addScriptTag({ content: read('resources/js/unsaved-changes.js') });
            await page.addScriptTag({ content: plain('resources/js/workspace-related-panels.js') + '\n' + (employee ? plain('resources/js/employee-related-panels.js') + '\n' + plain('resources/js/employee-workspace.js') : plain('resources/js/workspace.js')) });
            check(await page.locator('[data-workspace-previous]').first().isDisabled(), kind + ': first section Back disabled');
            await page.locator(`[name=${core[0]}]`).fill('Unchanged profile draft');
            if (employee) {
                await page.locator('[data-employee-section=employment]').click();
                await page.locator('[data-workspace-previous]:visible').click();
                check(new URL(page.url()).hash === '#personal', 'employee: Back from employment to personal');
            }
            await page.locator(`[data-${relatedAttr}=contacts]`).click();
            const contacts = page.locator('[data-related-panel=contacts]');
            await contacts.locator('[name=note]').fill('Unsaved related draft');
            await contacts.locator('[name=document]').setInputFiles({ name: 'example.txt', mimeType: 'text/plain', buffer: Buffer.from('fixture only') });
            await contacts.locator('[data-workspace-previous]').click();
            check(new URL(page.url()).hash === '#' + core.at(-1), kind + ': related Back chooses preceding visible tab');
            await page.locator(`[data-${relatedAttr}=contacts]`).click();
            check(await contacts.locator('[name=note]').inputValue() === 'Unsaved related draft', kind + ': unsaved related text retained');
            check(await contacts.locator('[name=document]').evaluate(el => el.files[0].name) === 'example.txt', kind + ': file selection retained');
            check(posts === 0, kind + ': Back sends no writes');
            fail = true;
            await contacts.locator('[type=submit]').click();
            await contacts.locator('[data-related-errors]').waitFor({ state: 'visible' });
            check(new URL(page.url()).hash === '#contacts', kind + ': validation failure stays on section');
            fail = false;
            await contacts.locator('[type=submit]').click();
            await page.waitForURL(url => url.hash === '#history');
            const history = page.locator('[data-related-panel=history]');
            await history.locator('[data-workspace-previous]').click();
            check(new URL(page.url()).hash === '#contacts', kind + ': Back still works after Save & next');
            // Profile is still dirty despite successful related save.
            await page.locator('#leave').click();
            check(await page.locator('#unsaved-changes').isVisible(), kind + ': leaving still asks about unsaved profile');
            await page.locator('[data-unsaved-stay]').click();
            check(await page.locator(`[name=${core[0]}]`).inputValue() === 'Unchanged profile draft', kind + ': profile draft retained');
            check(errors.length === 0, kind + ': no browser errors: ' + errors.join(', '));
            await page.close();
        }
        console.log(`${checks} checks passed`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
