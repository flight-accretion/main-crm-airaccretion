const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const {chromium} = require('C:/Users/reser/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root = path.resolve(__dirname, '../..');
const states = ['ready', 'payment', 'vendor', 'urgent'];
const html = `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/admin/libs/fullcalendar/main.min.css">
<link rel="stylesheet" href="/assets/admin/css/crm/upcoming-rides.css">
<style>body{margin:0}#outside{color:#123456}</style></head><body>
<main class="crm-upcoming"><div id="calendar"></div></main><div id="outside" class="ur-event"><strong>Unrelated text</strong></div>
<script src="/assets/admin/libs/fullcalendar/main.min.js"></script><script>
new FullCalendar.Calendar(document.getElementById('calendar'), {
 initialView:'dayGridMonth',initialDate:'2026-10-21',height:'auto',headerToolbar:false,
 events:${JSON.stringify(states.map((state, i) => ({id:state, title:state, start:'2026-10-'+(20+i)+(i%2 ? '' : 'T10:30:00'), allDay:!!(i%2), classNames:['ur-event', 'ur-'+state]})))},
 eventContent:info=>{const content=document.createElement('div');for(const tag of ['strong','small']){const text=document.createElement(tag);text.textContent=info.event.title;content.append(text);}return {domNodes:[content]};}
}).render();
</script></body></html>`;
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000}});
  await page.route('**/*',route=>{
   const url=new URL(route.request().url());
   if(url.pathname.startsWith('/assets/')){const file=path.join(root,'public',url.pathname);return fs.existsSync(file)?route.fulfill({path:file}):route.abort();}
   return route.fulfill({contentType:'text/html',body:html});
  });
  await page.goto('http://calendar.test');await page.locator('.fc .ur-event').first().waitFor();
  assert.equal(await page.locator('.fc .ur-event').count(),4);
  for(const dark of [false,true]){
   await page.evaluate(dark=>document.documentElement.classList.toggle('dark',dark),dark);
   const borders=dark?['rgb(108, 214, 155)','rgb(228, 181, 84)','rgb(157, 191, 255)','rgb(255, 153, 170)']:['rgb(22, 134, 75)','rgb(173, 104, 0)','rgb(48, 110, 227)','rgb(206, 44, 66)'];
   for(const [index,state] of states.entries()){
    const event=page.locator('.fc .ur-event.ur-'+state);
    assert.equal(await event.evaluate(e=>getComputedStyle(e).borderLeftColor),borders[index],state+' must retain its status border');
    for(const tag of ['strong','small'])assert.equal(await event.locator(tag).evaluate(e=>getComputedStyle(e).color),dark?'rgb(230, 237, 244)':'rgb(0, 0, 0)',state+' '+tag+' text');
   }
   assert.equal(await page.locator('#outside strong').evaluate(e=>getComputedStyle(e).color),'rgb(18, 52, 86)','Do not change unrelated text');
  }
  await page.setViewportSize({width:390,height:844});
  assert.equal(await page.locator('.fc .ur-event strong').first().evaluate(e=>getComputedStyle(e).display),'none','Retain compact mobile strips');
  console.log('PASS timed/all-day text colours, four status borders, dark theme, scoped styling and compact mobile layout.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
