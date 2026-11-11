// Real browser captures on the attested synthetic server. Never submits business forms.
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { chromium } = require(process.env.SEERA_PLAYWRIGHT || 'D:/laragon2/www/property_handle/node_modules/playwright');

(async () => {
    const root = fs.realpathSync(process.argv[2] || '');
    if (!/^seera-guide-[a-f0-9]{32}$/.test(path.basename(root))) throw new Error('Synthetic fixture required');
    const fixture = JSON.parse(fs.readFileSync(path.join(root, 'fixture.private.json')));
    const env = JSON.parse(fs.readFileSync(path.join(root, 'process-env.private.json')));
    const closure = JSON.parse(fs.readFileSync(path.join(root, 'closure.private.json')));
    const base = env.APP_URL;
    const proof = await (await fetch(base + '/__guide_fixture_attestation')).json();
    if (!fixture.synthetic || !proof.synthetic || proof.id !== path.basename(root) || proof.business_writes || proof.production_zatca) throw new Error('Unsafe server');
    const out = path.resolve('docs/user-guide/november-screenshots');
    fs.mkdirSync(out, { recursive: true });
    const employee = `/admin/hr/employees/${fixture.ids.employee}`;
    const user = `/admin/users/${fixture.ids.user}`;
    const scenes = [
        { slug: 'employee-photo', point: 'R10-03', title: 'Employee profile photo', route: employee + '/edit#personal', live: '/admin/hr/employees', steps: 'Open the employee → Personal.', change: 'Choose, preview, save, replace or remove an Employee photo.', selector: '[data-profile-photo]', ancestor: '.form-section', photo: true },
        { slug: 'user-photo', point: 'R10-04', title: 'User account photo', route: '/admin/users/create', live: '/admin/users/create', steps: 'Profile photo on Add User; also available on Edit / Manage.', change: 'The photo chooser now works and shows a preview. Save the user to keep the selected photo.', selector: '[data-profile-photo]', ancestor: '.card', photo: true },
        { slug: 'annual-leave', point: 'R10-02', title: 'Annual leave balance and year', route: employee + '?leave_year=2026#leaves', live: '/admin/hr/employees', steps: 'Open the employee → View → leave balance/year.', change: 'The selected year and Annual Leave balance are explicit. Only approved Annual Leave in that year reduces the annual balance.', selector: '#leaves', ancestor: '.table-card' },
        { slug: 'iban', point: 'R10-01', title: 'Employee bank details', route: employee + '/edit#payroll', live: '/admin/hr/employees', steps: 'Open the employee → Salary & payment → IBAN.', change: 'Copied spaces and Arabic/Persian digits are normalized; format/check-digit errors are explained.', status: 'Needs private confirmation of the exact disputed bank value. This screenshot shows the form, not bank ownership verification.', selector: '#iban', ancestor: '.form-section', clearIban: true },
        { slug: 'personal-permissions', point: 'R10-05', title: 'Individual access for one user', route: user + '/edit', live: '/admin/users', steps: 'Choose the user → Edit / Manage → Individual permissions.', change: 'Super Admin can set Inherit / Allow / Deny for one person with a reason. A personal Deny takes priority over role grants.', selector: '#individual-permissions', permissions: true },
        { slug: 'my-leave', point: 'R10-08', title: 'Request your own leave', route: '/admin/my-leave', live: '/admin/my-leave', steps: 'Topbar → My Leave. A unique eligible Employee link is required.', change: 'The linked employee sees their own requests and balance. New requests remain Pending for HR approval.', selector: '.page' },
        { slug: 'supplier-code', point: 'R10-09', title: 'Automatic Supplier code', route: '/admin/master/suppliers/create', live: '/admin/master/suppliers/create', steps: 'Add Supplier → Code. Leave the manual-code checkbox unchecked.', change: 'The next expected code is visible. The server allocates the final code on save; Save & New refreshes the preview.', selector: '.form-section' },
        { slug: 'customer-code', point: 'R10-09', title: 'Automatic Customer code', route: '/admin/master/customers/create', live: '/admin/master/customers/create', steps: 'Add Customer → Code.', change: 'The same preview/manual-code choice is available for Customers. Existing saved codes are preserved.', selector: '.form-section' },
        { slug: 'supplier-documents', point: 'R10-10', title: 'Supplier papers on the entry screen', route: '/admin/master/suppliers/create', live: '/admin/master/suppliers/create', steps: 'Add Supplier or Edit / Manage → Documents.', change: 'Choose document type, reference, dates and file together with the supplier. Save the parent form to store it.', selector: '[data-document-inputs]' },
        { slug: 'customer-documents', point: 'R10-11', title: 'Customer papers on the entry screen', route: '/admin/master/customers/create', live: '/admin/master/customers/create', steps: 'Add Customer or Edit / Manage → Documents.', change: 'Customer documents stay with their Customer. Saved files have View/Download; Customer View stays read-only.', selector: '[data-document-inputs]' },
        { slug: 'equipment-documents', point: 'R10-12', title: 'Equipment documents during initial entry', route: '/admin/equipment/create', live: '/admin/equipment/create', steps: 'Add Equipment → Documents.', change: 'Files can be selected before the first save, without moving to a separate View screen.', selector: '[data-document-inputs]' },
        { slug: 'equipment-meter', point: 'R10-13', title: 'Equipment initial meter', route: '/admin/equipment/create', live: '/admin/equipment/create', steps: 'Add Equipment → Initial meter reading.', change: 'The server saves a blank initial meter as zero; entered readings are kept and negative readings are refused. This image identifies the affected field; the save behavior is covered by regression tests.', selector: '#current_meter', ancestor: '.form-section' },
        { slug: 'users-review', point: 'R10-06 / latest screenshot', title: 'Old-user review and readable Deactivate action', route: '/admin/users', live: '/admin/users', steps: 'Users → review exact account IDs and Employee links with the owner.', change: 'The raw ui.deactivate text is replaced by a readable action. The owner inventory distinguishes displayed codes from actual Employee links.', status: 'Actual cleanup and any identity correction are still pending the approved list. The fictional accounts pictured were not deleted or deactivated.', selector: '.page' },
        { slug: 'master-review', point: 'R10-07', title: 'Master records to review before cleanup', route: '/admin/accounting/cost-centers', live: '/admin/accounting/cost-centers', steps: 'Review Cost Centers, Projects and Sites with the owner.', change: 'The private inventory identifies retained masters and missing linked references. Newly entered real records are protected from automatic cleanup.', status: 'Pending item-by-item Keep / Review decisions. This screenshot is a training list, not proof of completed cleanup.', selector: '.page' },
        { slug: 'overdue-reminder', point: 'NR-06', title: 'Customer overdue reminder', route: '/admin/notifications', live: '/admin/notifications', steps: 'Notifications → Open invoice.', change: 'After schedule activation, eligible Finance/Super Admin users receive one in-app reminder per overdue invoice/day from one day overdue. Settled or inaccessible invoices are excluded.', selector: '.page' },
        { slug: 'supplier-recovery', point: 'NR-29', title: 'Supplier recoverable payment and settlement', route: `/admin/master/suppliers/${closure.supplier}/edit#advances`, live: '/admin/master/suppliers', steps: 'Choose supplier → Edit / Manage → Supplier advances.', change: 'Record an agreed recoverable payment, explicitly apply it to an eligible bill, record money returned, or reverse a correction. Finance must configure its dedicated advance asset first.', selector: '[data-panel-content]', recovery: true },
    ];
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
    const captures = [], errors = [];
    try {
        const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, serviceWorkers: 'block' });
        await context.route('**/*', route => {
            const req = route.request(), url = new URL(req.url());
            if (url.protocol === 'blob:') return route.continue();
            if (url.origin !== base) return route.abort();
            if (!['GET', 'HEAD'].includes(req.method()) && !(req.method() === 'POST' && ['/login', '/admin/locale'].includes(url.pathname))) throw new Error('Business POST refused');
            return route.continue();
        });
        const page = await context.newPage(); page.setDefaultTimeout(15000); page.on('pageerror', e => errors.push(String(e))); page.on('dialog', d => d.accept());
        await page.goto(base + '/login'); await page.fill('[name=email]', fixture.user); await page.fill('[name=password]', fixture.password);
        await Promise.all([page.waitForURL(url => url.pathname !== '/login'), page.locator('button[type=submit]').click()]);
        for (const locale of ['en', 'ar']) {
            await page.goto(base + '/admin/dashboard');
            await Promise.all([page.waitForNavigation(), page.locator('.locale-switch').evaluate((form, locale) => { form.querySelector('select').value = locale; form.requestSubmit(); }, locale)]);
            for (const scene of scenes.filter(s => locale === 'en' || ['employee-photo', 'personal-permissions', 'supplier-documents', 'overdue-reminder', 'supplier-recovery'].includes(s.slug))) {
                const response = await page.goto(base + scene.route);
                if (response.status() !== 200) throw new Error(scene.slug + ' HTTP ' + response.status());
                if (scene.permissions) await page.locator('#individual-permissions').evaluate(el => { el.open = true; el.querySelector('details').open = true; });
                if (scene.recovery) await page.locator('[name=recoverable_confirmed]').evaluate(el => { el.closest('details').open = true; });
                if (scene.clearIban) await page.fill('#iban', '');
                if (scene.photo) await page.locator('[name=profile_photo]').setInputFiles({ name: 'training-profile.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAAE0lEQVR4nGNkYPjPAANMcBZeDgAx0wEH1s7nlgAAAABJRU5ErkJggg==', 'base64') });
                let target = page.locator(scene.selector).first();
                if (scene.ancestor) target = target.locator(`xpath=ancestor::div[contains(concat(' ',normalize-space(@class),' '),' ${scene.ancestor.slice(1)} ')][1]`);
                await target.waitFor({ state: 'visible' });
                const file = `${locale}-${scene.slug}.jpg`;
                await target.screenshot({ path: path.join(out, file), type: 'jpeg', quality: 82, animations: 'disabled' });
                const bytes = fs.readFileSync(path.join(out, file));
                captures.push({ ...scene, locale, file, captured_url: page.url(), live_url: 'https://seera.tech-brit.co.uk' + scene.live, bytes: bytes.length, sha256: crypto.createHash('sha256').update(bytes).digest('hex') });
                console.log('Captured ' + file);
            }
        }
        if (errors.length) throw new Error(errors.join('\n'));
    } finally { await browser.close(); }
    fs.writeFileSync(path.join(out, 'manifest.json'), JSON.stringify({ captured_date: '2026-11-11', synthetic: true, business_forms_submitted: false, production_access: false, source_commit: proof.source, captures, errors }, null, 2));
    const md = ['<!-- NOVEMBER_SCREENSHOTS_START -->', '## Screenshots: where each change is', '',
        'These are real application screenshots captured on **11 November 2026** using **fictional training data**. They show the prepared improvements. They do not mean these changes are already installed on your live website. Live screen links below require your normal authorized login; select your own record where indicated. Training record IDs are not live-record links.', '',
        '| Point | Screenshot / screen | Open screen |', '| --- | --- | --- |',
        ...scenes.map(s => `| ${s.point} | [${s.title}](#screenshot-${s.slug}) | [Open](${ 'https://seera.tech-brit.co.uk' + s.live }) |`), ''];
    for (const scene of scenes) {
        md.push(`<a id="screenshot-${scene.slug}"></a>`, `### ${scene.point} — ${scene.title}`, '', `**What changed:** ${scene.change}`, '',
            `**Where:** ${scene.steps} [Open this screen](${'https://seera.tech-brit.co.uk' + scene.live}).`, '',
            `**Status:** ${scene.status || 'Prepared and tested; check portal availability with the administrator, then record your acceptance.'}`, '',
            `<img src="november-screenshots/en-${scene.slug}.jpg" alt="${scene.title} — fictional training example" loading="lazy" style="display:block;max-width:100%;height:auto;border:1px solid #667085;border-radius:8px"/>`, '');
        if (captures.some(c => c.slug === scene.slug && c.locale === 'ar')) md.push('<details>', '<summary>Arabic screenshot / الصورة العربية</summary>', '', `<img src="november-screenshots/ar-${scene.slug}.jpg" alt="${scene.title} — Arabic training example" loading="lazy" style="display:block;max-width:100%;height:auto;border:1px solid #667085;border-radius:8px"/>`, '', '</details>', '');
    }
    md.push('<!-- NOVEMBER_SCREENSHOTS_END -->', '');
    const doc = path.resolve('docs/user-guide/CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.md');
    let content = fs.readFileSync(doc, 'utf8');
    if (content.includes('<!-- NOVEMBER_SCREENSHOTS_START -->')) content = content.replace(/<!-- NOVEMBER_SCREENSHOTS_START -->[\s\S]*?<!-- NOVEMBER_SCREENSHOTS_END -->\s*/, md.join('\n'));
    else content = content.replace('## Client confirmation checklist', md.join('\n') + '\n## Client confirmation checklist');
    fs.writeFileSync(doc, content);
    console.log(JSON.stringify({ screenshots: captures.length, errors: errors.length, business_forms_submitted: false }));
})().catch(e => { console.error(e); process.exitCode = 1; });
