const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const {chromium} = require('C:/Users/reser/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root = path.resolve(__dirname, '../..'), dir = process.argv[2];
(async () => {
    const browser = await chromium.launch({headless:true, executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try {
        for (const fixture of ['index', 'empty', 'accounts-0', 'accounts-1', 'accounts-2']) {
            if (!fs.existsSync(path.join(dir, fixture+'.html'))) continue;
            const page = await browser.newPage(), errors = [], writes = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('**/*', route => {
                const req = route.request(), url = new URL(req.url());
                if (req.method() !== 'GET') { writes.push(url.pathname); return route.abort(); }
                if (url.pathname.startsWith('/assets/')) {
                    const file = path.join(root,'public',url.pathname);
                    return fs.existsSync(file) ? route.fulfill({path:file}) : route.fulfill({status:204});
                }
                if (req.resourceType() !== 'document') return route.fulfill({status:204});
                const file = url.searchParams.get('month') === '2026-08' && !fixture.startsWith('accounts-') ? 'previous' : fixture;
                const html = fs.readFileSync(path.join(dir,file+'.html'),'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'')
                    .replace('</head>','<style>#loader{display:none!important}</style></head>')
                    .replace('</body>','<script src="/assets/admin/js/crm/navigation.js"></script><script src="/assets/admin/js/crm/operations-mobile-nav.js"></script></body>');
                return route.fulfill({contentType:'text/html',body:html});
            });
            await page.goto('http://accounts.test/admin/account/dashboard');
            assert.equal(await page.locator('.ah-tile').count(),8);
            assert.equal(await page.locator('.ah-tile h3').nth(4).innerText(),'Invoice pending');
            assert((await page.locator('.ah-tile h3').allTextContents()).includes('Approvals within 10 minutes'));
            assert.equal(await page.locator('.ah-chart-day').count(),14);
            for (const width of [1920,1440,1280,1024,768,390,320]) {
                await page.setViewportSize({width,height:1000});
                for (const dark of [false,true]) {
                    await page.evaluate(value => document.documentElement.classList.toggle('dark',value),dark);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth),false,fixture+' overflow '+width);
                    const overflow = await page.locator('.ah-dashboard').evaluate(main => Array.from(main.querySelectorAll('h1,h2,h3,p,strong')).filter(el => el.scrollWidth > el.clientWidth+2).map(el => el.textContent));
                    assert.deepEqual(overflow,[],fixture+' clipped text '+width);
                    if (!dark && [1440,390].includes(width)) await page.screenshot({path:path.join(dir,fixture+'-'+width+'.png'),fullPage:true});
                }
                if (fixture.startsWith('accounts-') && width < 768) {
                    assert.equal(await page.locator('.sidemenu-toggle').isVisible(),false);
                    await page.locator('[data-toggle-operations-more]').click();
                    const links = await page.locator('#operations-mobile-more a').evaluateAll(items => items.map(el => new URL(el.href).pathname));
                    assert.equal(links.filter(url => url === '/admin/account/dashboard').length,1,'Dashboard appears once in More');
                    await page.keyboard.press('Escape');
                }
            }
            await page.locator('.ah-chart-day').nth(6).focus();
            assert.equal(await page.locator('.ah-chart-day').nth(6).locator('.ah-chart-value').evaluate(el => getComputedStyle(el).visibility),'visible','Chart value available by keyboard');
            if (fixture === 'index') {
                await page.locator('#accounts-month').fill('2026-08');
                await page.getByRole('button',{name:'Apply month'}).click();
                await page.waitForURL('**/admin/account/dashboard?month=2026-08');
                assert.equal(await page.locator('#accounts-month').inputValue(),'2026-08');
            }
            assert.deepEqual(errors,[]); assert.deepEqual(writes,[]);
            console.log('PASS Accounts dashboard '+fixture+': eight cards, month filter, responsive/light/dark, accessible chart, read-only');
            await page.close();
        }
    } finally { await browser.close(); }
})().catch(error => {console.error(error);process.exitCode=1;});
