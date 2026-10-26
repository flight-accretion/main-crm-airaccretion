const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const {chromium} = require('C:/Users/reser/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root = path.resolve(__dirname, '../..'), directory = process.argv[2];
(async () => {
    const browser = await chromium.launch({headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try {
        for (const role of [0, 1, 2]) {
            const page = await browser.newPage(), errors = [], writes = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('**/*', route => {
                const request = route.request(), url = new URL(request.url());
                if (request.method() !== 'GET') { writes.push(url.pathname); return route.abort(); }
                if (url.pathname.startsWith('/assets/')) {
                    const file = path.join(root, 'public', url.pathname);
                    return fs.existsSync(file) ? route.fulfill({path: file}) : route.fulfill({status: 204});
                }
                if (request.resourceType() !== 'document') return route.fulfill({status: 204});
                const html = fs.readFileSync(path.join(directory, 'accounts-' + role + '.html'), 'utf8')
                    .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '')
                    .replace('</head>', '<style>#loader{display:none!important}</style></head>')
                    .replace('</body>', '<script src="/assets/admin/js/crm/navigation.js"></script><script src="/assets/admin/js/crm/operations-mobile-nav.js"></script></body>');
                return route.fulfill({contentType: 'text/html', body: html});
            });
            await page.goto('http://invoice-shell.test/admin/account/invoices?status=all');
            for (const width of [1920, 1440, 1024, 390, 320]) {
                await page.setViewportSize({width, height: 1000});
                await page.locator('#toggle-filters').evaluate(button => {
                    const panel = document.getElementById('filter-section'); panel.style.display = 'block';
                    button.setAttribute('aria-expanded', 'true');
                });
                for (const dark of [false, true]) {
                    await page.evaluate(value => document.documentElement.classList.toggle('dark', value), dark);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'Accounts shell overflow ' + role + '/' + width);
                }
                if (width < 768) {
                    assert.equal(await page.locator('.sidemenu-toggle').isVisible(), false);
                    assert.equal(await page.locator('.operations-mobile-nav').isVisible(), true);
                    assert.deepEqual(await page.locator('.operations-mobile-nav a span').allTextContents(), ['Payments', 'Vendors', 'Rides']);
                    await page.locator('[data-toggle-operations-more]').click();
                    const links = await page.locator('#operations-mobile-more a').evaluateAll(items => items.map(item => new URL(item.href).pathname));
                    assert(links.includes('/admin/account/invoices'), 'Accounts More includes Invoices');
                    const allowed = await page.locator('.main-menu a[href]').evaluateAll(items => items.map(item => new URL(item.href).pathname));
                    assert(links.every(link => allowed.includes(link)), 'More preserves Accounts permissions');
                    await page.keyboard.press('Escape');
                }
                if (role === 2 && [1440, 390].includes(width)) await page.screenshot({path: path.join(directory, 'accounts-shell-' + width + '.png'), fullPage: true});
            }
            assert.deepEqual(errors, []); assert.deepEqual(writes, []);
            console.log('PASS invoice full shell, Accounts bottom tabs and authorized More, five widths, light/dark role ' + role);
            await page.close();
        }
    } finally { await browser.close(); }
})().catch(error => {console.error(error); process.exitCode = 1;});
