const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'C:/Users/reser/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'../..'),dir=process.argv[2];
if(!dir)throw Error('Pass the synthetic Payment Review fixture directory.');
(async()=>{
    const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try{
        for(const width of [1440,1024,768,390,320]){
            const page=await browser.newPage({viewport:{width,height:950}});
            await page.route('**/*',route=>{
                const url=new URL(route.request().url());
                if(url.pathname.startsWith('/assets/'))return route.fulfill({path:path.join(root,'public',url.pathname)});
                // Layout-only fixture: preserve the real rendered markup and CSS without starting payment AJAX workflows.
                const html=fs.readFileSync(path.join(dir,'index.html'),'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
                return route.fulfill({contentType:'text/html',body:html});
            });
            await page.goto('http://crm.test/admin/account/payment-review');
            const positions=await page.locator('.pr-filters>.pr-field').evaluateAll(labels=>labels.map(label=>{
                const node=[...label.childNodes].find(n=>n.nodeType===3 && n.textContent.trim());const range=document.createRange();range.selectNodeContents(node);
                const text=range.getBoundingClientRect(),field=label.querySelector('input,select').getBoundingClientRect();
                return {stacked:field.top>=text.bottom,left:Math.abs(field.left-text.left),height:field.height};
            }));
            assert.equal(positions.length,6);assert.ok(positions.every(p=>p.stacked && p.left<2 && p.height>=40),JSON.stringify(positions));
            assert.equal(await page.locator('.pr-table tbody tr').count(),2);
            assert.equal(await page.locator('nav[aria-label="Pagination Navigation"]').count(),0,'Two visible results must not show phantom pages');
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`${width}px overflow`);
            await page.screenshot({path:path.join(dir,`payment-review-${width}.png`)});await page.close();
            console.log(`PASS ${width}px: label/input alignment, two visible rows, no phantom pagination and no overflow`);
        }
    }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
