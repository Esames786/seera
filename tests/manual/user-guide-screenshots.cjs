// Capture real SEERA UI with synthetic fixture data. Business writes are blocked twice:
// by this browser route policy and the mandatory local PHP training router.
const fs=require('node:fs'), path=require('node:path'), cp=require('node:child_process'), crypto=require('node:crypto');
const {chromium}=require(process.env.SEERA_PLAYWRIGHT || 'playwright');
const ROOT=fs.realpathSync(process.argv[2] || '');
if (!/^seera-guide-[a-f0-9]{32}$/.test(path.basename(ROOT)) || process.env.SEERA_GUIDE_CONFIRM_SYNTHETIC!=='YES')
  throw new Error('Explicit synthetic confirmation and isolated fixture directory required.');
const env=JSON.parse(fs.readFileSync(path.join(ROOT,'process-env.private.json'),'utf8').replace(/^\uFEFF/,''));
const facts=JSON.parse(fs.readFileSync(path.join(ROOT,'fixture.private.json'),'utf8'));
const BASE=(process.env.SEERA_GUIDE_BASE || env.APP_URL).replace(/\/$/,'');
const url=new URL(BASE);
if (url.protocol!=='http:' || !['127.0.0.1','localhost'].includes(url.hostname) || url.username || url.password
    || url.search || url.hash || url.pathname!=='/' || BASE!==env.APP_URL
    || env.APP_ENV!=='testing' || env.DB_CONNECTION!=='sqlite' || env.ZATCA_PRODUCTION_ENABLED!=='false'
    || fs.realpathSync(env.DB_DATABASE)!==fs.realpathSync(path.join(ROOT,'training.sqlite')))
  throw new Error('Only an attested local synthetic SQLite fixture is permitted. Staging/production blocked.');
const CWD=path.resolve(__dirname,'../..'), OUT=path.join(CWD,'docs/user-guide');
const EVIDENCE=path.join(CWD,'docs/release-readiness/evidence/visual-guide-2026-11-02');
const IMAGES=path.join(OUT,'visual-examples');
const PHP=process.env.SEERA_PHP || 'php';
const scenes=require('./user-guide-scenes.cjs')(facts.ids);
const esc=x=>String(x??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const state=()=>JSON.parse(cp.execFileSync(PHP,[path.join(__dirname,'user-guide-fixture.php'),ROOT,'state'],
  {cwd:CWD,env:{...process.env,...env},encoding:'utf8',maxBuffer:10*1024*1024}));
const results=[], errors=[], blocked=[], badResponses=[];
const check=(name,ok,detail=null)=>{results.push({name,ok:!!ok,detail});if(!ok)throw new Error(name+': '+JSON.stringify(detail));};
const fieldSelector=name=>'[name="'+name.replace(/["\\]/g,'\\$&')+'"]:visible';
const dictionary={
  en:{title:'SEERA ERP — Visual Training Guide',home:'Guide home',manual:'Full user guide',other:'العربية',
    intro:'Real application screenshots from an isolated, freshly seeded training database. Every person, company, balance and transaction shown is fictional. Example form edits are unsaved; existing View pages show preloaded synthetic records.',
    boundary:'No Save, Submit, Approve, Post, Pay, Receive, Issue or Transfer was executed during capture. Only authentication and language preferences changed. This is training evidence, not business UAT, financial reconciliation, legal advice or production ZATCA validation.',
    coverage:'These examples cover the core ERP screens listed below. Notification Center is not illustrated in this edition. Labels and available actions may vary with your role and installed release.',
    common:'How to use the workspaces',steps:['Open the correct record and check its code, project/site and status. View is for reading; Edit / Manage exposes permitted changes.',
      'Complete the relevant section. + New opens related master-data entry; its record is created only when you deliberately save that dialog.',
      'Save & stay keeps the form open. Save & next saves then moves to the next section where supported. Save & new opens a new record; Save & close returns to the register.',
      'Back / Previous changes context. If edits exist, the unsaved-changes prompt offers the available choices; cancelling navigation retains your form. Do not assume Back saves.',
      'Approval, GL posting, receiving and payment are separate authorized workflow actions. Read-only View pages must not be used to change amounts.'],
    workflows:'Follow the connected business sequence',flows:['People: employee profile → employment/salary/documents → system account if required → attendance/leave → payroll processing → approval → GL posting → payment.',
      'Procurement: supplier + item + warehouse → Purchase Request → required approvals → Purchase Order → Goods Receipt → matched Supplier Bill → required approvals/accounting → Payment.',
      'Sales: customer + project → Customer Invoice → authorized posting → Receipt. Corrections use Credit/Debit Notes; unapplied credit may be allocated/refunded under its own controls. These financial actions are explained in the full guide, not executed here.',
      'Projects: customer → project → sites → budget/BOQ → approved expenses/material/equipment costs → project reports.'],
    values:'Fields actually filled / selected',field:'Field',value:'Training value',remaining:'Still required before a real save',buttons:'Visible actions (not executed)',route:'Screen route',screen:'Real screenshot',purpose:'What to do next',
    notAll:'These examples illustrate fields; they are not necessarily complete valid submissions. Replace training addresses and identifiers with verified business data and review remaining required inputs. Never reuse the training account or examples in production.',
    limits:'Coverage and acceptance limits',limitText:'GPS/device permissions, offline sync, bank-file acceptance, live payment, financial approval, credit/refund execution and ZATCA submissions were not exercised. Other-language labels remaining on captured Arabic screens reflect the actual application snapshot; the guide does not translate or certify the entire ERP UI.',
    mobile:'Mobile examples (390px)',empty:'No fields changed; read-only review of synthetic fixture records.',footer:'Captured 2 November 2026 · Core-based training snapshot · No production access'
  },
  ar:{title:'سيرا ERP — دليل التدريب المصور',home:'الرئيسية',manual:'دليل النظام الكامل',other:'English',
    intro:'لقطات فعلية من التطبيق باستخدام قاعدة تدريب معزولة ومهيأة من الصفر. جميع الأشخاص والشركات والأرصدة والمعاملات المعروضة افتراضية. تعديلات النماذج غير محفوظة، وصفحات العرض تحتوي سجلات تدريب محملة مسبقاً.',
    boundary:'لم تنفذ عمليات حفظ أو إرسال أو اعتماد أو ترحيل أو دفع أو استلام أو صرف أو تحويل أثناء التصوير. تغيرت بيانات تسجيل الدخول وتفضيلات اللغة فقط. هذه أدلة تدريب وليست اختبار قبول أعمال أو مطابقة مالية أو تحققاً إنتاجياً للفاتورة الإلكترونية.',
    coverage:'تغطي الأمثلة شاشات النظام الأساسية المدرجة أدناه. لا يتضمن هذا الإصدار شرحاً مصوراً لمركز الإشعارات. قد تختلف التسميات والإجراءات حسب الدور والإصدار المثبت.',
    common:'كيفية استخدام مساحات العمل',steps:['افتح السجل الصحيح وراجع الرمز والمشروع أو الموقع والحالة. العرض للقراءة، والتعديل يتيح التغييرات المصرح بها.',
      'أكمل القسم المطلوب. تفتح إضافة جديد نموذج بيانات أساسية مرتبطاً؛ لا ينشأ السجل إلا عند حفظ ذلك النموذج صراحةً.',
      'الحفظ والبقاء يبقي النموذج مفتوحاً. الحفظ والتالي يحفظ وينتقل للقسم التالي عند توفره. الحفظ والجديد يفتح سجلاً جديداً، والحفظ والإغلاق يعيدك للسجل.',
      'السابق أو الرجوع يغير السياق. عند وجود تعديلات تظهر خيارات التغييرات غير المحفوظة؛ إلغاء التنقل يبقي النموذج. لا تفترض أن الرجوع يحفظ.',
      'الاعتماد والترحيل والاستلام والدفع إجراءات منفصلة تتطلب صلاحيات. صفحات العرض لا تستخدم لتعديل المبالغ.'],
    workflows:'تسلسل العمل المترابط',flows:['الموارد البشرية: ملف الموظف ← الوظيفة والراتب والمستندات ← حساب النظام عند الحاجة ← الحضور والإجازة ← معالجة الرواتب ← الاعتماد ← الترحيل ← الصرف.',
      'المشتريات: المورد والصنف والمستودع ← طلب شراء ← الاعتمادات المطلوبة ← أمر شراء ← استلام بضاعة ← فاتورة مورد مطابقة ← الاعتماد والمحاسبة ← الدفع.',
      'المبيعات: العميل والمشروع ← فاتورة العميل ← الترحيل المصرح به ← سند القبض. تعالج التصحيحات بإشعارات الدائن والمدين، ويخصص الرصيد غير المستخدم أو يرد بضوابط مستقلة. يشرح الدليل الكامل هذه العمليات ولم تنفذ هنا.',
      'المشاريع: العميل ← المشروع ← المواقع ← الميزانية وجدول الكميات ← المصروفات والمواد وتكاليف المعدات المعتمدة ← تقارير المشروع.'],
    values:'الحقول المعبأة أو المختارة فعلياً',field:'الحقل',value:'قيمة التدريب',remaining:'متطلبات متبقية قبل الحفظ الفعلي',buttons:'الإجراءات الظاهرة — لم تنفذ',route:'مسار الشاشة',screen:'لقطة فعلية',purpose:'الخطوة التالية',
    notAll:'الأمثلة توضيحية وقد لا تمثل طلبات مكتملة صالحة للحفظ. استبدل عناوين التدريب والمعرفات ببيانات أعمال معتمدة وراجع الحقول الإلزامية المتبقية. لا تستخدم حساب التدريب أو الأمثلة في الإنتاج.',
    limits:'حدود التغطية والقبول',limitText:'لم تختبر صلاحيات أجهزة تحديد الموقع أو وضع عدم الاتصال أو اعتماد ملف البنك أو الدفع الفعلي أو الاعتماد المالي أو تنفيذ الإشعارات والتخصيص والاسترداد أو الإرسال للهيئة. الكلمات غير العربية المتبقية في اللقطات تعكس التطبيق الفعلي؛ الدليل لا يترجم كامل النظام ولا يعتمد لغته.',
    mobile:'أمثلة الهاتف — ٣٩٠ بكسل',empty:'لم تعدل حقول؛ مراجعة سجلات التدريب للقراءة فقط.',footer:'التصوير في ٢ نوفمبر ٢٠٢٦ · لقطة تدريب من الإصدار الأساسي · دون وصول للإنتاج'
  }
};
function render(lang,captures,source) {
  const t=dictionary[lang], other=lang==='en'?'VISUAL-EXAMPLES-AR.html':'VISUAL-EXAMPLES.html';
  const cards=captures.filter(x=>x.lang===lang && !x.mobile).map((x,i)=>{
    const scene=scenes.find(s=>s.slug===x.slug), title=lang==='ar'?scene.ar:scene.title;
    const img=fs.readFileSync(path.join(IMAGES,x.image)).toString('base64');
    const mobile=captures.find(y=>y.slug===x.slug && y.lang===lang && y.mobile);
    return '<section id="'+esc(x.slug)+'" data-topic="'+esc(scene.group)+'"><h2>'+String(i+1).padStart(2,'0')+' · '+esc(title)+'</h2><p class="route">'+esc(t.route)+': <code dir="ltr">'+esc(scene.href)+'</code></p>'
      +'<p>'+esc((lang==='ar'?scene.tipAr:scene.tip) || (lang==='ar'?'راجع السجل والحقول ثم استخدم الإجراء المصرح به. تفاصيل التشغيل في الدليل الكامل.':'Review the record and fields, then use the permitted action. Detailed operational steps are in the full guide.'))+'</p>'
      +'<img loading="lazy" src="data:image/jpeg;base64,'+img+'" alt="'+esc(title)+'" width="'+x.width+'" height="'+x.height+'">'
      +'<h3>'+esc(t.values)+'</h3>'+(x.values.length?'<div class="table-wrap"><table><thead><tr><th>'+esc(t.field)+'</th><th>'+esc(t.value)+'</th></tr></thead><tbody>'+x.values.map(v=>'<tr><td>'+esc(v.label)+'<small dir="ltr">'+esc(v.name)+'</small></td><td dir="auto">'+esc(v.value)+'</td></tr>').join('')+'</tbody></table></div>':'<p>'+esc(t.empty)+'</p>')
      +(x.remaining.length?'<p class="note"><strong>'+esc(t.remaining)+': </strong>'+x.remaining.map(esc).join(' · ')+'</p>':'')
      +'<p><strong>'+esc(t.buttons)+': </strong>'+x.buttons.map(esc).join(' · ')+'</p>'
      +(mobile?'<details><summary>'+esc(t.mobile)+'</summary><img loading="lazy" class="mobile" src="data:image/jpeg;base64,'+fs.readFileSync(path.join(IMAGES,mobile.image)).toString('base64')+'" alt="'+esc(title)+' 390px"></details>':'')+'</section>';
  }).join('\n');
  const toc=captures.filter(x=>x.lang===lang&&!x.mobile).map(x=>{const s=scenes.find(s=>s.slug===x.slug);return '<a href="#'+esc(x.slug)+'">'+esc(lang==='ar'?s.ar:s.title)+'</a>';}).join('');
  return '<!doctype html><html lang="'+lang+'" dir="'+(lang==='ar'?'rtl':'ltr')+'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'+esc(t.title)+'</title><style>'
    +'*{box-sizing:border-box}body{margin:0;background:#f3f6fa;color:#18283e;font:16px/1.7 system-ui,Segoe UI,sans-serif}header,main,footer{max-width:1200px;margin:auto;padding:24px}header{border-top:6px solid #087ef5}nav{display:flex;flex-wrap:wrap;gap:16px}a{color:#075ca9}h1{font-size:clamp(26px,4vw,38px);line-height:1.3}h2{font-size:24px}h3{font-size:18px}section,.intro{background:#fff;border:1px solid #d7e0ec;border-radius:12px;padding:22px;margin:24px 0;scroll-margin-top:12px;min-width:0}img{display:block;max-width:100%;height:auto;border:1px solid #d7e0ec;border-radius:6px}img.mobile{max-width:min(390px,100%);margin:auto}code,small{overflow-wrap:anywhere}.route{font-size:13px;color:#536276}.note{border-inline-start:4px solid #c67800;background:#fff8e8;padding:12px}.toc{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr));gap:8px}.toc a{display:block;border:1px solid #d7e0ec;border-radius:6px;padding:8px;text-decoration:none}.table-wrap{overflow:auto;max-width:100%}table{width:100%;border-collapse:collapse}th,td{text-align:start;border:1px solid #d7e0ec;padding:8px;overflow-wrap:anywhere}small{display:block;color:#607086;font-size:12px}summary{cursor:pointer;font-weight:bold;padding:12px}footer{font-size:13px;color:#536276}@media(max-width:480px){header,main,footer{padding:12px}section,.intro{padding:12px}th,td{padding:6px;font-size:14px}}@media print{body{background:#fff}nav,.toc{display:none}section{break-before:page;border:0;padding:0}img{max-height:230mm;width:auto;margin:auto}details{display:none}}'
    +'</style></head><body><header><nav><a href="index.html">'+esc(t.home)+'</a><a href="SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.html">'+esc(t.manual)+'</a><a href="'+other+'" lang="'+(lang==='en'?'ar':'en')+'">'+esc(t.other)+'</a></nav><h1>'+esc(t.title)+'</h1><p>'+esc(t.intro)+'</p><p class="note">'+esc(t.boundary)+'</p><p class="note">'+esc(t.coverage)+'</p></header><main><div class="intro"><h2>'+esc(t.common)+'</h2><ol>'+t.steps.map(v=>'<li>'+esc(v)+'</li>').join('')+'</ol><h2>'+esc(t.workflows)+'</h2><ul>'+t.flows.map(v=>'<li>'+esc(v)+'</li>').join('')+'</ul><p>'+esc(t.notAll)+'</p></div><div class="toc">'+toc+'</div>'+cards+'<section id="limits"><h2>'+esc(t.limits)+'</h2><p>'+esc(t.limitText)+'</p></section></main><footer>'+esc(t.footer)+'</footer></body></html>';
}
if(process.argv.includes('--render-reviewed')){
  const reviewed=JSON.parse(fs.readFileSync(path.join(EVIDENCE,'capture-results.json')));
  if(reviewed.summary.failed!==0 || reviewed.summary.screenshots!==94)throw new Error('A successful reviewed capture is required');
  for(const capture of reviewed.captures){
    const hash=crypto.createHash('sha256').update(fs.readFileSync(path.join(IMAGES,capture.image))).digest('hex');
    if(hash!==capture.sha256)throw new Error('Screenshot hash differs: '+capture.image);
  }
  for(const lang of ['en','ar'])fs.writeFileSync(path.join(OUT,lang==='en'?'VISUAL-EXAMPLES.html':'VISUAL-EXAMPLES-AR.html'),render(lang,reviewed.captures,reviewed.source));
  console.log('Both guide editions rendered from 94 hash-verified screenshots; no browser or database operation.');
}else (async()=>{
  fs.mkdirSync(IMAGES,{recursive:true}); fs.mkdirSync(EVIDENCE,{recursive:true});
  const attestation=await (await fetch(BASE+'/__guide_fixture_attestation')).json();
  check('Server attests synthetic isolated fixture',attestation.synthetic===true && attestation.id===facts.id && attestation.business_writes===false && attestation.production_zatca===false);
  const before=state(), captures=[];
  fs.writeFileSync(path.join(EVIDENCE,'business-before.json'),JSON.stringify(before,null,2));
  const browser=await chromium.launch({headless:true,executablePath:process.env.SEERA_BROWSER || undefined});
  try {
    const context=await browser.newContext({viewport:{width:1440,height:1000},serviceWorkers:'block'});
    await context.route('**/*',route=>{
      const r=route.request(), target=new URL(r.url());
      if(target.origin!==url.origin){blocked.push({method:r.method(),path:target.pathname,reason:'external origin'});return route.abort();}
      if(!['GET','HEAD'].includes(r.method()) && !(r.method()==='POST' && ['/login','/admin/locale'].includes(target.pathname))){
        blocked.push({method:r.method(),path:target.pathname,reason:'business write'});return route.abort();
      }
      return route.continue();
    });
    const login=await context.newPage();
    await login.goto(BASE+'/login'); await login.fill('[name=email]',facts.user); await login.fill('[name=password]',facts.password);
    await Promise.all([login.waitForURL('**/admin/**'),login.click('button[type=submit]')]);
    check('Synthetic account authenticated',new URL(login.url()).pathname==='/admin/dashboard');
    const token=await login.locator('input[name=_token]').first().inputValue();
    const negative=await context.request.post(BASE+'/admin/master/customers',{form:{_token:token,name:'Must not save'}});
    check('Server rejects a business-write probe',negative.status()===405);
    for(const lang of ['en','ar']){
      const locale=await context.request.post(BASE+'/admin/locale',{form:{_token:token,locale:lang},maxRedirects:0});
      check(lang+' locale preference accepted',locale.status()===302);
      for(const scene of scenes){
        const page=await context.newPage(); page.setDefaultTimeout(15000);
        page.on('pageerror',e=>errors.push({scene:scene.slug,lang,error:String(e)}));
        page.on('response',r=>{if(r.status()>=400)badResponses.push({scene:scene.slug,lang,status:r.status(),path:new URL(r.url()).pathname});});
        page.on('dialog',d=>d.accept());
        const response=await page.goto(BASE+scene.href,{waitUntil:'load',timeout:60000});
        check(lang+' '+scene.slug+' route',response?.status()===200 && new URL(page.url()).pathname===new URL(BASE+scene.href).pathname);
        await page.locator('.page').waitFor(); await page.evaluate(()=>document.fonts.ready);
        check(lang+' '+scene.slug+' document direction',await page.locator('html').getAttribute('dir')===(lang==='ar'?'rtl':'ltr'));
        const values=[];
        for(const [name,value] of Object.entries(scene.select)){
          const input=page.locator(fieldSelector(name)).first();
          check(lang+' '+scene.slug+' select '+name,await input.count()===1 && await input.isEnabled());
          const selected=value==='@first'?await input.locator('option').evaluateAll(els=>els.find(o=>o.value && !o.disabled)?.value):value;
          check(lang+' '+scene.slug+' has option '+name,!!selected);
          await input.selectOption(selected);
          values.push({name,label:await input.evaluate(e=>e.labels?.[0]?.textContent?.trim()||e.name),value:await input.locator('option:checked').textContent()});
        }
        for(const [name,value] of Object.entries(scene.fields)){
          const input=page.locator(fieldSelector(name)).first();
          check(lang+' '+scene.slug+' input '+name,await input.count()===1 && await input.isEnabled() && !await input.evaluate(e=>e.readOnly));
          await input.fill(value);
          check(lang+' '+scene.slug+' value '+name,await input.inputValue()===value);
          values.push({name,label:await input.evaluate(e=>e.labels?.[0]?.textContent?.trim()||e.name),value});
        }
        if(scene.employeeLookup){
          await page.fill('#employee-search','GUIDE-EMP-01');
          await page.locator('#employee-search-results button').filter({hasText:'Sara Training'}).click();
          check(lang+' employee lookup copies identity',(await page.locator('#name').inputValue())==='Sara Training' && (await page.locator('[name=source_employee_id]').inputValue())===String(facts.ids.unlinked_employee));
          for(const name of ['name','employee_id','email']){const input=page.locator(fieldSelector(name)).first(); values.push({name,label:await input.evaluate(e=>e.labels?.[0]?.textContent?.trim()||e.name),value:await input.inputValue()});}
        }
        await page.evaluate(()=>window.scrollTo(0,0));
        // A clearly separate training stamp, without rewriting application labels or values.
        await page.evaluate(text=>{const stamp=document.createElement('div');stamp.textContent=text;stamp.style.cssText='position:fixed;bottom:8px;right:8px;z-index:2147483647;background:#143553;color:white;padding:5px 10px;border-radius:5px;font:12px system-ui;pointer-events:none';document.body.append(stamp);},lang==='ar'?'تدريب افتراضي — لم يحفظ النموذج':'SYNTHETIC TRAINING — no form saved');
        const remaining=await page.locator('.page input:required:visible,.page select:required:visible,.page textarea:required:visible').evaluateAll(els=>els.filter(e=>!e.value).map(e=>e.labels?.[0]?.textContent?.trim()||e.name));
        const buttons=await page.locator('.page button:visible,.page a.btn:visible').allTextContents();
        const image=lang+'-'+scene.slug+'.jpg', file=path.join(IMAGES,image);
        await page.screenshot({path:file,type:'jpeg',quality:72,fullPage:true,animations:'disabled'});
        const size=await page.evaluate(()=>({width:document.documentElement.clientWidth,height:Math.max(document.body.scrollHeight,document.documentElement.scrollHeight)}));
        captures.push({slug:scene.slug,lang,image,values,remaining,buttons:[...new Set(buttons.map(s=>s.trim()).filter(Boolean))],...size,sha256:crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex')});
        if(['dashboard','customer-create','supplier-create','employee-personal'].includes(scene.slug)){
          await page.setViewportSize({width:390,height:844});
          const mobileImage=lang+'-'+scene.slug+'-390.jpg';
          await page.screenshot({path:path.join(IMAGES,mobileImage),type:'jpeg',quality:72,fullPage:true,animations:'disabled'});
          captures.push({slug:scene.slug,lang,mobile:true,image:mobileImage,sha256:crypto.createHash('sha256').update(fs.readFileSync(path.join(IMAGES,mobileImage))).digest('hex')});
        }
        console.log('CAPTURE '+lang+' '+scene.slug+' ('+values.length+' verified values)'); await page.close();
      }
    }
    await login.close();
    const after=state();
    fs.writeFileSync(path.join(EVIDENCE,'business-after.json'),JSON.stringify(after,null,2));
    check('All business tables and row contents unchanged',JSON.stringify(before)===JSON.stringify(after),
      {tables:Object.keys(before).length,changed:Object.keys(before).filter(name=>JSON.stringify(before[name])!==JSON.stringify(after[name])).map(name=>({name,before:before[name],after:after[name]}))});
    check('No browser page errors',errors.length===0,errors);
    check('No failed application responses',badResponses.length===0,badResponses);
    check('No attempted business writes during capture',blocked.every(request=>request.reason==='external origin'),blocked.filter(request=>request.reason!=='external origin'));
    check('External CDN requests denied by capture policy',blocked.every(request=>request.reason==='external origin'),{blockedRequests:blocked.length});
    for(const lang of ['en','ar'])fs.writeFileSync(path.join(OUT,lang==='en'?'VISUAL-EXAMPLES.html':'VISUAL-EXAMPLES-AR.html'),render(lang,captures,attestation.source));
    fs.writeFileSync(path.join(EVIDENCE,'capture-results.json'),JSON.stringify({source:attestation.source,base:BASE,synthetic:true,
      summary:{scenes:scenes.length,screenshots:captures.length,checks:results.length,passed:results.filter(x=>x.ok).length,failed:results.filter(x=>!x.ok).length},
      results,errors,badResponses,blocked,businessStateBefore:before,businessStateAfter:after,captures},null,2));
    console.log('COMPLETE '+captures.length+' screenshots; '+results.length+' checks passed; business tables unchanged.');
  }finally{await browser.close();}
})().catch(e=>{fs.mkdirSync(EVIDENCE,{recursive:true});fs.writeFileSync(path.join(EVIDENCE,'capture-failure.json'),JSON.stringify({results,errors,badResponses,blocked,error:String(e)},null,2));console.error(e);process.exitCode=1;});
