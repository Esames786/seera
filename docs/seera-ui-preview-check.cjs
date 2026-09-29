// Standalone prototype checks only. Does not load the Laravel app or access a database.
// Usage: node docs/seera-ui-preview-check.cjs <path-to-installed-playwright>
const { chromium } = require(process.argv[2] || 'playwright');
const path = require('node:path');
const { pathToFileURL } = require('node:url');

(async () => {
    const browser = await chromium.launch({
        headless: true,
        executablePath: process.env.PREVIEW_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    });
    try {
        const page = await browser.newPage({ viewport: { width: 1440, height: 1100 } });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const check = async (condition, label) => {
            if (!await condition) throw new Error(label);
            console.log('PASS ' + label);
        };
        await page.goto(pathToFileURL(path.join(__dirname, 'seera-ui-transformation-preview-2026-09-22.html')).href);
        await page.locator('.preview').screenshot({ path: path.join(__dirname, 'seera-ui-transformation-preview-desktop.png') });
        await page.locator('#field-name').fill('Adeel Demo Edited');
        await page.locator('[data-tab="pay"]').click();
        await check(page.locator('#saveStatus').textContent().then(t => t.includes('Unsaved')), 'edits preserved across sections');
        await page.locator('[data-action="back"]').click();
        await check(page.locator('#unsaved').isVisible(), 'dirty navigation opens dialog');
        await page.locator('#stay').click();
        await check(page.locator('#employeeHeading').textContent().then(t => t === 'Adeel Demo Edited'), 'Stay keeps edits');
        await page.locator('[data-action="back"]').click();
        await page.locator('#discard').click();
        await check(page.locator('#canvas').textContent().then(t => !t.includes('Adeel Demo Edited')), 'Discard restores saved employee');
        await page.locator('[data-action="open"]').click();
        await page.locator('#field-name').fill('Adeel Updated');
        await page.locator('[data-save="next"]').click();
        await check(page.locator('#tab-employment').getAttribute('aria-selected').then(t => t === 'true'), 'Save and next advances one section');
        await page.locator('[data-save="close"]').click();
        await check(page.locator('#canvas').textContent().then(t => t.includes('Adeel Updated')), 'Save and close returns updated list');
        await page.locator('[data-action="open"]').click();
        await page.locator('#field-email').fill('not-valid');
        await page.locator('[data-action="back"]').click();
        await page.locator('#saveContinue').click();
        await check(page.locator('#field-email').inputValue().then(t => t === 'not-valid'), 'failed save retains input');
        await check(page.locator('#saveStatus').textContent().then(t => t.includes('Unsaved')), 'failed save stays dirty');
        await page.locator('#field-email').fill('adeel.updated@example.test');
        await page.locator('[data-action="back"]').click();
        await page.locator('#saveContinue').click();
        await check(page.locator('[data-action="open"]').isVisible(), 'Save and continue navigates only after success');
        await page.locator('[data-nav="users"]').click();
        await page.locator('#employee-search').fill('Adeel');
        await page.locator('[data-action="select-employee"]').click();
        await check(page.locator('#account-email').inputValue().then(t => t === 'adeel.updated@example.test'), 'employee selection prefills saved email');
        await page.locator('#account-role').selectOption('self');
        await page.locator('#account-consent').check();
        await page.locator('[data-action="create-account"]').click();
        await check(page.locator('#user-fields').textContent().then(t => t.includes('Demo account linked')), 'explicit demo user link');
        await page.locator('[data-nav="employee"]').click();
        await page.locator('#locale').selectOption('ar');
        await check(page.locator('#shell').getAttribute('dir').then(t => t === 'rtl'), 'Arabic RTL');
        await check(page.locator('#locale option').count().then(n => n === 2), 'English and Arabic only');
        await check(page.locator('#tab-profile').textContent().then(t => t === 'الملف الشخصي'), 'Arabic labels');
        await page.locator('#tab-profile').focus();
        await page.keyboard.press('ArrowLeft');
        await check(page.locator('#tab-employment').getAttribute('aria-selected').then(t => t === 'true'), 'RTL keyboard tab navigation');
        await page.locator('#tab-profile').click();
        await page.evaluate(() => document.querySelector('#toast').hidden = true);
        await page.locator('.preview').screenshot({ path: path.join(__dirname, 'seera-ui-transformation-preview-arabic.png') });
        await page.locator('#beforeButton').click();
        await check(page.locator('#canvas').textContent().then(t => t.includes('Employee Management')), 'Before illustration');
        await page.locator('#afterButton').click();
        await page.setViewportSize({ width: 390, height: 844 });
        await check(page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'mobile document has no horizontal overflow');
        await check(page.locator('#field-name').evaluate(el => el.getBoundingClientRect().right <= window.innerWidth), 'mobile inputs remain inside the viewport');
        await page.evaluate(() => document.querySelector('.preview').scrollIntoView({ block: 'start' }));
        await page.screenshot({ path: path.join(__dirname, 'seera-ui-transformation-preview-mobile.png') });
        await check(errors.length === 0, 'no browser script errors');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
