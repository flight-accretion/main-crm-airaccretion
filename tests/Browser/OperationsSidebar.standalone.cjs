const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require('C:/Users/reser/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'../..'),dir=process.argv[2];
const sections=['OVERVIEW','RIDE MANAGEMENT','OPERATIONS QUEUES','LEADS & PAYMENTS','MASTER DATA & TOOLS'];
async function checkOperationsTree(page){
 const tree=await page.evaluate(()=>window.CrmNavigation.tree());
 assert.deepEqual(tree.filter(item=>item.section).map(item=>item.label),sections);
 assert.deepEqual(tree.find(item=>item.label==='KPI').children.map(item=>item.label),['KPI Dashboard','Daily Work Done','Manual KPI']);
 assert.deepEqual(tree.find(item=>item.label==='Leads').children.map(item=>item.label),['View Leads']);
 assert.equal(await page.locator('.main-menu a[href*="/admin/dnp-leads"],.main-menu a[href$="/admin/lead/create"]').count(),0);
 assert.deepEqual(tree.filter(item=>['Review Queue','Reschedule Queue','Refund Queue','Vendor Follow-ups'].includes(item.label)).map(item=>item.label),['Review Queue','Reschedule Queue','Refund Queue','Vendor Follow-ups']);
 assert.deepEqual(tree.find(item=>item.label==='Refund Queue').children.map(item=>item.label),['Customer Refund','Vendor Refund']);
 const tools=tree.find(item=>item.label==='Services & Products');
 const authorized=await page.evaluate(()=>window.CrmNavigation.authorizedLinks().map(a=>new URL(a.href).pathname));
 const expected=[['/admin/services/create','Add Service'],['/admin/services','View Services'],['/admin/products','View Products'],['/admin/product-sync','Product Sync (Airpoints)'],['/admin/service-addresses','View Service Addresses']].filter(([url])=>authorized.includes(url)).map(([,label])=>label);
 assert.deepEqual(tools?.children.map(item=>item.label)||[],expected);
}
(async()=>{const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});try{
for(const role of ['operations-executive','operations-manager','senior-operations-manager','super','hr','sales-executive','accounts-executive']){
const page=await browser.newPage({viewport:{width:1440,height:1000}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.route('**/*',route=>{const u=new URL(route.request().url());if(u.pathname.startsWith('/assets/')){const file=path.join(root,'public',u.pathname);return fs.existsSync(file)?route.fulfill({path:file}):route.abort();}return route.fulfill({contentType:'text/html',body:fs.readFileSync(path.join(dir,role+'-operations.html'),'utf8')});});
await page.goto('http://ops-nav.test/admin/operations/queue/review');
if(role.includes('operations')){
 await checkOperationsTree(page);
 await page.setViewportSize({width:390,height:844});await page.locator('[data-toggle-operations-more]').click();
 assert.deepEqual(await page.locator('#operations-mobile-more .crm-more-section').allTextContents(),sections);
 assert.equal(await page.locator('#operations-mobile-more a[href*="/admin/dnp-leads"],#operations-mobile-more a[href$="/admin/lead/create"]').count(),0);
 await page.keyboard.press('Escape');await page.setViewportSize({width:1440,height:1000});
}
if(role==='super'){
 const select=page.locator('[data-sidebar-workspace]');assert.equal(await select.count(),1,'Super Admin needs a workspace selector');
 const all=await page.locator('.main-menu a[href]').evaluateAll(items=>items.map(e=>e.href));assert.equal(new Set(all).size,all.length,'No duplicate destinations');
 for(const view of ['operations','hr','sales','accounts','all']){await select.selectOption(view);assert.equal(await select.inputValue(),view);const hrefs=await page.locator('.main-menu a[href]').evaluateAll(items=>items.map(e=>e.href));assert(hrefs.length>0);assert(hrefs.every(h=>all.includes(h)),'Workspace must not grant permissions');assert.equal(new Set(hrefs).size,hrefs.length);if(view==='operations')await checkOperationsTree(page);if(view==='all')assert.deepEqual(hrefs,all,'Restore all authorized links');}
 await select.selectOption('operations');await page.reload();assert.equal(await select.inputValue(),'operations');
 await page.setViewportSize({width:390,height:844});await page.locator('[data-toggle-operations-more]').click();await page.locator('#operations-mobile-more').waitFor({state:'visible'});assert.equal(await page.locator('#operations-mobile-more [data-sidebar-workspace]').count(),1);await page.locator('#operations-mobile-more [data-sidebar-workspace]').selectOption('hr');assert((await page.locator('#operations-mobile-more').innerText()).includes('HR Dashboard'));await page.keyboard.press('Escape');
}else assert.equal(await page.locator('[data-sidebar-workspace]').count(),0);
assert.deepEqual(errors,[]);console.log('PASS Operations sidebar / authorized workspace views '+role);await page.close();}
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
