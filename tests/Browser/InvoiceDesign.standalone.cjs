const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const {chromium} = require('C:/Users/reser/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root = path.resolve(__dirname, '../..'), directory = process.argv[2];
const details = JSON.parse(fs.readFileSync(path.join(directory, 'details.json')));
const rows = JSON.parse(fs.readFileSync(path.join(directory, 'rows.json')));
assert.equal(details.success, true);
(async () => {
    const browser = await chromium.launch({headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try {
        for (const width of (process.env.CRM_VIEWPORT ? [Number(process.env.CRM_VIEWPORT)] : [1920, 1440, 1280, 1024, 768, 390, 320])) {
            const page = await browser.newPage({viewport: {width, height: 1000}}), errors = [], writes = [];
            page.on('pageerror', error => errors.push(error.message));
            let nextFailure = false, finalizeSuccess = false;
            await page.route('**/*', async route => {
                const request = route.request(), url = new URL(request.url());
                if (url.pathname === '/test-jquery.js') return route.fulfill({path: path.join(directory, 'jquery-3.6.1.min.js')});
                if (url.pathname.startsWith('/assets/')) {
                    const file = path.join(root, 'public', url.pathname);
                    return fs.existsSync(file) ? route.fulfill({path: file}) : route.fulfill({status: 204});
                }
                if (request.method() !== 'GET') {
                    writes.push({url: url.pathname, method: request.method(), body: request.postDataJSON()});
                    if (url.pathname.endsWith('/finalize')) return finalizeSuccess
                        ? route.fulfill({json: {success: true, message: 'Invoice finalized successfully.'}})
                        : route.fulfill({status: 422, json: {success: false, message: 'Test validation error'}});
                    return route.fulfill({json: {success: true, message: 'Saved in test transport only'}});
                }
                if (request.resourceType() === 'document') {
                    const html = fs.readFileSync(path.join(directory, url.searchParams.has('empty') ? 'empty.html' : 'index.html'), 'utf8').replace('</head>', '<script src="/test-jquery.js"></script><script src="/assets/admin/libs/preline/preline.js"></script></head>');
                    return route.fulfill({contentType: 'text/html', body: html});
                }
                if (request.resourceType() === 'fetch') {
                    if (nextFailure) { nextFailure = false; return route.fulfill({status: 500, json: {success: false}}); }
                    const response = structuredClone(details);
                    const row = rows.find(row => url.pathname.endsWith('/' + row.id));
                    response.data.voucher.id = row.id;
                    if (row.invoice_status === 2) response.data.existingInvoice.status = 2;
                    else if (!row.existing_invoice) response.data.existingInvoice = null;
                    return route.fulfill({json: response});
                }
                return route.fulfill({status: 204});
            });
            await page.goto('http://invoice.test/admin/account/invoices?status=all');
            await page.waitForFunction(() => Boolean(window.HSOverlay));
            assert.equal(await page.locator('.ai-invoice-row').count(), 4);
            assert.equal(await page.locator('#status').inputValue(), 'all', 'Status filter retains current queue');
            assert.equal(await page.locator('#invoice-rows').inputValue(), '10');
            assert.deepEqual(await page.locator('#invoice-rows option').allTextContents(), ['10', '25', '50', '100']);
            assert.equal(await page.locator('.ai-table th').allTextContents().then(labels => labels.includes('State')), false);
            assert.equal(await page.locator('#filter-section').isVisible(), false);
            await page.locator('#toggle-filters').click();
            assert.equal(await page.locator('#filter-section').isVisible(), true);
            for (const dark of [false, true]) {
                await page.evaluate(value => document.documentElement.classList.toggle('dark', value), dark);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'No horizontal page scroll ' + width);
                assert.equal(await page.locator('.ai-field').evaluateAll(fields => fields.every(field => {
                    const label = field.querySelector('span').getBoundingClientRect(), input = field.querySelector('input,select').getBoundingClientRect();
                    return input.top >= label.bottom && Math.abs(input.left - label.left) < 1;
                })), true, 'Filter labels above fields');
                const clipped = await page.locator('.crm-invoices').evaluate(main => Array.from(main.querySelectorAll('th,td,strong,.ai-button')).filter(item => getComputedStyle(item).display !== 'inline' && item.scrollWidth > item.clientWidth + 2).map(item => item.textContent));
                assert.deepEqual(clipped, [], 'No clipped table text ' + width);
            }
            await page.evaluate(() => document.documentElement.classList.remove('dark'));
            const row = page.locator('.ai-invoice-row').filter({has: page.locator('.finalize-invoice-btn')});
            await row.locator('td').nth(1).click();
            await page.waitForFunction(() => !document.getElementById('view-invoice').hasAttribute('aria-busy'));
            await page.locator('#view-invoice').waitFor({state: 'visible'});
            await page.waitForFunction(() => getComputedStyle(document.getElementById('view-invoice')).opacity === '1');
            assert((await page.locator('#view-invoice #client-info').innerText()).includes('Cancelled Customer'));
            for (const heading of ['Client Information', 'Customer GST Info', 'Travel Information', 'Payment History']) assert((await page.locator('#view-invoice').textContent()).includes(heading), heading + ' preserved');
            const popup = await page.locator('.ai-detail-content').boundingBox();
            assert(popup.x >= 0 && popup.x + popup.width <= width + 1 && popup.y >= 0 && popup.y + popup.height <= 1001, 'Centered popup fits ' + width);
            const popupOverflow = await page.locator('.view-invoice-body').evaluate(body => {
                if (body.scrollWidth <= body.clientWidth + 1) return [];
                const right = body.getBoundingClientRect().right;
                return Array.from(body.querySelectorAll('*')).filter(item => item.getBoundingClientRect().right > right + 1).map(item => item.className + ': ' + item.textContent.trim().slice(0, 70));
            });
            assert.deepEqual(popupOverflow, [], 'Popup has no sideways scroll ' + width);
            if ([1440, 390].includes(width)) await page.screenshot({path: path.join(directory, 'invoice-popup-' + width + '.png')});
            await page.locator('#view-invoice [data-hs-overlay="#view-invoice"]').click();
            await page.locator('#view-invoice').waitFor({state: 'hidden'});
            assert.equal(writes.length, 0, 'Row click must never write data');
            await row.locator('.ai-gst-btn').click();
            await page.locator('#gst-edit').waitFor({state: 'visible'});
            await page.locator('#gst-number-input').fill('');
            assert.equal(await page.locator('#gst-number-input').getAttribute('required'), null);
            await page.locator('#gst-save-btn').click();
            await page.waitForFunction(() => document.getElementById('gst-edit').style.display === 'none');
            assert.equal(writes.at(-1).body.gst_number, '');
            await page.locator('#success-message-modal').waitFor({state: 'visible'});
            await page.goto('http://invoice.test/admin/account/invoices?status=all');
            await page.locator('#view-invoice').waitFor({state: 'hidden'});
            await row.locator('.finalize-invoice-btn').click();
            await page.locator('#invoice-finalize-modal').waitFor({state: 'visible'});
            const before = writes.length;
            await page.locator('#invoice-finalize-date').fill('2026-10-12');
            assert.equal(writes.length, before, 'Opening/date selection cannot finalize');
            await page.locator('#invoice-finalize-save').click();
            await page.locator('#invoice-finalize-error').waitFor({state: 'visible'});
            assert.deepEqual(writes.at(-1).body, {finalized_date: '2026-10-12'});
            assert.equal(await page.locator('#invoice-finalize-save').isEnabled(), true);
            await page.locator('#invoice-finalize-modal [data-hs-overlay]').first().click();
            await page.locator('#invoice-finalize-modal').waitFor({state: 'hidden'});
            if (await page.locator('#filter-section').isVisible()) await page.locator('#toggle-filters').click();
            if ([1440, 390].includes(width)) await page.screenshot({path: path.join(directory, 'invoice-queue-' + width + '.png'), fullPage: true});
            const lockedRow = page.locator('.ai-invoice-row').filter({has: page.locator('td', {hasText: 'INV-finalized'})});
            await lockedRow.focus(); await page.keyboard.press('Enter');
            await page.waitForFunction(() => !document.getElementById('view-invoice').hasAttribute('aria-busy'));
            await page.waitForFunction(() => getComputedStyle(document.getElementById('view-invoice')).opacity === '1');
            assert.equal(await page.locator('#edit-gst-btn').isVisible(), false, 'Finalized details remain locked');
            await page.keyboard.press('Escape'); await page.locator('#view-invoice').waitFor({state: 'hidden'});
            if (width === 1440) {
                await row.locator('.finalize-invoice-btn').click();
                finalizeSuccess = true;
                await page.locator('#invoice-finalize-save').click();
                await page.locator('#success-message-modal').waitFor({state: 'visible'});
                assert((await page.locator('#success-message-text').textContent()).includes('Invoice finalized successfully'));
            } else if (width === 1920) {
                await page.locator('.generate-invoice-btn').click();
                await page.locator('#confirmation-modal').waitFor({state: 'visible'});
                await page.locator('#confirm-action-btn').click();
                await page.locator('#success-message-modal').waitFor({state: 'visible'});
                assert(writes.at(-1).url.endsWith('/generate'), 'Generate keeps the existing endpoint');
            } else if (width === 320) {
                await page.goto('http://invoice.test/admin/account/invoices?status=all&empty=1');
                assert.equal(await page.locator('.ai-invoice-row').count(), 0);
                assert.equal(await page.locator('.ai-pagination').count(), 0);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
            }
            assert.deepEqual(errors, []);
            console.log('PASS invoice queue, filters, row popup, optional GST and date dialog ' + width);
            await page.close();
        }
    } finally { await browser.close(); }
})().catch(error => {console.error(error); process.exitCode = 1;});
