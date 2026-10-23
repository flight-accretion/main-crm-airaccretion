const fs=require('node:fs'), path=require('node:path'), assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'C:/Users/reser/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'../..'), directory=process.argv[2];
if(!directory) throw new Error('Pass the synthetic vendor fixture directory.');
const deps=process.env.VENDOR_DEPS_DIR || directory;
const records=JSON.parse(fs.readFileSync(path.join(directory,'records.json'),'utf8'));
(async()=>{
    const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try {
        for(const width of [1440,1024,768,390,320]) {
            const page=await browser.newPage({viewport:{width,height:950}}), errors=[], writes=[];
            let failRead=false;
            page.on('pageerror',e=>errors.push(e.message));
            page.on('dialog',d=>d.dismiss());
            await page.route('**/*',async route=>{
                const req=route.request(), url=new URL(req.url()), parts=url.pathname.split('/');
                if(url.pathname.startsWith('/test-deps/')) {
                    const filename=path.basename(url.pathname);
                    const file=filename==='jquery.min.js' && process.env.JQUERY_PATH ? process.env.JQUERY_PATH : path.join(deps,filename);
                    return fs.existsSync(file) ? route.fulfill({path:file}) : route.fulfill({status:204,body:''});
                }
                if(url.pathname.startsWith('/assets/')) {
                    const file=path.join(root,'public',url.pathname);
                    return fs.existsSync(file) ? route.fulfill({path:file}) : route.fulfill({status:204,body:''});
                }
                if(req.method()!=='GET') {
                    writes.push({url:url.pathname,data:req.postData(),contentType:req.headers()['content-type']});
                    if(req.headers()['x-requested-with']==='XMLHttpRequest') return route.fulfill({status:422,contentType:'application/json',body:JSON.stringify({success:false,errors:{name:['Simulated validation error']}})});
                    return route.fulfill({contentType:'text/html',body:fs.readFileSync(path.join(directory,'index.html'),'utf8')});
                }
                if(req.headers()['x-requested-with']==='XMLHttpRequest') {
                    if(failRead) return route.fulfill({status:500,contentType:'application/json',body:'{}'});
                    const record=records[parts.at(-2)]; let data;
                    if(record) data=parts.at(-1)==='view-modal' ? record.view : record.edit;
                    else if(parts.includes('get-service-products')) {const edit=Object.values(records)[0].edit;data={products:edit.allProducts,selected_product_ids:edit.allProducts.map(p=>p.id)};}
                    else if(parts.includes('states')) data={states:Object.values(records)[0].edit.states};
                    else data=Object.values(records)[0].edit.cities;
                    return route.fulfill({contentType:'application/json',body:JSON.stringify(data)});
                }
                if(url.hostname!=='crm.test') return route.fulfill({status:204,body:''});
                return route.fulfill({contentType:'text/html',body:fs.readFileSync(path.join(directory,url.searchParams.get('status')==='0'?'filtered.html':'index.html'),'utf8')});
            });
            await page.goto('http://crm.test/admin/vendors');
            await page.waitForFunction(()=>window.jQuery && jQuery.fn.select2 && window.intlTelInput);
            assert.equal(await page.locator('#filter-section').isVisible(),false);
            assert.equal(await page.locator('.vc-table th').allTextContents().then(t=>t.join('|')),'Vendor|Contact|Email|Location|Status|Actions');
            const row=page.locator('[data-vendor-row]').first(), id=await row.getAttribute('data-vendor-row');
            assert.ok(await page.getByRole('button',{name:'Deactivate',exact:true}).count());
            assert.ok(await page.getByRole('button',{name:'Activate',exact:true}).count());
            assert.equal(await page.getByRole('button',{name:'Follow up',exact:true}).count(),0);
            await page.locator('#toggle-filters').click();
            await page.locator('#vendor-filter-form').waitFor({state:'visible'});
            assert.equal(await page.locator('#toggle-filters').getAttribute('aria-expanded'),'true');
            const fieldPositions=await page.locator('.vc-filter>label').evaluateAll(labels=>labels.map(label=>{
                const text=[...label.childNodes].find(n=>n.nodeType===3 && n.textContent.trim()); const range=document.createRange();range.selectNodeContents(text);
                const title=range.getBoundingClientRect(), field=(label.querySelector('.select2-container') || label.querySelector('input,select')).getBoundingClientRect();
                return {label:label.textContent.trim(),stacked:field.top>=title.bottom,left:Math.abs(field.left-title.left),height:field.height};
            }));
            assert.ok(fieldPositions.every(p=>p.stacked && p.left<2 && p.height>=40),JSON.stringify(fieldPositions));
            await page.screenshot({path:path.join(directory,`vendor-filters-${width}.png`)});
            await page.locator('#vendor-filter-form [name="vendor_name"]').fill('Partner');
            await page.locator('#vendor-filter-form [name="status"]').selectOption('0');
            await Promise.all([page.waitForURL(u=>u.searchParams.get('status')==='0'),page.getByRole('button',{name:'Apply filters',exact:true}).click()]);
            assert.equal(await page.locator('[data-vendor-row]').count(),1);
            assert.equal(await page.locator('#filter-section').isVisible(),false,'Filters stay collapsed after reload');
            await page.locator('#toggle-filters').click();
            assert.equal(await page.locator('#vendor-filter-form [name="status"]').inputValue(),'0');
            await page.getByRole('button',{name:'Reset filters',exact:true}).click();
            await page.waitForURL('http://crm.test/admin/vendors');
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`${width}px vendor list overflow`);
            await page.screenshot({path:path.join(directory,`vendors-${width}.png`)});
            await row.locator('.open-vendor-modal').click();
            await page.locator('#cancel-vendor-toggle').click();
            assert.equal(writes.length,0);
            await row.locator('.open-vendor-modal').click();
            await Promise.all([page.waitForRequest(r=>r.method()==='POST'),page.locator('#confirm-vendor-toggle').click()]);
            assert.equal(writes.at(-1).url,`/admin/vendors/toggle-status/${id}`);
            assert.equal(new URLSearchParams(writes.at(-1).data).get('_method'),'PATCH');
            await page.waitForLoadState();
            await row.locator('.view-vendor-btn').click();
            await page.waitForFunction(name=>document.querySelector('#view_name').textContent===name,records[id].view.vendor.name);
            await page.locator('#view-vendor').waitFor({state:'visible'});
            assert.ok((await page.locator('#view_products').innerText()).includes('Aviation services'));
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`${width}px view overflow`);
            await page.locator('#view-vendor [data-hs-overlay]').click();
            await page.locator('#view-vendor').waitFor({state:'hidden'});
            failRead=true;
            await row.locator('.view-vendor-btn').click();
            await page.locator('#view_name').getByText('Unable to load this vendor. Please reopen it.',{exact:true}).waitFor();
            assert.equal(await page.locator('#view_email').innerText(),'');
            await page.locator('#view-vendor [data-hs-overlay]').click();
            await row.locator('.edit-vendor-btn').click();
            await page.locator('#edit-vendor .vendor-load-error').getByText('Unable to load this vendor. Please reopen it.',{exact:true}).waitFor();
            assert.equal(await page.locator('#edit-vendor-form button[type="submit"]').isDisabled(),true);
            await page.locator('#edit-vendor [data-hs-overlay]').click();
            failRead=false;
            await row.locator('.edit-vendor-btn').click();
            await page.waitForFunction(id=>document.querySelector('#edit-vendor-form').action.endsWith(id),id);
            await page.locator('#edit-vendor').waitFor({state:'visible'});
            assert.equal(await page.locator('#edit_email').inputValue(),records[id].edit.vendor.email);
            assert.deepEqual(await page.locator('#edit_service_ids').evaluate(e=>[...e.selectedOptions].map(o=>o.value)),records[id].edit.vendor.service_ids);
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`${width}px edit overflow`);
            await page.screenshot({path:path.join(directory,`vendor-edit-${width}.png`)});
            await page.locator('#edit_name').fill('Updated vendor');
            await page.locator('#edit-vendor-form button[type="submit"]').click();
            await page.getByText('Simulated validation error',{exact:true}).waitFor();
            assert.equal(writes.at(-1).url,`/admin/vendors/${id}`);
            assert.ok(writes.at(-1).data.includes('Updated vendor'));
            await page.locator('#edit-vendor [data-hs-overlay]').click();
            await page.locator('[data-vendor-add]').click();
            await page.locator('#add-vendor-form').waitFor({state:'visible'});
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`${width}px add overflow`);
            await page.locator('#add-vendor-form-element [name="name"]').fill('New vendor');
            await page.locator('#add-vendor-form-element [name="email"]').fill('new@example.test');
            await page.locator('#add_contact_number').fill('9123456789');
            await page.locator('#add_extra_service_ids').selectOption(records[id].edit.allExtraServices[0].id,{force:true});
            await Promise.all([page.waitForRequest(r=>r.method()==='POST' && new URL(r.url()).pathname==='/admin/vendors'),page.locator('#add-vendor-form-element button[type="submit"]').click()]);
            assert.ok(writes.at(-1).data.includes('New vendor'));
            assert.ok(writes.at(-1).data.includes('+91') || writes.at(-1).data.includes('%2B91'));
            assert.deepEqual(errors,[]);
            await page.close(); console.log(`PASS ${width}px: columns, filters/reset, status target/cancel, view/edit, edit validation and no overflow`);
        }
    } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
