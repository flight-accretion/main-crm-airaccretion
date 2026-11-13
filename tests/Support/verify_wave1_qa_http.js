const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const base = 'http://127.0.0.1:8001';

async function main() {
    const identity = await (await fetch(base + '/__qa/identity')).json();
    assert.equal(identity.database, 'accretion_wave1_qa');
    assert.equal(identity.role, 'accretion_wave1_qa_role');
    assert.deepEqual(identity.connections, ['pgsql']);
    assert.equal(identity.outbound, 'disabled');
    const results = [];
    const roles = process.argv.includes('--downloads-only') ? [] : ['super-admin', 'admin', 'owner', 'sales-manager', 'sales-executive', 'operations', 'accounts', 'hr'];
    for (const role of roles) {
        const login = await fetch(base + '/__qa/as/' + role, { redirect: 'manual' });
        assert.equal(login.status, 302);
        const cookie = login.headers.getSetCookie().map(value => value.split(';')[0]).join('; ');
        assert.ok(cookie);
        const routes = [
            ['/admin/upcoming-follow-up', !['accounts', 'operations'].includes(role)],
            ['/owner/dashboard?month=2026-08', role === 'owner'],
            ['/admin/report/vendor-payments?status=unpaid', !role.startsWith('sales-')],
            ['/admin/report/profit-loss?from_payment_date=2026-08-01&to_payment_date=2026-08-31', !role.startsWith('sales-')],
            ['/admin/refunds', !['sales-manager', 'sales-executive', 'hr'].includes(role)],
        ];
        for (const [route, allowed] of routes) {
            const start = Date.now();
            const response = await fetch(base + route, {
                headers: { Cookie: cookie }, redirect: 'manual', signal: AbortSignal.timeout(45000),
            });
            const body = await response.text();
            const result = { role, route, expected: allowed ? 200 : 403, actual: response.status, ms: Date.now() - start };
            result.pass = result.expected === result.actual;
            results.push(result);
            console.log(JSON.stringify(result));
            if (response.status >= 500) {
                fs.writeFileSync(path.join(__dirname, '.wave1-qa-runtime', 'http-error-' + role + '.html'), body);
            }
        }
    }
    if (results.length) {
        fs.writeFileSync(path.join(__dirname, '.wave1-qa-runtime', 'http-results.json'), JSON.stringify(results, null, 2));
    }
    assert.equal(results.filter(result => !result.pass).length, 0, 'Authenticated route matrix has failures');
    for (const [role, route, expected] of [
        ['operations', '/admin/operations/dashboard?cases=due', 200],
        ['operations', '/admin/operations/queue/review', 200],
        ['operations', '/admin/operations/vendor-followups', 200],
        ['operations', '/admin/operations/queue/review?segment=unassigned&operations_user_id=00000000-0000-4000-8000-000000000001', 200],
        ['sales-executive', '/admin/operations/queue/review?segment=unassigned', 403],
        ['hr', '/admin/targets/download-incentive?year=2026&month=11&file_type=csv', 200],
    ]) {
        const login = await fetch(base + '/__qa/as/' + role, { redirect: 'manual' });
        const cookie = login.headers.getSetCookie().map(value => value.split(';')[0]).join('; ');
        const response = await fetch(base + route, { headers: { Cookie: cookie }, redirect: 'manual' });
        const body = await response.text();
        console.log(JSON.stringify({ role, route, expected, actual: response.status }));
        if (route.includes('cases=due')) {
            const resultsPanel = body.split('id="oh-results"')[1];
            assert.ok(resultsPanel, 'Linked Due Today results must render');
            const rows = (resultsPanel.match(/class="oh-case"/g) || []).length;
            const card = body.match(/<strong>([\d,]+)<\/strong>\s*<h3>Due today<\/h3>/);
            assert.ok(card, 'Due Today card must render');
            assert.equal(rows, Math.min(25, Number(card[1].replaceAll(',', ''))));
            console.log(JSON.stringify({ dueTodayCard: card[1], renderedRows: rows }));
        }
        if (route.endsWith('/review') && role === 'operations') {
            assert.match(body, /2 cases/);
        }
        if (role === 'operations' && route.includes('segment=unassigned')) {
            assert.match(body, /1 cases/);
            console.log('Unassigned segment retained its one row with an assignee filter');
        }
        assert.equal(response.status, expected);
    }
    const accountsLogin = await fetch(base + '/__qa/as/accounts', { redirect: 'manual' });
    const accountsCookie = accountsLogin.headers.getSetCookie().map(value => value.split(';')[0]).join('; ');
    const acceptances = [
        ['/admin/report/vendor-payments/export?format=csv&status=unpaid', 'vendor.csv', 'text/csv'],
        ['/admin/report/vendor-payments/export?format=xlsx&status=unpaid', 'vendor.xlsx', 'spreadsheetml'],
        ['/admin/report/incentives/download?year=2026&month=11&file_type=csv', 'incentive.csv', 'text/csv'],
        ['/admin/report/incentives/download?year=2026&month=11&file_type=excel', 'incentive.xlsx', 'spreadsheetml'],
    ];
    const fixturesPath = path.join(__dirname, '.wave1-qa-runtime', 'fixtures.json');
    if (fs.existsSync(fixturesPath)) {
        const fixtures = JSON.parse(fs.readFileSync(fixturesPath, 'utf8'));
        if (fixtures.voucher_id) {
            acceptances.push(['/admin/account/invoices/' + fixtures.voucher_id + '/pdf', 'invoice.pdf', 'application/pdf']);
        }
    }
    for (const [route, file, contentType] of acceptances) {
        const response = await fetch(base + route, { headers: { Cookie: accountsCookie }, redirect: 'manual' });
        const bytes = Buffer.from(await response.arrayBuffer());
        const record = { role: 'accounts', route, status: response.status, contentType: response.headers.get('content-type'), bytes: bytes.length };
        console.log(JSON.stringify(record));
        assert.equal(response.status, 200);
        const disposition = response.headers.get('content-disposition') || '';
        assert.ok(record.contentType.includes(contentType)
            || (file.endsWith('.csv') && record.contentType.startsWith('text/plain') && disposition.includes('.csv')));
        assert.ok(disposition.includes(file.endsWith('.pdf') ? 'inline' : 'attachment'));
        assert.ok(bytes.length > 0);
        fs.writeFileSync(path.join(__dirname, '.wave1-qa-runtime', file), bytes);
    }
}

main().catch(error => { console.error(error.message); process.exitCode = 1; });
