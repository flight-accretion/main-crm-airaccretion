const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/pages/whatsapp/admin/templates.blade.php'), 'utf8');
const start = source.indexOf('let lastModalTrigger = null;');
const end = source.indexOf('AUTO OPEN EDIT', start);
assert.ok(start > 0 && end > start);
// Stop before the Blade-controlled auto-open section, retaining complete JS handlers.
const lastComment = source.lastIndexOf('/*', end);
const code = source.slice(start, lastComment);
const classes = () => {
    const values = new Set();
    return {add: x => values.add(x), remove: x => values.delete(x), contains: x => values.has(x),
        toggle: (x, on) => on ? values.add(x) : values.delete(x)};
};

async function scenario(success) {
    const listeners = {};
    let doc, fetches = 0, removed = 0;
    const control = () => ({classList: classes(), dataset: {}, hidden: false, disabled: false,
        focus() {doc.activeElement = this;}, getClientRects() {return [{}];}, addEventListener() {}});
    const trigger = control();
    trigger.dataset = {templateId: 'qa-template', templateName: 'qa_company', senderCount: '2', deleteUrl: '/templates/qa-template'};
    trigger.closest = selector => selector === '[data-msg91-open-delete-modal]' ? trigger : null;
    const form = control();
    form.addEventListener = (type, fn) => {form[type] = fn;};
    const close = control(), cancel = control(), submit = control(), name = control(), count = control(), feedback = control(), label = control(), icon = control();
    const fields = {'[data-msg91-delete-template-form]': form, '[data-msg91-delete-feedback]': feedback,
        '[data-msg91-delete-template-name]': name, '[data-msg91-delete-sender-count]': count,
        '[data-msg91-delete-submit]': submit, '[data-msg91-delete-submit-label]': label, '[data-msg91-delete-submit-icon]': icon};
    const modal = {id: 'msg91-delete-template-modal', classList: classes(), setAttribute() {},
        querySelector(selector) {
            if (selector.includes('.is-submitting')) return form.classList.contains('is-submitting') ? form : null;
            return fields[selector] || close;
        }, querySelectorAll: () => [close, cancel, submit]};
    cancel.closest = selector => selector === '[data-msg91-close-modal]' ? cancel : selector === '.msg91-modal' ? modal : null;
    close.closest = cancel.closest;
    doc = {activeElement: trigger, body: {classList: classes()},
        addEventListener(type, fn) {(listeners[type] ||= []).push(fn);},
        getElementById: id => id === modal.id ? modal : null,
        querySelector: selector => selector === '.msg91-modal.is-open' && modal.classList.contains('is-open') ? modal : null,
        querySelectorAll: selector => selector === '[data-msg91-template-id]' ? [
            {dataset: {msg91TemplateId: 'qa-template'}, remove() {removed++;}},
            {dataset: {msg91TemplateId: 'unrelated'}, remove() {throw new Error('Unrelated template removed');}},
        ] : []};
    const event = (target, key) => ({target, key, preventDefault() {}, shiftKey: false});
    const dispatch = (type, value) => (listeners[type] || []).forEach(fn => fn(value));
    vm.runInNewContext(`(() => {${code}})()`, {document: doc, setTimeout: fn => fn(), FormData: class {},
        window: {setTimeout() {}, location: {reload() {}}, history: {}},
        fetch: async (url, options) => {
            fetches++; assert.equal(url, '/templates/qa-template'); assert.equal(options.method, 'POST');
            return {ok: success, text: async () => JSON.stringify(success
                ? {complete: true, message: 'All senders deleted'}
                : {complete: false, succeeded: 1, remaining: 1, message: 'Deletion incomplete: 1 sender confirmed, 1 unresolved'})};
        }});
    dispatch('click', event(trigger));
    assert.equal(name.textContent, 'qa_company'); assert.equal(count.textContent, '2');
    assert.ok(modal.classList.contains('is-open'));
    dispatch('click', event(cancel));
    assert.ok(!modal.classList.contains('is-open')); assert.equal(fetches, 0); assert.equal(doc.activeElement, trigger);
    dispatch('click', event(trigger));
    form.submit(event(form)); form.submit(event(form));
    assert.equal(fetches, 1); assert.ok(submit.disabled);
    dispatch('keydown', event(null, 'Escape'));
    assert.ok(modal.classList.contains('is-open'), 'Busy deletion cannot close accidentally');
    await new Promise(setImmediate); await new Promise(setImmediate);
    assert.equal(feedback.hidden, false);
    if (success) {assert.equal(removed, 1); assert.match(feedback.textContent, /All senders/);}
    else {assert.equal(removed, 0); assert.equal(submit.disabled, false); assert.match(feedback.textContent, /1 unresolved/);}
}

(async () => {
    await scenario(false); await scenario(true);
    let scripts = 0;
    for (const file of process.argv.slice(2)) {
        const html = fs.readFileSync(file, 'utf8');
        for (const match of html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) {
            if (/\bsrc\s*=/.test(match[1]) || /\btype\s*=\s*["'](?!text\/javascript|application\/javascript)[^"']+["']/.test(match[1])) continue;
            require('node:child_process').execFileSync(process.execPath, ['--check'], {input: match[2]}); scripts++;
        }
    }
    console.log(`PASS: actual template handlers: cancel/no request, correct identity/count, duplicate-submit guard, busy Escape, partial failure reset and success isolation; ${scripts} rendered scripts parsed. Not visual browser QA.`);
})().catch(error => {console.error(error); process.exitCode = 1;});
