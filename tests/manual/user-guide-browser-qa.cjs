// Authenticated and file-independent guide validation; no business write.
const fs=require('node:fs'),path=require('node:path');
const {pathToFileURL}=require('node:url');
const {chromium}=require(process.env.SEERA_PLAYWRIGHT || 'playwright');
(async()=>{
  const root=fs.realpathSync(process.argv[2]||'');
  if(!/^seera-guide-[a-f0-9]{32}$/.test(path.basename(root)))throw new Error('Disposable fixture required');
  const facts=JSON.parse(fs.readFileSync(path.join(root,'fixture.private.json')));
  const env=JSON.parse(fs.readFileSync(path.join(root,'process-env.private.json')));
  const base=env.APP_URL, proof=await(await fetch(base+'/__guide_fixture_attestation')).json();
  if(!proof.synthetic || proof.id!==facts.id || proof.production_zatca || proof.business_writes)throw new Error('Unsafe fixture');
  const out=process.env.SEERA_GUIDE_QA_OUT ? path.resolve(process.env.SEERA_GUIDE_QA_OUT) : path.resolve(__dirname,'../../docs/release-readiness/evidence/visual-guide-2026-11-02');
  fs.mkdirSync(out,{recursive:true});
  const results=[],errors=[],failed=[];
  const check=(name,ok,detail=null)=>{results.push({name,ok:!!ok,detail});if(!ok)throw new Error(name)};
  const browser=await chromium.launch({headless:true,executablePath:process.env.SEERA_BROWSER});
  try{
    const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1440,height:1000}});
    await context.route('**/*',r=>new URL(r.request().url()).origin===base?r.continue():r.abort());
    const page=await context.newPage();page.setDefaultTimeout(60000);page.on('pageerror',e=>errors.push(String(e)));
    const guest=await page.goto(base+'/user-guide/VISUAL-EXAMPLES.html');
    check('Guest redirected to login',new URL(page.url()).pathname==='/login' && guest.status()===200);
    await page.fill('[name=email]',facts.user);await page.fill('[name=password]',facts.password);
    await Promise.all([page.waitForURL(u=>u.pathname.startsWith('/admin/')||u.pathname.startsWith('/user-guide/')),page.click('button[type=submit]')]);
    if(process.env.SEERA_GUIDE_QA_EXPECT_NOTIFICATIONS==='YES'){
      await page.goto(base+'/admin/dashboard');
      check('Combined candidate retains notification bell',await page.locator('[data-notification-center]').count()===1);
      check('Combined candidate retains notification inbox',(await context.request.get(base+'/admin/notifications')).status()===200);
    }
    page.on('response',r=>{if(r.status()>=400)failed.push({path:new URL(r.url()).pathname,status:r.status()})});
    for(const [file,lang] of [['VISUAL-EXAMPLES.html','en'],['VISUAL-EXAMPLES-AR.html','ar']]){
      const response=await page.goto(base+'/user-guide/'+file);
      console.log('CHECK '+lang+' guide response '+response.status());
      check(lang+' guide served',response.status()===200);
      check(lang+' guide language/direction',await page.locator('html').getAttribute('lang')===lang && await page.locator('html').getAttribute('dir')===(lang==='ar'?'rtl':'ltr'));
      check(lang+' guide has 43 training scenes',await page.locator('section[data-topic]').count()===43);
      const imgs=await page.locator('img').evaluateAll(async images=>{
        images.forEach(image=>{image.loading='eager';});
        return Promise.race([
          Promise.all(images.map(async image=>{await image.decode();return {embedded:image.src.startsWith('data:image/jpeg;base64,'),width:image.naturalWidth,height:image.naturalHeight}})),
          new Promise((_,reject)=>setTimeout(()=>reject(new Error('Embedded image decode timed out')),30000))
        ]);
      });
      console.log('CHECK '+lang+' decoded '+imgs.length+' images');
      check(lang+' 47 embedded images decode',imgs.length===47 && imgs.every(x=>x.embedded&&x.width>0&&x.height>0));
      check(lang+' example values and coverage boundary visible',(await page.locator('body').textContent()).includes('TRAIN-INV-01') && (await page.locator('body').textContent()).includes(lang==='en'?'Notification Center':'مركز الإشعارات'));
      for(const width of [320,390,768,1440]){
        await page.setViewportSize({width,height:1000});
        const bounds=await page.evaluate(()=>({viewport:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth}));
        check(lang+' guide no page overflow '+width,bounds.scroll<=bounds.viewport+1,bounds);
        if(width===390||width===1440)await page.screenshot({path:path.join(out,'guide-'+lang+'-'+width+'.png'),animations:'disabled'});
      }
      const flip=page.locator('nav a').filter({hasText:lang==='en'?'العربية':'English'});
      await Promise.all([page.waitForNavigation(),flip.click()]);
      check(lang+' language edition navigation works',await page.locator('html').getAttribute('lang')===(lang==='en'?'ar':'en'));
    }
    for(const file of ['index.html','SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.html','SCREEN-INDEX.html','WORKFLOW-INDEX.html']){
      check(file+' served',(await page.goto(base+'/user-guide/'+file)).status()===200);
    }
    const offlineDirectory=process.argv[3]?fs.realpathSync(process.argv[3]):path.resolve(__dirname,'../../docs/user-guide');
    const offline=await browser.newContext({javaScriptEnabled:false,serviceWorkers:'block'});
    await offline.route('**/*',r=>new URL(r.request().url()).protocol==='file:'?r.continue():r.abort());
    const local=await offline.newPage();
    for(const [file,lang] of [['VISUAL-EXAMPLES.html','en'],['VISUAL-EXAMPLES-AR.html','ar']]){
      await local.goto(pathToFileURL(path.join(offlineDirectory,file)).href);
      const valid=await local.locator('img').evaluateAll(async images=>{
        images.forEach(image=>{image.loading='eager'});
        await Promise.all(images.map(image=>image.decode()));
        return images.length===47&&images.every(image=>image.complete&&image.naturalWidth>0);
      });
      check(lang+' offline packaged guide images decode',valid);
      await Promise.all([local.waitForNavigation(),local.locator('nav a').filter({hasText:lang==='en'?'العربية':'English'}).click()]);
      check(lang+' offline language link works',await local.locator('html').getAttribute('lang')===(lang==='en'?'ar':'en'));
    }
    await offline.close();
    check('No guide page errors',errors.length===0,errors);
    check('No failed guide responses',failed.length===0,failed);
    fs.writeFileSync(path.join(out,'browser-results.json'),JSON.stringify({summary:{total:results.length,passed:results.filter(x=>x.ok).length,failed:results.filter(x=>!x.ok).length},results,errors,failedResponses:failed},null,2));
    console.log('PASS '+results.length+' guide browser checks; 94 decoded images; no page errors.');
  }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1});
