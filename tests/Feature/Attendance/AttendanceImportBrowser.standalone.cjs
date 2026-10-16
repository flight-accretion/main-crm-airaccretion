/* Run with NODE_PATH pointing to a Playwright installation. Only static fixtures and mocked uploads. */
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const repo = path.resolve(__dirname, '../../..');

(async () => {
    const output = execFileSync('C:/xampp/php/php.exe', [path.join(__dirname, 'AttendanceImportWorkflow.standalone.php')], {cwd: repo, encoding: 'utf8'});
    process.stdout.write(output);
    const fixture = output.match(/BROWSER_FIXTURE=(.+)/)[1].trim();
    const html = fs.readFileSync(fixture, 'utf8');
    const browser = await chromium.launch({headless: true, executablePath: process.env.ATTENDANCE_BROWSER || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'});
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const users = [{id: 'one', name: 'Charul Garg', role: 'HR'}, {id: 'two', name: 'Other User', role: 'Sales'}];
        const employee = {paycode: null, mapping_key: 'name-one', file_name: 'Charul Garg', user_id: 'one', match_type: 'exact_name', record_count: 10001, period_from: '2026-09-01', period_to: '2026-09-30'};
        const rows = Array.from({length: 10001}, (_, index) => ({paycode: null, file_name: index === 0 ? '<img src=x onerror=alert(1)>' : 'Charul Garg', attendance_date: '2026-09-03', day_name: 'Wednesday', raw_in: '10:30', raw_out: '', raw_status: 'MIS'}));
        await page.route('http://attendance.test/**', async route => {
            const url = new URL(route.request().url());
            if (url.pathname.endsWith('/preview')) return route.fulfill({json: {import_id: 'preview-one', filename: 'test.csv', from_date: '2026-09-01', to_date: '2026-09-30', total_employees: 2, total_records: rows.length, outside_range_rows: 0, users, employees: [employee, {...employee, mapping_key: 'name-two', file_name: 'Ambiguous User', user_id: null, match_type: 'unmatched'}], rows}});
            if (url.pathname.startsWith('/assets/')) return route.fulfill({path: path.join(repo, 'public', url.pathname), contentType: url.pathname.endsWith('.css') ? 'text/css' : url.pathname.endsWith('.js') ? 'application/javascript' : 'application/octet-stream'});
            const sidebar = '<aside id="sidebar" style="display:none"><a class="side-menu__item" href="/dashboard">Dashboard</a><a class="side-menu__item" href="/leads">View Leads</a><a class="side-menu__item" href="/follow-up">Today\'s Follow-up</a><a class="side-menu__item" href="/kpi">KPI Dashboard</a><a class="side-menu__item" href="/authorized-only">Authorized link</a></aside>';
            const icons = '<link rel="stylesheet" href="/assets/admin/iconfonts/tabler-icons/iconfont/tabler-icons.min.css">';
            return route.fulfill({body: html.replace('<head>', '<head>' + icons).replace('<body>', '<body style="margin:0">' + sidebar), contentType: 'text/html'});
        });
        await page.goto('http://attendance.test/admin/attendance/import');
        assert.equal(await page.locator('#attendance-imports-table th button').count(), 10, 'Existing history column sorting must remain available');
        await page.locator('#attendance-imports-table th button').nth(6).click();
        assert.equal(await page.locator('#attendance-imports-table th').nth(6).getAttribute('aria-sort'), 'ascending');
        await page.locator('#from_date').fill('2026-09-01');
        await page.locator('#to_date').fill('2026-09-30');
        await page.locator('#excel_file').setInputFiles({name: 'test.csv', mimeType: 'text/csv', buffer: Buffer.from('Date,Day,Name,In,Out,Status\n')});
        await page.locator('#attendancePreviewButton').click();
        await page.locator('#attendancePreviewSection').waitFor({state: 'visible'});
        assert.equal(await page.locator('#attendanceRowsBody tr').count(), 50);
        assert.equal(await page.locator('#attendanceRowsBody img').count(), 0);
        assert.match(await page.locator('#attendanceRowsBody').innerText(), /Miss punch/);
        assert.equal(await page.locator('#attendanceConfirmButton').isDisabled(), true);
        await page.locator('.attendance-user-map').nth(1).selectOption('one');
        assert.equal(await page.locator('#attendanceConfirmButton').isDisabled(), true);
        await page.locator('.attendance-user-map').nth(1).selectOption('two');
        assert.equal(await page.locator('#attendanceConfirmButton').isEnabled(), true);
        await page.locator('#attendanceRowsNext').click();
        assert.equal(await page.locator('#attendanceRowsBody tr').count(), 50);
        assert.equal(await page.locator('#attendanceRowsPage').innerText(), '51-100 of 10001');
        for (const width of [1440, 1024, 768, 390, 320]) {
            await page.setViewportSize({width, height: 900});
            for (const theme of ['light', 'dark']) {
                await page.evaluate(theme => document.documentElement.className = theme, theme);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `Page overflow at ${width} ${theme}`);
                if (width < 768) {
                    assert.equal(await page.locator('#attendanceMobileNav').isVisible(), true);
                    assert.equal(await page.locator('#attendanceMobileNav > *').count(), 5);
                    assert.equal(await page.locator('#attendanceMobileNav i').evaluateAll(icons => icons.every(icon => !['none', 'normal', ''].includes(getComputedStyle(icon, '::before').content))), true, 'All mobile icons render from the existing icon font');
                    const navBox = await page.locator('#attendanceMobileNav').boundingBox();
                    assert.ok(Math.abs(navBox.y + navBox.height - 900) < 2);
                }
                await page.evaluate(() => scrollTo(0, 0));
                await page.screenshot({path: path.join(path.dirname(fixture), `import-${width}-${theme}.png`), fullPage: false});
            }
        }
        await page.getByRole('button', {name: 'More', exact: true}).click();
        assert.equal(await page.locator('#attendanceMoreDialog').isVisible(), true);
        assert.equal(await page.locator('#attendanceMoreLinks a[href="http://attendance.test/authorized-only"]').count(), 1);
        await page.locator('#attendanceMoreClose').click();
        await page.locator('#to_date').fill('2026-09-29');
        assert.equal(await page.locator('#attendancePreviewSection').isHidden(), true);
        assert.equal(await page.locator('#attendanceConfirmButton').isDisabled(), true);
        assert.deepEqual(errors, []);
        console.log('PASS browser: bounded 10,001-row preview, mapping gating, XSS escaping, stale-preview invalidation, five mobile links, 320-1440px light/dark overflow checks');
        console.log('SCREENSHOTS=' + path.dirname(fixture));
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
