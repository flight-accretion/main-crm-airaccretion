const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'C:/Users/reser/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root = path.resolve(__dirname, '../..'), directory = process.argv[2];
if (!directory) throw new Error('Pass the synthetic services fixture directory.');
const details = JSON.parse(fs.readFileSync(path.join(directory, 'details.json'), 'utf8'));
(async () => {
    const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try {
        for (const width of (process.env.SERVICES_WIDTHS ? process.env.SERVICES_WIDTHS.split(',').map(Number) : [1440, 1024, 768, 390, 320])) {
            const page = await browser.newPage({viewport: {width, height: 950}}), errors = [], writes = [];
            let failView = false, failWrite = false;
            page.on('pageerror', error => errors.push(error.message));
            page.on('requestfailed', req => console.log('Request failed', req.method(), req.url(), req.failure()));
            await page.route('**/*', async route => {
                const req = route.request(), url = new URL(req.url());
                if (url.pathname.startsWith('/assets/')) {
                    const asset = path.join(root, 'public', url.pathname);
                    return fs.existsSync(asset) ? route.fulfill({path: asset}) : route.abort();
                }
                if (req.method() !== 'GET') {
                    writes.push({url: url.pathname, data: new URLSearchParams(req.postData() || '')});
                    return route.fulfill({status: failWrite ? 422 : 200, contentType: 'application/json', body: JSON.stringify(failWrite ? {message: 'Simulated validation failure'} : {success: true, new_status: false})});
                }
                const id = url.pathname.split('/').pop();
                if (details[id] && req.headers()['x-requested-with'] === 'XMLHttpRequest') {
                    return route.fulfill({status: failView ? 500 : 200, contentType: 'application/json', body: JSON.stringify(failView ? {message: 'Unavailable'} : details[id])});
                }
                const file = url.pathname.endsWith('/create') ? 'create.html' : (url.pathname.endsWith('/edit') ? 'edit.html' : 'index.html');
                return route.fulfill({contentType: 'text/html', body: fs.readFileSync(path.join(directory, file), 'utf8')});
            });
            await page.goto('http://crm.test/admin/services');
            assert.equal(await page.locator('[data-service-row]:visible').count(), 10);
            assert.equal(await page.locator('.sc-filters').evaluate(e => e.open), false);
            await page.locator('.sc-filters summary').click();
            assert.equal(await page.locator('[name="product_id"]').isVisible(), true);
            const filterFields=await page.locator('.sc-filters form>label').evaluateAll(labels=>labels.map(label=>{
                const text=[...label.childNodes].find(n=>n.nodeType===3 && n.textContent.trim());const range=document.createRange();range.selectNodeContents(text);
                const title=range.getBoundingClientRect(),field=label.querySelector('input,select').getBoundingClientRect();
                return {stacked:field.top>=title.bottom,left:Math.abs(title.left-field.left),height:field.height};
            }));
            assert.ok(filterFields.every(p=>p.stacked && p.left<2 && p.height>=40),JSON.stringify(filterFields));
            await page.screenshot({path:path.join(directory,`service-filters-${width}.png`)});
            assert.equal(await page.locator('.sc-chips').count(), 0);
            await page.locator('.sc-filters summary').click();
            await page.locator('[data-service-next]').click();
            assert.equal(await page.locator('[data-service-row]:visible').count(), 10);
            await page.locator('[data-service-next]').click();
            assert.equal(await page.locator('[data-service-row]:visible').count(), 8);
            await page.locator('[data-service-prev]').click();
            await page.locator('[data-service-prev]').click();
            assert.ok(await page.getByRole('button', {name: 'Deactivate', exact: true}).count());
            assert.ok(await page.locator('[data-service-toggle][data-status="0"]').count());
            const row = page.locator('[data-service-row]').first(), id = await row.getAttribute('data-service-row');
            await row.locator('[data-service-view]').click();
            await page.locator('#service-view-dialog .sc-details').waitFor();
            assert.ok((await page.locator('#service-view-dialog h3').innerText()).includes('Gangtok'));
            assert.ok((await page.locator('#service-view-dialog').innerText()).includes('Airport pickup'));
            await page.keyboard.press('Escape');
            assert.equal(await page.locator('#service-view-dialog').evaluate(e => e.open), false);
            failView = true;
            await row.locator('[data-service-view]').click();
            await page.getByText('Unable to load this service. Please try again.', {exact: true}).waitFor();
            assert.equal(await page.locator('#service-view-dialog .sc-details').count(), 0, 'Failure cannot retain another record');
            await page.locator('#service-view-dialog [data-service-close]').click(); failView = false;
            await row.locator('[data-service-toggle]').click();
            await page.locator('#service-confirm-dialog [data-service-close]').last().click();
            assert.equal(writes.length, 0, 'Cancel must not send a mutation');
            failWrite = true;
            await row.locator('[data-service-toggle]').click();
            await page.locator('[data-service-confirm]').click();
            await page.getByText('Simulated validation failure', {exact: true}).waitFor();
            assert.equal(writes[0].data.get('_method'), 'PATCH');
            assert.ok(writes[0].url.endsWith(id));
            await page.keyboard.press('Escape'); failWrite = false;
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${width}px list overflow`);
            await page.screenshot({path: path.join(directory, `services-${width}.png`), fullPage: width === 390});

            await page.goto('http://crm.test/admin/services/create');
            await page.locator('#service-name').fill('Gangtok To Bagdogra By Helicopter - 8 People');
            const product = await page.locator('#product_id option').nth(1).getAttribute('value');
            await page.locator('#product_id').selectOption(product);
            await page.locator('#service_amount_input').fill('118000');
            await page.locator('#fees_percent_input').fill('18');
            await page.getByRole('checkbox', {name: /Airport pickup/}).check();
            await page.getByRole('checkbox', {name: /Priority boarding/}).check();
            assert.ok((await page.locator('[data-service-preview-net]').innerText()).includes('96,760.00'));
            assert.ok((await page.locator('[data-service-preview-total]').innerText()).includes('1,22,700.00'));
            await page.locator('[data-service-extra-search]').fill('not found');
            assert.equal(await page.locator('[data-service-extra-option]:visible').count(), 0);
            assert.equal(await page.locator('[name="extra_service_ids[]"]:checked').count(), 2);
            await page.locator('[data-service-extra-search]').fill('');
            await page.locator('.ql-editor').fill('Existing terms retained');
            await page.locator('#service-email-note').fill('Bring photo ID');
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${width}px form overflow`);
            if (width <= 767) assert.equal(await page.locator('.sc-preview').evaluate(e => getComputedStyle(e).position), 'static');
            await page.screenshot({path: path.join(directory, `service-add-${width}.png`), fullPage: width === 390});
            const invalid = await page.locator('#serviceForm').evaluate(form => [...form.querySelectorAll(':invalid')].map(e => ({name: e.name, value: e.value, message: e.validationMessage})));
            assert.deepEqual(invalid, [], 'Filled service form must be valid');
            await page.locator('#serviceForm').evaluate(form => {
                form.dataset.submitSeen = '0';
                form.addEventListener('submit', event => { form.dataset.submitSeen = String(Number(form.dataset.submitSeen) + 1); form.dataset.submitPrevented = String(event.defaultPrevented); });
                form.addEventListener('click', event => { if (event.target.closest('button[type="submit"]')) form.dataset.saveClicked = '1'; });
            });
            try {
                await Promise.all([page.waitForRequest(req => req.method() === 'POST' && new URL(req.url()).pathname === '/admin/services'), page.getByRole('button', {name: 'Save service', exact: true}).click()]);
            } catch (error) {
                console.log('Submission diagnostics', {width, url: page.url(), writes: writes.map(w => w.url), errors});
                console.log(await page.locator('#serviceForm').evaluate(form => ({action: form.action, valid: form.checkValidity(), diagnostics: {...form.dataset}, focused: document.activeElement?.outerHTML, buttons: [...form.querySelectorAll('button[type="submit"]')].map(e => ({text: e.textContent, owner: e.form?.id, disabled: e.disabled}))})));
                await page.screenshot({path: path.join(directory, `submission-failure-${width}.png`), fullPage: true});
                throw error;
            }
            const submitted = writes.at(-1).data;
            assert.equal(submitted.get('service_amount'), '118000');
            assert.equal(submitted.get('fees_percent'), '18');
            assert.equal(submitted.getAll('extra_service_ids[]').length, 2);
            assert.equal(submitted.get('booking_email_note'), 'Bring photo ID');
            assert.ok(submitted.get('terms_and_conditions').includes('Existing terms retained'));
            await page.goto(`http://crm.test/admin/services/${id}/edit?focus=price`);
            assert.equal(await page.locator('[name="extra_service_ids[]"]:checked').count(), 2, 'Edit must retain linked extras');
            assert.equal(await page.locator('#product_id').inputValue(), product, 'Edit must retain selected product');
            assert.ok((await page.locator('.ql-editor').innerText()).includes('Existing weather terms'));
            assert.equal(errors.length, 0, errors.join('\n'));
            await page.close();
            console.log(`PASS ${width}px: popup/load failure/close, pagination, action identity/cancel/error, pricing, add-on search, rich-text submission and no overflow`);
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
