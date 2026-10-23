const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'C:/Users/reser/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root = path.resolve(__dirname, '../..'), directory = process.argv[2];
if (!directory) throw new Error('Pass the synthetic address fixture directory.');
const records = JSON.parse(fs.readFileSync(path.join(directory, 'records.json'), 'utf8'));
(async () => {
    const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try {
        for (const width of [1440, 1024, 768, 390, 320]) {
            const page = await browser.newPage({viewport: {width, height: 950}}), errors = [], writes = [];
            let failRead = false;
            page.on('pageerror', e => errors.push(e.message));
            await page.route('**/*', async route => {
                const req = route.request(), url = new URL(req.url()), parts = url.pathname.split('/');
                if (url.pathname.startsWith('/assets/')) {
                    const asset = path.join(root, 'public', url.pathname);
                    return fs.existsSync(asset) ? route.fulfill({path: asset}) : route.abort();
                }
                if (req.method() !== 'GET') {
                    writes.push({method:req.method(), url:url.pathname, data:new URLSearchParams(req.postData() || '')});
                    if (req.method() === 'PATCH') return route.fulfill({status:422, contentType:'application/json', body:JSON.stringify({message:'Simulated status failure'})});
                    return route.fulfill({contentType:'text/html', body:fs.readFileSync(path.join(directory,'index.html'),'utf8')});
                }
                if (req.headers()['x-requested-with'] === 'XMLHttpRequest') {
                    if (failRead) return route.fulfill({status:500, contentType:'application/json', body:'{}'});
                    const record = records[parts.at(-2)];
                    let data;
                    if (record) data = parts.at(-1) === 'view-modal' ? record.view : record.edit;
                    else {
                        const first = Object.values(records)[0].edit.data;
                        data = parts.includes('states') ? {states:first.states} : first.cities;
                    }
                    return route.fulfill({contentType:'application/json', body:JSON.stringify(data)});
                }
                return route.fulfill({contentType:'text/html', body:fs.readFileSync(path.join(directory,'index.html'),'utf8')});
            });
            await page.goto('http://crm.test/admin/service-addresses');
            assert.equal(await page.locator('[data-address-row]').count(), 10);
            const fields=await page.locator('.sa-tools>label').evaluateAll(labels=>labels.map(label=>{
                const text=[...label.childNodes].find(n=>n.nodeType===3 && n.textContent.trim());const range=document.createRange();range.selectNodeContents(text);
                const title=range.getBoundingClientRect(),field=label.querySelector('input,select').getBoundingClientRect();
                return {stacked:field.top>=title.bottom,left:Math.abs(title.left-field.left),height:field.height};
            }));
            assert.ok(fields.every(p=>p.stacked && p.left<2 && p.height>=40),JSON.stringify(fields));
            assert.ok(await page.getByRole('button',{name:'Activate',exact:true}).count());
            assert.ok(await page.getByRole('button',{name:'Deactivate',exact:true}).count());
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${width}px list overflow`);
            await page.screenshot({path:path.join(directory,`addresses-${width}.png`), fullPage:false});
            const row = page.locator('[data-address-row]').first(), id = await row.getAttribute('data-address-row');
            await row.locator('[data-address-view]').click();
            const view = page.locator('[data-address-dialog="view"]');
            await view.locator('.sa-details').waitFor();
            assert.ok((await view.innerText()).includes(records[id].view.serviceAddress.address));
            assert.equal(await view.locator('a').getAttribute('rel'), 'noopener noreferrer');
            await page.keyboard.press('Escape');
            failRead = true;
            await row.locator('[data-address-view]').click();
            await view.getByText('Unable to load address data. Please try again.').waitFor();
            assert.equal(await view.locator('.sa-details').count(),0);
            await page.keyboard.press('Escape');
            await row.locator('[data-address-edit]').click();
            const edit = page.locator('[data-address-form="edit"]');
            await edit.getByText('Unable to load address data. Please try again.').waitFor();
            assert.equal(await edit.locator('[data-save]').isDisabled(),true);
            await page.keyboard.press('Escape'); failRead = false;
            await row.locator('[data-address-status]').click();
            await page.locator('[data-address-dialog="status"] [data-address-close]').last().click();
            assert.equal(writes.length,0,'Cancel must not update data');
            await row.locator('[data-address-status]').click();
            await page.locator('[data-status-confirm]').click();
            await page.getByText('Simulated status failure',{exact:true}).waitFor();
            assert.equal(writes[0].url, `/admin/service-addresses/${id}/toggle-status`);
            await page.keyboard.press('Escape');

            await page.locator('[data-address-add]').click();
            const add = page.locator('[data-address-form="add"]');
            const source = Object.values(records)[0].edit.data;
            await add.locator('[name="contact_person_name"]').fill('New ground manager');
            await add.locator('[name="contact_number"]').fill('9876543210');
            await add.locator('[name="address"]').fill('New airport reporting point');
            await add.locator('[name="product_id"]').selectOption(source.products[0].id);
            await add.locator('[name="service_id"]').selectOption(source.services[0].id);
            await add.locator('[name="country_id"]').selectOption(source.countries[0].id);
            await add.locator('[name="state_id"] option').nth(1).waitFor({state:'attached'});
            await add.locator('[name="state_id"]').selectOption(source.states[0].id);
            await add.locator('[name="city_id"] option').nth(1).waitFor({state:'attached'});
            await add.locator('[name="city_id"]').selectOption(source.cities[0].id);
            assert.equal(await add.evaluate(f=>f.checkValidity()),true);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth),false,`${width}px add popup overflow`);
            await page.screenshot({path:path.join(directory,`address-add-${width}.png`)});
            await Promise.all([page.waitForRequest(r=>r.method()==='POST'), add.locator('[data-save]').click()]);
            assert.equal(writes.at(-1).data.get('country_code'),'+91');
            assert.equal(writes.at(-1).data.get('contact_number'),'9876543210');
            assert.equal(writes.at(-1).data.get('service_id'),source.services[0].id);
            await page.waitForLoadState();
            const editRow=page.locator(`[data-address-row="${id}"]`);
            await editRow.locator('[data-address-edit]').click();
            await edit.locator('fieldset:not([disabled])').waitFor();
            assert.equal(await edit.locator('[name="contact_number"]').inputValue(),'9876543210');
            assert.equal(await edit.locator('[name="contact_country_code"]').inputValue(),'+91');
            assert.equal(await edit.locator('[name="_address_id"]').inputValue(),id);
            assert.equal(await edit.locator('[name="city_id"]').inputValue(),source.cities[0].id);
            await edit.locator('[name="contact_person_name"]').fill('Changed manager');
            await Promise.all([page.waitForRequest(r=>r.method()==='POST'), edit.locator('[data-save]').click()]);
            assert.equal(writes.at(-1).url,`/admin/service-addresses/${id}`);
            assert.equal(writes.at(-1).data.get('_method'),'PUT');
            assert.equal(writes.at(-1).data.get('contact_country_code'),'+91');
            assert.equal(writes.at(-1).data.get('contact_person_name'),'Changed manager');
            assert.deepEqual(errors,[]);
            await page.close(); console.log(`PASS ${width}px: separate columns, view/edit/error/cancel, status target, country/state/city, create/update payloads and overflow`);
        }
    } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1;});
