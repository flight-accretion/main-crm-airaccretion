const fs=require('node:fs'), path=require('node:path'), assert=require('node:assert/strict'), vm=require('node:vm');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'C:/Users/reser/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'../..'), dir=process.argv[2];
if(!dir) throw Error('Pass the synthetic fixture directory.');
function extract(file,start,end) {
    const source=fs.readFileSync(path.join(root,file),'utf8'); const from=source.indexOf(start), to=source.indexOf(end,from);
    assert.ok(from>=0 && to>from,'Missing production function');
    return source.slice(from,to).replace(/\{\{[\s\S]*?\}\}/g,'/admin/followups/files/__file__');
}
for(const file of ['rides/ride-status','payment-review/payment-review']) {
    const code=extract(`resources/views/admin/account/${file}.blade.php`,'const followupReceiptBaseUrl =','// Function to view receipt');
    const context=vm.createContext({encodeURIComponent}); vm.runInContext(code,context);
    assert.equal(vm.runInContext("followupReceiptUrl('followups/receipt name.pdf')",context),'/admin/followups/files/receipt%20name.pdf');
}
const vendorContext=vm.createContext({encodeURIComponent});
vm.runInContext(extract('resources/views/admin/account/vendors/vendor-payments.blade.php','function getReceiptUrl(', 'function attachReceiptViewHandlers('),vendorContext);
for(const [input,expected] of [['followups/client.pdf','/admin/followups/files/client.pdf'],['/storage/followups/client.pdf','/admin/followups/files/client.pdf'],['vendor-payment-receipts/vendor.pdf','/storage/vendor-payment-receipts/vendor.pdf'],['/storage/vendor-payment-receipts/vendor.pdf','/storage/vendor-payment-receipts/vendor.pdf']]) {
    assert.equal(vm.runInContext(`getReceiptUrl(${JSON.stringify(input)})`,vendorContext),expected);
}
(async()=>{
    const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try {
        for(const width of [1440,1024,768,390,320]) {
            const page=await browser.newPage({viewport:{width,height:950}});
            await page.route('**/*',route=> {
                const pathname=new URL(route.request().url()).pathname;
                const file=pathname.startsWith('/assets/') ? path.join(root,'public',pathname) : path.join(dir,'index.html');
                return fs.existsSync(file) ? route.fulfill({path:file}) : route.abort();
            });
            await page.goto('http://crm.test/admin/rides/ride-status');
            const timeline=page.locator('.rs-timeline');
            assert.equal(await timeline.evaluate(e=>e.open),false);
            assert.equal(await timeline.locator('ol').isVisible(),false);
            const positions=await page.locator('.rs-actions > :is(a,button)').evaluateAll(items=>items.map(e=>{const r=e.getBoundingClientRect();return {top:r.top,bottom:r.bottom,right:r.right};}));
            assert.equal(positions.length,2);
            assert.ok(Math.abs(positions[0].top-positions[1].top)<1,`${width}px action buttons must share a line`);
            assert.ok(positions.every(p=>p.right<=width),`${width}px action buttons must fit`);
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
            await timeline.locator('summary').click(); assert.equal(await timeline.locator('ol').isVisible(),true);
            await timeline.locator('summary').click(); assert.equal(await timeline.locator('ol').isVisible(),false);
            await page.screenshot({path:path.join(dir,`ride-status-${width}.png`)}); await page.close();
            console.log(`PASS ${width}px: timeline collapsed/toggle, single-line actions and no overflow`);
        }
    } finally {await browser.close();}
    console.log('PASS same-origin protected receipt URLs and vendor preview normalization.');
})().catch(e=>{console.error(e);process.exitCode=1;});
