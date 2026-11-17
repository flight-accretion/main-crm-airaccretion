const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/pages/follow-ups/add-follow-up.blade.php'), 'utf8');
const marker = "document.addEventListener('DOMContentLoaded', function() {\n            const servicesSelect";
const normalized = source.replace(/\r\n/g, '\n');
const start = normalized.indexOf(marker);
assert.notEqual(start, -1);
const bodyStart = normalized.indexOf('{', start) + 1;
const end = normalized.indexOf('// ALWAYS build table', bodyStart);
assert.ok(end > bodyStart);
// Execute the actual template's initialization prefix, before pricing/UI effects.
const initialize = new Function('document', 'statusSelect', 'syncExtraServicesForSelectedServices',
    'let previousServiceSelections, previousExtraSelections;\n' + normalized.slice(bodyStart, end) +
    '\nreturn {previousServiceSelections, previousExtraSelections};');
assert.equal(initialize({getElementById: () => null}, null, () => {throw new Error('Owner must not initialize payment fields');}), undefined);
const normal = initialize({getElementById: id => id === 'services' ? {selectedOptions: [{value: 'saved-service'}]} : null}, {}, () => {});
assert.deepEqual(normal, {previousServiceSelections: ['saved-service'], previousExtraSelections: []});
console.log('PASS: Owner status-only form skips payment initialization; ordinary follow-up tolerates retired extras picker (2 scenarios).');
let scripts = 0;
for (const file of process.argv.slice(2)) {
    const html = fs.readFileSync(file, 'utf8');
    for (const match of html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) {
        if (/\bsrc\s*=/.test(match[1]) || /\btype\s*=\s*["'](?!text\/javascript|application\/javascript)[^"']+["']/.test(match[1])) continue;
        require('node:child_process').execFileSync(process.execPath, ['--check'], {input: match[2], stdio: ['pipe', 'pipe', 'pipe']});
        scripts++;
    }
}
if (scripts) console.log(`PASS: ${scripts} rendered inline scripts parsed across ${process.argv.length - 2} authenticated role pages.`);
