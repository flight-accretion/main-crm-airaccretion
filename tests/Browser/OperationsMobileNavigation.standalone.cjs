const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const {chromium} = require('C:/Users/reser/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root = path.resolve(__dirname, '../..'), directory = process.argv[2];
if (!directory) throw new Error('Pass the operations-mobile fixture directory.');
(async () => {
    const browser = await chromium.launch({headless:true, executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try {
        for (const file of fs.readdirSync(directory).filter(name => name.endsWith('.html'))) {
            const page = await browser.newPage({viewport:{width:390,height:844}}), errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('**/*', route => {
                const url = new URL(route.request().url());
                if (url.pathname.startsWith('/assets/')) {
                    const asset = path.join(root, 'public', url.pathname);
                    return fs.existsSync(asset) ? route.fulfill({path:asset}) : route.abort();
                }
                return route.fulfill({contentType:'text/html', body:fs.readFileSync(path.join(directory,file),'utf8')});
            });
            await page.goto('http://ops-nav.test/admin/operations/queue/review');
            await page.addStyleTag({url:'http://ops-nav.test/assets/admin/css/crm/lead-detail.css'});
            await page.evaluate(() => {
                const actions=document.createElement('div');actions.className='ld-mobile-actions';
                actions.innerHTML='<a href="tel:123">Call</a><a href="https://wa.me/123">WhatsApp</a>';
                document.body.append(actions);
            });
            const nav = page.locator('.operations-mobile-nav'), panel = page.locator('#operations-mobile-more');
            for (const width of [390,320,767]) {
                await page.setViewportSize({width,height:844});
                await nav.waitFor({state:'visible'});
                const box = await nav.boundingBox();
                assert(Math.abs(box.y + box.height - 844) < 1, 'Navigation must remain at viewport bottom');
                const actions=await page.locator('.ld-mobile-actions').boundingBox();
                assert(actions.y+actions.height<=box.y+1,'Call/WhatsApp actions must not overlap bottom tabs');
                assert.equal(await page.locator('.kt-mobile-nav').isVisible(),false,'Do not stack two navigation bars');
                assert.equal(await page.locator('.hr-mobile-nav').isVisible(),false);
                assert.equal(await page.locator('.target-mobile-nav').isVisible(),false);
                assert.equal(await page.locator('.sidemenu-toggle').isVisible(),false,'Sidebar toggle must be hidden for every role');
                const sources = await page.locator('.app-sidebar .main-menu a[href]').evaluateAll(items => items.map(item => item.href));
                for (const href of await nav.locator('a:visible').evaluateAll(items => items.map(item => item.href))) assert(sources.includes(href),'Unauthorized primary link');
                assert.equal(await nav.locator('[aria-current="page"]').count(),1);
                const toggle = nav.locator('[data-toggle-operations-more]');
                await toggle.click();
                assert.equal(await panel.isVisible(),true);
                assert.equal(await toggle.getAttribute('aria-expanded'),'true');
                const moreLinks = await panel.locator('a[href]').evaluateAll(items => items.map(item => item.href));
                assert.deepEqual([...new Set(moreLinks)].sort(),[...new Set(sources)].sort(),'More must match sidebar permissions');
                const panelBox = await panel.boundingBox();
                assert(panelBox.y >= 0 && panelBox.y + panelBox.height < box.y, 'Popup overlaps navigation or leaves screen');
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth),false);
                if (width === 390) await page.screenshot({path:path.join(directory,`${file}-more-mobile.png`)});
                await page.keyboard.press('Escape');
                assert.equal(await panel.isVisible(),false);
                assert.equal(await toggle.evaluate(el => el === document.activeElement),true);
                await toggle.click(); await panel.locator('[data-close-operations-more]').click();
                assert.equal(await panel.isVisible(),false);
                await toggle.click(); await page.locator('#outside').click();
                assert.equal(await panel.isVisible(),false);
            }
            await nav.locator('[data-toggle-operations-more]').click();
            await page.setViewportSize({width:1440,height:1000});
            assert.equal(await nav.isVisible(),false);
            assert.equal(await panel.isVisible(),false);
            assert.equal(await page.locator('.sidemenu-toggle').isVisible(),true,'Desktop navigation must remain unchanged');
            assert.deepEqual(errors,[]);
            console.log(`PASS ${file}: mobile bottom tabs, More permissions, close/Escape/outside click, responsive and desktop`);
            await page.close();
        }
    } finally { await browser.close(); }
})().catch(error => {console.error(error);process.exitCode=1;});
