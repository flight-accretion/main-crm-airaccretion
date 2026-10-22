const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

// Exercise production functions with deliberately out-of-order transport callbacks.
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/account/rides/ride-status.blade.php'), 'utf8');
function extract(start, end) {
    const from = source.indexOf(start);
    const to = source.indexOf(end, from);
    assert.ok(from >= 0 && to > from, `Missing source boundary: ${start}`);
    return source.slice(from, to);
}
const requests = [], errors = [], timers = [], values = new Map();
const panel = {hidden: true, setAttribute() {}};
const popover = {hidden: true, setAttribute() {}};
const card = id => ({dataset: {rideCard: id}, querySelector: () => ({appendChild() {}})});
const chain = selector => ({
    val(value) { if (arguments.length) { values.set(selector, value); return this; } return values.get(selector); },
    prop() { return this; }, hide() { return this; }, attr() { return ''; },
    ajaxSend(callback) { context.send = callback; return this; },
    ajaxComplete(callback) { context.complete = callback; return this; }
});
const $ = selector => chain(selector);
$.ajax = options => {
    const request = {options, aborted: false, abort() {
        this.aborted = true;
        options.error?.({status: 0}, 'abort');
        options.complete?.();
    }};
    requests.push(request);
    return request;
};
const context = vm.createContext({
    document: {getElementById: () => panel, querySelectorAll: () => [card('A'), card('B')]},
    URL, location: {href: 'http://localhost/admin/rides/ride-status'},
    window: {}, $, console, rideInlinePanel: panel, rideStatusPopover: popover,
    hideEditDatesForm() {}, setPopoverOpen(open) { popover.hidden = !open; },
    showLoadingState() {}, showError(message) { errors.push(message); },
    populateRideDetails(data) { context.loaded = data; },
    setTimeout(callback) { timers.push(callback); }
});
vm.runInContext(
    extract('let currentRideId = null;', '// Ride Status page:') +
    extract('function lockRideDetails(locked)', '// A save must finish') +
    extract('function openRidePanel(rideId)', "$(document).on('click', '.rs-status-toggle'") +
    extract('function loadRideDetails(rideId)', 'function populateRideDetails(data)'), context
);
const run = code => vm.runInContext(code, context);
const ok = (request, name) => request.options.success({success: true, data: {client_name: name, followup_id: `followup-${name}`}});

run("openRidePanel('A'); openRidePanel('B');");
assert.equal(requests[0].aborted, true);
assert.equal(panel.inert, true);
assert.equal(run('ensureRideDetailsReady()'), false);
ok(requests[1], 'B');
ok(requests[0], 'A'); // Simulate an already queued callback despite abort.
assert.equal(context.loaded.client_name, 'B');
assert.equal(run('loadedRideDetailsId'), 'B');
assert.equal(run('ensureRideDetailsReady()'), true);
requests[0].options.error({status: 500}, 'error');
assert.equal(errors.length, 1, 'Stale errors must be ignored');

run("loadRideDetails('B');");
assert.equal(run('loadedRideDetailsId'), null);
assert.equal(context.window.currentFollowupId, null);
requests[2].options.error({status: 404}, 'error');
assert.equal(panel.inert, true);
assert.equal(run('ensureRideDetailsReady()'), false);
run("loadRideDetails('B');");
requests[3].options.success({success: false, message: 'Unavailable'});
assert.equal(panel.inert, true, 'Invalid responses must not enable actions');

run("openRidePanel('A'); closeRidePanel();");
ok(requests[4], 'A');
timers.forEach(callback => callback());
assert.equal(panel.hidden, true);
assert.equal(run('loadedRideDetailsId'), null, 'Closed panels must ignore late data');

run("openRidePanel('A');");
ok(requests[5], 'A');
run('rideActionRequests = 1;');
const count = requests.length;
run("openRidePanel('B');");
assert.equal(requests.length, count, 'Cannot switch rides while a write is pending');
assert.equal(run('currentRideId'), 'A');
assert.equal(run('ensureRideDetailsReady()'), false);
run('rideActionRequests = 0;');
assert.equal(run('ensureRideDetailsReady()'), true);

run("loadRideDetails('B');");
requests[6].options.success({ride: {from_date: '2026-10-22'}, client: {name: 'B'}, followup_id: 'followup-B'});
assert.equal(context.loaded.followup_id, 'followup-B');
assert.equal(run('ensureRideDetailsReady()'), true, 'Direct API response remains supported');

const hooks = extract('const rideActionPaths =', 'function setPopoverOpen(open)').replace(
    /@json\(route\('([^']+)', '__RIDE__'\)\)/g,
    (_, name) => JSON.stringify(name.endsWith('send-refund-email')
        ? '/admin/rides/rides/__RIDE__/send-refund-email'
        : '/admin/rides/ride-status/__RIDE__/details')
);
vm.runInContext(hooks, context);
const read = {}, write = {}, email = {}, unrelated = {};
context.send(null, read, {url: '/admin/rides/ride-status/A/details', method: 'GET'});
context.send(null, unrelated, {url: '/unrelated', method: 'POST'});
assert.equal(run('rideActionRequests'), 0);
context.send(null, write, {url: '/admin/rides/ride-status/B/update-status', type: 'POST'});
context.send(null, email, {url: '/admin/rides/rides/A/send-refund-email', method: 'POST'});
assert.equal(run('rideActionRequests'), 2);
assert.equal(run('ensureRideDetailsReady()'), false);
context.complete(null, write);
context.complete(null, unrelated);
assert.equal(run('rideActionRequests'), 1);
context.complete(null, email);
assert.equal(run('rideActionRequests'), 0);
assert.equal(run('ensureRideDetailsReady()'), true);

const refundSave = extract('function saveRideRefundData() {', 'function openRefundSuccessModal()');
assert.match(refundSave, /const refundRideId = String\(currentRideId\)/);
assert.match(refundSave, /send-refund-email[\s\S]*?\.replace\(':rideId', refundRideId\)/);
assert.match(refundSave, /encodeURIComponent\(refundFollowupId\)/);
assert.doesNotMatch(extract('function loadRideDetails(rideId)', 'function populateRideDetails(data)'), /showSampleData\(/);
console.log('PASS: stale/aborted/closed requests, load failures, direct API format, pending-write locks and captured refund targets.');
