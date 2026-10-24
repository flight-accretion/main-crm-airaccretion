const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const {chromium} = require('C:/Users/reser/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root = path.resolve(__dirname, '../..'), directory = process.argv[2];
(async () => {
    const browser = await chromium.launch({headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try {
        for (const role of ['operations-executive', 'operations-manager', 'senior-operations-manager', 'super', 'accounts-executive', 'accounts-manager', 'senior-accounts-manager', 'hr', 'sales-executive']) {
            const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
            const errors = []; page.on('pageerror', error => errors.push(error.message));
            await page.route('**/*', route => {
                const url = new URL(route.request().url());
                if (url.pathname.startsWith('/assets/')) return route.fulfill({path: path.join(root, 'public', url.pathname)});
                return route.fulfill({contentType: 'text/html', body: fs.readFileSync(path.join(directory, role + '-operations.html'), 'utf8')});
            });
            await page.goto('http://ops-nav.test/admin/operations/queue/review');
            const expected = role.includes('operations') ? {customer_refund: '12', vendor_refund: '3', ...(role === 'operations-executive' ? {} : {payment_approval: '25'})}
                : role === 'super' ? {customer_refund: '12', vendor_refund: '3', account_vendor_refund: '4', payment_approval: '25', invoice: '7'}
                : role.includes('accounts') ? {customer_refund: '12', account_vendor_refund: '4', payment_approval: '25', invoice: '7'} : {};
            const counts = selector => page.locator(selector + ' [data-pending-queue]').evaluateAll(items => Object.fromEntries(items.map(item => [item.dataset.pendingQueue, item.textContent.trim()])));
            const parent = role.includes('operations') || role === 'super' ? {refund_queue: '15'} : {};
            assert.deepEqual(await counts('.main-menu'), {...expected, ...parent}, 'Desktop badges ' + role);
            await page.locator('.main-menu details').evaluateAll(items => items.forEach(item => item.open = true));
            assert.equal(await page.locator('.main-menu [data-pending-queue]').evaluateAll(items => items.every(item => {
                const badge = item.getBoundingClientRect(), link = item.parentElement.getBoundingClientRect();
                return badge.width >= 22 && badge.right <= link.right && badge.left >= link.left;
            })), true, 'Desktop badges stay inside links');
            const labels = await page.evaluate(() => {
                const read = items => items.flatMap(item => [item.label, ...(item.children ? read(item.children) : [])]);
                return read(window.CrmNavigation.tree());
            });
            assert(!labels.some(label => /Refund\s*\d|Review\s*\d|Invoices\s*\d/.test(label)), 'Counts must not become menu labels');
            for (const width of [320, 390, 767]) {
                await page.setViewportSize({width, height: 844});
                await page.locator('[data-toggle-operations-more]').click();
                const panel = '#operations-mobile-more';
                assert.deepEqual(await counts(panel), {...expected, ...parent}, 'Mobile counts ' + role + '/' + width);
                await page.locator(panel + ' details').evaluateAll(items => items.forEach(item => item.open = true));
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'No horizontal overflow');
                assert.equal(await page.locator(panel + ' [data-pending-queue]').evaluateAll(items => items.every(item => {
                    const badge = item.getBoundingClientRect(), link = item.parentElement.getBoundingClientRect();
                    return badge.width >= 22 && badge.right <= link.right && badge.left >= link.left;
                })), true, 'Badges stay inside links');
                if (role === 'operations-manager' && width === 390) {
                    await page.locator(panel + ' [data-pending-queue="payment_approval"]').scrollIntoViewIfNeeded();
                    await page.screenshot({path: path.join(directory, 'pending-counts-mobile.png')});
                }
                await page.keyboard.press('Escape');
            }
            if (role === 'super') {
                await page.setViewportSize({width: 1440, height: 1000});
                const picker = page.locator('.app-sidebar [data-sidebar-workspace]');
                await picker.selectOption('operations');
                assert.deepEqual(await counts('.main-menu'), {customer_refund: '12', vendor_refund: '3', refund_queue: '15', payment_approval: '25'});
                await picker.selectOption('all');
                assert.deepEqual(await counts('.main-menu'), {...expected, ...parent}, 'Counts survive workspace switch');
            }
            assert.deepEqual(errors, []); console.log('PASS pending badges desktop/mobile ' + role);
            await page.close();
        }
    } finally { await browser.close(); }
})().catch(error => {console.error(error); process.exitCode = 1;});
