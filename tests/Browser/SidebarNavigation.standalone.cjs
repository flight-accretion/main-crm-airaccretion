const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require('C:/Users/reser/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'../..'),dir=process.argv[2];
if(!dir)throw Error('Pass the rendered navigation fixture directory.');
const files=fs.readdirSync(dir).filter(file=>file.endsWith('.html'));
const targetPaths=['/admin/hr','/admin/attendance/import','/admin/attendance/settings','/admin/kpi','/admin/kpi/work-done','/admin/operations','/admin/kpi-management','/admin/hr/operations-manual-kpi','/admin/kpi-history','/admin/kpi/outreach','/admin/targets','/admin/targets/incentives','/admin/upcoming-follow-up','/admin/lead','/admin/dnp-leads'];
(async()=>{const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});try{
 for(const file of files){
  const page=await browser.newPage({viewport:{width:1440,height:1050}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',route=>{const url=new URL(route.request().url());if(url.pathname.startsWith('/assets/')){const asset=path.join(root,'public',url.pathname);return fs.existsSync(asset)?route.fulfill({path:asset}):route.fulfill({body:'',contentType:url.pathname.endsWith('.js')?'text/javascript':'text/css'});}return route.fulfill({contentType:'text/html',body:fs.readFileSync(path.join(dir,file),'utf8')});});
  await page.goto('http://nav.test/admin/attendance/settings');
  await page.locator('#test-more').evaluate(button=>{const nav=document.createElement('nav');nav.className='test-mobile-nav';button.before(nav);nav.append(button);});
  const before=await page.locator('.main-menu').evaluate(menu=>Array.from(menu.querySelectorAll('a[href]')).filter(a=>/^https?:/.test(a.href)).map(a=>new URL(a.href).pathname.replace(/\/$/,'')).filter(Boolean));
  if(fs.existsSync(path.join(root,'public/assets/admin/js/crm/navigation.js')))await page.addScriptTag({url:'http://nav.test/assets/admin/js/crm/navigation.js'});
  const labels=await page.locator('.main-menu > [data-crm-primary]').evaluateAll(items=>items.map(item=>item.dataset.crmPrimary));
  const permitted=p=>before.includes(p);
  const expected=[permitted('/admin/hr')?'HR Dashboard':null,permitted('/admin/attendance/import')||permitted('/admin/attendance/settings')?'Attendance':null,permitted('/admin/kpi')?'KPI':null,permitted('/admin/targets')?'Target':null,permitted('/admin/upcoming-follow-up')?"Today's Follow-up":null,permitted('/admin/lead')||permitted('/admin/dnp-leads')?'Leads':null].filter(Boolean);
  assert.deepEqual(labels,expected,'role tree '+file);
  const after=await page.locator('.main-menu').evaluate(menu=>Array.from(menu.querySelectorAll('a[href]')).filter(a=>/^https?:/.test(a.href)).map(a=>new URL(a.href).pathname.replace(/\/$/,'')).filter(Boolean));
  assert.deepEqual([...new Set(after)].sort(),[...new Set(before)].sort(),'authorized URLs changed '+file);
  assert.equal(await page.locator('.crm-nav-group').evaluateAll(groups=>groups.every(g=>g.querySelector('a[href]'))),true);
  if(permitted('/admin/targets'))assert.equal(await page.locator('.main-menu a').filter({hasText:/^Assign Target$/}).count(),1);
  if(permitted('/admin/kpi-history'))assert.equal(await page.locator('[data-crm-primary=KPI] a[href$="kpi-history"]').count(),1);
  if(permitted('/admin/operations'))assert.equal(await page.locator('[data-crm-key=daily-work] a').filter({hasText:/^Operations$/}).count(),1);
  const tree=await page.evaluate(()=>window.CrmNavigation.tree());
  for(const width of [1440,1024,390,320]){
   await page.setViewportSize({width,height:1050});await page.locator('#test-more').click();
   await page.waitForFunction(()=>document.getElementById('test-more-panel').classList.contains('crm-more-tree'));
   assert.deepEqual(await page.locator('#test-more-panel').evaluate(panel=>Array.from(panel.querySelectorAll('a[href]')).map(a=>new URL(a.href).pathname)),after.map(p=>p||'/'));
   const mobileTree=await page.locator('#test-more-panel').evaluate(panel=>{
    const read=parent=>Array.from(parent.children).map(node=>node.tagName==='DETAILS'?{label:node.querySelector('summary').textContent,children:read(node.querySelector('.crm-more-children'))}:{label:node.textContent,href:node.href});return read(panel);
   });
   const compact=nodes=>nodes.map(node=>node.children?{label:node.label,children:compact(node.children)}:{label:node.label,href:node.href});
   assert.deepEqual(mobileTree,compact(tree),'More nesting differs from desktop '+file);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,file+' overflow '+width);
   if(file==='hr.html'&&width===1440){await page.locator('[data-crm-primary=KPI] > details > summary').click();await page.screenshot({path:path.join(dir,'sidebar-desktop.png'),fullPage:true});}
   if(file==='hr.html'&&width===390)await page.screenshot({path:path.join(dir,'more-mobile.png'),fullPage:true});
   await page.locator('#test-more').click();
  }
  assert.deepEqual(errors,[]);console.log('PASS role-preserving desktop/mobile tree '+file);await page.close();
 }
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
