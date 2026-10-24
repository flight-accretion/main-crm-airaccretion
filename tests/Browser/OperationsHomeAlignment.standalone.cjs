const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const {chromium} = require('C:/Users/reser/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root = path.resolve(__dirname, '../..'), directory = process.argv[2];
const fixtures = ['index.html', 'empty.html'];
(async () => {
    const browser = await chromium.launch({headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try {
        for (const file of fixtures) {
            const page = await browser.newPage(), errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('**/*', route => {
                const request = route.request(), url = new URL(request.url());
                if (request.method() !== 'GET') throw new Error('Read-only browser checks only');
                if (url.pathname.startsWith('/assets/')) {
                    const asset = path.join(root, 'public', url.pathname);
                    return fs.existsSync(asset) ? route.fulfill({path: asset}) : route.fulfill({status: 204});
                }
                if (request.resourceType() !== 'document') return route.fulfill({status: 204});
                return route.fulfill({contentType: 'text/html', body: fs.readFileSync(path.join(directory, file), 'utf8')});
            });
            await page.goto('http://home.test/admin/operations/dashboard');
            assert((await page.locator('.oh-incentive').innerText()).trim().length > 0, 'No blank incentive panel');
            for (const width of [1920, 1440, 1280, 1150, 1024, 768, 390, 320]) {
                await page.setViewportSize({width, height: 1050});
                for (const dark of [false, true]) {
                    await page.evaluate(value => document.documentElement.classList.toggle('dark', value), dark);
                    const standing = await page.locator('.oh-standing').boundingBox();
                    const kpi = await page.locator('.oh-standing > .oh-panel').boundingBox();
                    const incentive = await page.locator('.oh-incentive').boundingBox();
                    const cases = await page.locator('#oh-cases').boundingBox();
                    const departure = await page.locator('.oh-columns > .oh-panel').nth(1).boundingBox();
                    if (width > 1150) {
                        assert(Math.abs(kpi.x - cases.x) < 1 && Math.abs(kpi.width - cases.width) < 1, 'Left column edges align');
                        assert(Math.abs(incentive.x - departure.x) < 1 && Math.abs(incentive.width - departure.width) < 1, 'Right column edges align');
                        assert(Math.abs(kpi.y - incentive.y) < 1 && Math.abs(kpi.height - incentive.height) < 1, 'Standing panels have equal height');
                        assert(Math.abs(cases.y - departure.y) < 1 && Math.abs(cases.height - departure.height) < 1, 'Cases and departure have equal height');
                    } else {
                        assert(incentive.y >= kpi.y + kpi.height, 'Standing stacks on narrow screens');
                        assert(departure.y >= cases.y + cases.height, 'Cases and departure stack on narrow screens');
                        assert(Math.abs(kpi.width - standing.width) < 1, 'KPI fills available width');
                    }
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'No page overflow ' + width);
                    const overflow = await page.locator('.oh-standing, .oh-columns').evaluateAll(rows => rows.flatMap(row => Array.from(row.querySelectorAll('h2,h3,p,strong,.oh-badge')).filter(item => getComputedStyle(item).display !== 'inline' && item.scrollWidth > item.clientWidth + 2).map(item => item.textContent)));
                    assert.deepEqual(overflow, [], 'No clipped text ' + width);
                    if (!dark && [1440, 390].includes(width)) await page.locator('.oh-dashboard').screenshot({path: path.join(directory, 'alignment-' + file + '-' + width + '.png')});
                }
            }
            assert.deepEqual(errors, []); console.log('PASS Operations dashboard alignment, equal heights, responsive and dark mode: ' + file);
            await page.close();
        }
    } finally { await browser.close(); }
})().catch(error => {console.error(error); process.exitCode = 1;});
