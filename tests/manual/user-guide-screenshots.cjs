// SEERA visual user guide: screenshot capture ONLY on explicitly allowed localhost/staging.
// Requires: npm i --no-save playwright, or SEERA_PLAYWRIGHT=<path to installed playwright>.
// Does not submit forms, click save, mutate records, or access production.
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.SEERA_PLAYWRIGHT || 'playwright');

const BASE = (process.env.SEERA_GUIDE_BASE || 'http://127.0.0.1:8000').replace(/\/$/, '');
const url = new URL(BASE);
if (!(url.protocol === 'http:' && ['127.0.0.1', 'localhost'].includes(url.hostname)) &&
    !(process.env.SEERA_GUIDE_ALLOW_STAGING === 'I_CONFIRM_SYNTHETIC_STAGING' &&
      url.protocol === 'https:' && /^staging[.-]/i.test(url.hostname))) {
  throw new Error('Only localhost or explicitly acknowledged staging domains are allowed. Production access blocked.');
}
const EMAIL = process.env.SEERA_GUIDE_USER;
const PASSWORD = process.env.SEERA_GUIDE_PASSWORD;
if (!EMAIL || !PASSWORD) throw new Error('Supply disposable fixture credentials via SEERA_GUIDE_USER and SEERA_GUIDE_PASSWORD.');
const OUT = path.resolve(__dirname, '../../docs/user-guide/visual-examples');
fs.mkdirSync(OUT, {recursive:true});
const scenes = [
  {slug:'dashboard', title:'Dashboard overview', href:'/admin/dashboard', fields:{}},
  {slug:'customer-create', title:'Create a customer — fictional fields', href:'/admin/master/customers/create', fields:{
    name:'Al Noor Demo Contracting LLC',code:'DEMO-CUS-001',vat_number:'',cr_number:'',contact_person:'Training Contact',
    phone:'+966500000000',email:'training-customer@example.test',credit_limit:'25000',billing_address:'Example Street, Riyadh — training only'
  }},
  {slug:'supplier-create', title:'Create a supplier — fictional fields', href:'/admin/master/suppliers/create', fields:{
    name:'Al Waha Demo Materials',code:'DEMO-SUP-001',contact_person:'Training Supplier',
    phone:'+966500000001',email:'training-supplier@example.test'
  }},
  {slug:'project-create', title:'Create a project — fictional fields', href:'/admin/master/projects/create', fields:{
    name:'Training Warehouse Renovation',code:'DEMO-PRJ-001',budget:'150000',location:'Riyadh — training site',
    description:'Demonstration project: never submit this form.'
  }},
  {slug:'invoice-create', title:'Customer invoice — fictional example', href:'/admin/accounting/accounts-receivable/create', fields:{
    invoice_number:'DEMO-INV-001',notes:'Training example only — never submit or post.',
    'lines[0][description]':'Training construction service','lines[0][quantity]':'2',
    'lines[0][unit_price]':'5000'
  }}
];
(async()=>{
  const browser=await chromium.launch({headless:true,executablePath:process.env.SEERA_BROWSER || undefined});
  const items=[];
  try {
    const context=await browser.newContext({viewport:{width:1440,height:980},serviceWorkers:'block'});
    await context.route('**/*',route=>{
      const target=new URL(route.request().url());
      return target.origin===url.origin ? route.continue() : route.abort();
    });
    const page=await context.newPage();
    await page.goto(BASE+'/login',{waitUntil:'domcontentloaded'});
    await page.locator('input[name=email]').fill(EMAIL);
    await page.locator('input[name=password]').fill(PASSWORD);
    await Promise.all([page.waitForURL(/\/admin\/|\/set-password/,{timeout:20000}),page.locator('button[type=submit]').click()]);
    if (page.url().includes('set-password')) throw new Error('Use a disposable account with password-change completed; do not change passwords during capture.');
    for (const scene of scenes) {
      const response=await page.goto(BASE+scene.href,{waitUntil:'domcontentloaded'});
      if (!response || response.status()!==200) { console.warn('SKIP '+scene.slug+' HTTP '+(response?.status()||'unknown'));continue; }
      // Fill via browser only, never submit. Do not invent dropdown record IDs.
      for (const [name,value] of Object.entries(scene.fields)) {
        const selector='input[name="'+name.replace(/"/g,'\\"')+'"],textarea[name="'+name.replace(/"/g,'\\"')+'"]';
        const input=page.locator(selector).first();
        if(await input.count() && await input.isVisible() && await input.isEnabled() && !(await input.getAttribute('readonly'))) {
          await input.fill(value);
        }
      }
      // Data masking: never include live selects, existing records, account details or username in image.
      await page.evaluate(()=>{
        for (const el of document.querySelectorAll('select')) {
          if(el.selectedOptions.length && el.selectedOptions[0]?.value) {
            el.style.filter='blur(5px)';
          }
        }
        for(const el of document.querySelectorAll('.avatar,.topbar .breadcrumb,.sidebar-user,.user-profile')) {
          el.style.filter='blur(8px)';
        }
      });
      const image=scene.slug+'.png';
      await page.screenshot({path:path.join(OUT,image),fullPage:true,animations:'disabled'});
      items.push({title:scene.title,image,path:scene.href,values:scene.fields});
      console.log('CAPTURE '+image);
    }
  } finally {await browser.close();}
  const esc=x=>String(x).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const cards=items.map((x,i)=>'<section><h2>'+(i+1)+'. '+esc(x.title)+'</h2><p><code>'+esc(x.path)+'</code> · Read-only capture; no form was saved.</p><img src="'+esc(x.image)+'" alt="'+esc(x.title)+'"><table><tr><th>Field</th><th>Fictional training example</th></tr>'+Object.entries(x.values).map(([k,v])=>'<tr><td>'+esc(k)+'</td><td>'+esc(v)+'</td></tr>').join('')+'</table></section>').join('\n');
  const html='<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SEERA — Screenshot Walkthrough</title><style>body{font:16px/1.5 system-ui;max-width:1100px;margin:auto;padding:24px;background:#f8fafc;color:#17233a}section{background:white;border:1px solid #d9e2ec;border-radius:12px;margin:28px 0;padding:20px}img{display:block;max-width:100%;height:auto;border:1px solid #ddd}td,th{padding:8px;border-bottom:1px solid #eee;text-align:left}table{width:100%}code{overflow-wrap:anywhere}</style><h1>SEERA ERP — Visual User Guide</h1><p><strong>Training data only.</strong> Screens are captured from an isolated demo environment without submitting a form. Screens requiring linked existing customers/accounts are intentionally left unselected. Actual business workflows need valid access and approvals.</p>'+cards+'</html>';
  fs.writeFileSync(path.resolve(__dirname,'../../docs/user-guide/VISUAL-EXAMPLES.html'),html);
  console.log('Visual guide generated. Review image data before committing. Captured '+items.length+' screens.');
})().catch(err=>{console.error(err);process.exitCode=1;});
