const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require('C:/Users/reser/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'../..'),dir=process.argv[2];
if(!dir)throw Error('Pass the read-only rendered dashboard fixture directory.');
// Exercise the full server-rendered shell and the two navigation scripts without external widgets/network.
const documentFor=file=>fs.readFileSync(path.join(dir,file),'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'')
 .replace('</head>','<style>#loader{display:none!important}</style></head>')
 .replace('</body>','<script src="/assets/admin/js/crm/navigation.js"></script><script src="/assets/admin/js/crm/operations-mobile-nav.js"></script></body>');
(async()=>{const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});try{
 for(const role of ['ops','super']){
  const page=await browser.newPage({viewport:{width:1440,height:1050}}),errors=[],writes=[];
  page.on('pageerror',error=>errors.push(error.message));
  await page.route('**/*',route=>{
   const req=route.request(),url=new URL(req.url());
   if(req.method()!=='GET'){writes.push(url.pathname);return route.abort();}
   if(url.pathname.startsWith('/assets/')){const file=path.join(root,'public',url.pathname);return fs.existsSync(file)?route.fulfill({path:file}):route.fulfill({status:204});}
   if(req.resourceType()!=='document')return route.fulfill({status:204});
   return route.fulfill({contentType:'text/html',body:documentFor(role+'-'+(url.searchParams.get('cases')==='due'?'due':url.searchParams.has('cases')?'cases':'main')+'.html')});
  });
  await page.goto('http://home.test/admin/operations/dashboard');
  assert.equal(await page.locator('h1').innerText(),'Operations Dashboard');
  assert((await page.locator('.oh-incentive').innerText()).includes('Sample'));
  for(const width of [1440,1024,768,390,320])for(const dark of [false,true]){
   await page.setViewportSize({width,height:1050});
   await page.evaluate(dark=>{document.documentElement.classList.toggle('dark',dark);document.documentElement.classList.toggle('light',!dark);},dark);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`${role} overflow ${width} ${dark}`);
   const overflow=await page.locator('.oh-dashboard').evaluate(main=>Array.from(main.querySelectorAll('h1,h2,h3,p,strong,.oh-badge')).filter(e=>e.scrollWidth>e.clientWidth+2 && getComputedStyle(e).display!=='inline').map(e=>e.className+':'+e.textContent));
   assert.deepEqual(overflow,[],`${role} text overflow ${width}`);
   if(width<768){
    assert.equal(await page.locator('.sidemenu-toggle').isVisible(),false);
    await page.locator('[data-toggle-operations-more]').click();
    assert.equal(await page.locator('#operations-mobile-more').isVisible(),true);
    await page.keyboard.press('Escape');
   }
   if(!dark && [1440,390].includes(width))await page.screenshot({path:path.join(dir,`${role}-${width}.png`),fullPage:true});
  }
  await page.locator('.oh-today a').nth(2).click();
  assert.equal(new URL(page.url()).searchParams.get('cases'),'due');
  assert.equal(await page.locator('#oh-results h2').innerText(),'Due today');
  assert.deepEqual(errors,[]);assert.deepEqual(writes,[]);console.log('PASS full-shell dashboard layout light/dark, five widths, links, no writes '+role);
  await page.close();
 }
}finally{await browser.close();}})().catch(error=>{console.error(error);process.exitCode=1;});
