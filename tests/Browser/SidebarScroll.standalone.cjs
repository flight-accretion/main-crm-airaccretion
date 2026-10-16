const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = require('C:/Users/reser/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const repo = path.resolve(__dirname, '../..');
const fixtures = process.argv[2];
if (!fixtures) throw new Error('Pass the rendered navigation fixture directory.');

(async () => {
    const browser = await chromium.launch({headless:true, executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try {
        for (const role of ['hr', 'super']) for (const height of [600, 768, 900]) for (const simplebar of [false, true]) {
            const page = await browser.newPage({viewport:{width:1366, height}});
            let html = fs.readFileSync(path.join(fixtures, role + '.html'), 'utf8');
            html = html.replace('</head>', '<link rel="stylesheet" href="/assets/admin/css/style.css"><link rel="stylesheet" href="/assets/admin/libs/simplebar/simplebar.min.css"><link rel="stylesheet" href="/assets/admin/css/crm/navigation.css"></head>');
            await page.route('**/*', route => {
                const url = new URL(route.request().url());
                return url.pathname.startsWith('/assets/') ? route.fulfill({path:path.join(repo, 'public', url.pathname)}) : route.fulfill({body:html, contentType:'text/html'});
            });
            await page.goto('http://nav.test/admin/attendance/settings');
            await page.addScriptTag({url:'/assets/admin/js/crm/navigation.js'});
            await page.addScriptTag({url:'/assets/admin/libs/simplebar/simplebar.min.js'});
            await page.evaluate(enabled => {
                document.querySelector('.app-sidebar').style.minHeight = '0';
                if (enabled) new SimpleBar(document.getElementById('sidebar-scroll'), {autoHide:true});
                document.querySelectorAll('.crm-nav-group').forEach(group => group.open = true);
            }, simplebar);
            await page.waitForTimeout(200);
            const result = await page.evaluate(() => {
                const outer = document.getElementById('sidebar-scroll');
                const bar = SimpleBar.instances.get(outer);
                const scroll = bar ? bar.getScrollElement() : outer;
                const header = document.querySelector('.main-sidebar-header');
                const headerTop = header.getBoundingClientRect().top;
                scroll.scrollTop = scroll.scrollHeight;
                const lead = Array.from(document.querySelectorAll('.main-menu a')).find(link => link.textContent.includes('View DNP'));
                let bounds = lead.getBoundingClientRect();
                const viewport = scroll.getBoundingClientRect();
                // Super Admin has additional sections below Leads; move back within the menu only.
                if (bounds.top < viewport.top) {
                    scroll.scrollTop += bounds.top - viewport.top - 12;
                    bounds = lead.getBoundingClientRect();
                }
                return {reachable:bounds.top >= viewport.top && bounds.bottom <= Math.min(viewport.bottom, innerHeight), headerFixed:header.getBoundingClientRect().top === headerTop};
            });
            assert.equal(result.reachable, true, role + ' ' + height + ' submenu unreachable');
            assert.equal(result.headerFixed, true, 'Header scrolled with menu');
            console.log('PASS ' + role + ' ' + height + 'px ' + (simplebar ? 'SimpleBar' : 'native'));
            await page.close();
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
