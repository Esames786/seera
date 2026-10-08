// Local mock HTTP, real shared scripts. Not live ERP/business acceptance.
const fs = require('fs');
const assert = require('assert');
const { chromium } = require(process.argv[2] || 'playwright');
const script = path => fs.readFileSync(path, 'utf8').replace(/^import .*;\r?\n/gm, '').replace('export function', 'function');
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PREVIEW_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    let checks = 0;
    const check = (value, name) => { assert.ok(value, name); checks++; console.log('PASS ' + name); };
    try {
        for (const kind of ['item', 'warehouse']) {
            const page = await browser.newPage();
            const errors = []; page.on('pageerror', error => errors.push(error.message));
            let writes = 0, stockReads = 0;
            const panel = (name, number) => `<div data-panel-status></div><h2>${name} page ${number}</h2><button type="button" data-workspace-previous hidden>Back</button><table><tr><td>Read only stock</td></tr></table>${name === 'stock' && number === 1 ? '<button data-panel-load="/stock?page=2">Next page</button>' : ''}`;
            await page.route('http://inventory.test/**', async route => {
                const url = new URL(route.request().url());
                if (route.request().method() !== 'GET') writes++;
                if (url.pathname === '/stock') { stockReads++; return route.fulfill({ json: { html: panel('stock', Number(url.searchParams.get('page') || 1)) } }); }
                if (url.pathname === '/ledger') return route.fulfill({ json: { html: panel('ledger', 1) } });
                return route.fulfill({ contentType: 'text/html', body: `<h1>${kind} identity</h1><div data-workspace data-workspace-guard-tabs><nav data-workspace-nav><a href="#profile" data-workspace-section="profile">Overview</a><a href="/stock#stock" data-workspace-related="stock" data-related-url="/stock">Stock</a><a href="/ledger#ledger" data-workspace-related="ledger" data-related-url="/ledger">Ledger</a></nav><div data-workspace-form><form method="post"><input name="name" value="Original"><button type="submit" data-save-default>Save</button></form></div><div data-workspace-related-host data-close-url="/list" data-loading="Loading" data-error="Failed" data-saved="Saved"></div></div><dialog id="unsaved-changes"><button data-unsaved-stay>Stay</button><button data-unsaved-discard>Discard</button><button data-unsaved-save>Save</button></dialog>` });
            });
            await page.goto('http://inventory.test/workspace');
            await page.addScriptTag({ content: script('resources/js/unsaved-changes.js') });
            await page.addScriptTag({ content: script('resources/js/workspace-related-panels.js') + '\n' + script('resources/js/workspace.js') });
            check(stockReads === 0, kind + ': no eager child loads');
            await page.locator('input[name=name]').fill('Dirty');
            await page.locator('[data-workspace-related=stock]').click();
            check(await page.locator('#unsaved-changes').isVisible(), kind + ': dirty profile tab guard');
            await page.locator('[data-unsaved-stay]').click();
            check(await page.locator('input[name=name]').inputValue() === 'Dirty', kind + ': Stay preserves input');
            await page.locator('input[name=name]').fill('Original');
            await page.locator('[data-workspace-related=stock]').click();
            await page.getByRole('heading', { name: 'stock page 1' }).waitFor();
            check(page.url().endsWith('#stock'), kind + ': bookmarkable active tab');
            check(await page.locator('[data-related-panel=stock] form').count() === 0, kind + ': related stock is read-only');
            await page.getByRole('button', { name: 'Next page' }).click();
            await page.getByRole('heading', { name: 'stock page 2' }).waitFor();
            check(stockReads === 2, kind + ': pagination loads only requested page');
            await page.locator('[data-workspace-related=ledger]').click();
            await page.getByRole('heading', { name: 'ledger page 1' }).waitFor();
            await page.locator('[data-related-panel=ledger] [data-workspace-previous]').click();
            check(await page.getByRole('heading', { name: 'stock page 2' }).isVisible(), kind + ': Back preserves previous panel page');
            await page.locator('[data-related-panel=stock] [data-workspace-previous]').click();
            check(await page.locator('[data-workspace-form]').isVisible(), kind + ': Back returns to overview');
            check(writes === 0, kind + ': navigation sends no writes');
            check(errors.length === 0, kind + ': no browser errors ' + errors.join(', '));
            await page.close();
        }
        console.log(checks + ' checks passed');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
