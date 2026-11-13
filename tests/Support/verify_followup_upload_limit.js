const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const blade = fs.readFileSync(path.resolve(__dirname, '../../resources/views/admin/pages/follow-ups/add-follow-up.blade.php'), 'utf8');
const script = blade.split('<script>').find(block => block.includes('const MAX_KB = 2048;'));
assert.ok(script);
const listeners = {};
const upload = { addEventListener: (name, callback) => { listeners[name] = callback; } };
const button = { disabled: false };
const error = { textContent: '' };
const form = {
    querySelector: () => button,
    addEventListener: (name, callback) => { listeners[name] = callback; },
};
const preview = { appendChild() {} };
upload.form = form;
const document = {
    addEventListener: (name, callback) => callback(),
    getElementById: id => ({ image: upload, 'image-preview': preview, 'image-error': error })[id],
    createElement: () => ({ style: {}, appendChild() {}, addEventListener() {} }),
};
vm.runInNewContext(script.split('// Registration Link Functionality')[0], {
    document, URL: { createObjectURL: () => 'blob:test' }, alert() {},
});

const limit = 2048 * 1024;
function choose(size) {
    listeners.change.call({ files: [{ size, type: 'image/png' }] });
}

choose(limit);
assert.equal(button.disabled, false);
choose(limit + 1);
assert.equal(button.disabled, true, 'A file one byte above 2 MiB must be blocked');
let prevented = false;
listeners.submit({ preventDefault: () => { prevented = true; } });
assert.equal(prevented, true);
choose(limit);
assert.equal(button.disabled, false);
assert.equal(error.textContent, '');
console.log('Follow-up upload boundary and recovery passed');
