const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/pages/whatsapp/inbox.blade.php'), 'utf8');
const inline = source.slice(source.indexOf('<script>') + 8, source.indexOf('</script>', source.indexOf('<script>')))
    .replace(/const endpoints = \{[\s\S]*?\};/, 'const endpoints = {};')
    .replace(/const csrfToken = .*?;/, 'const csrfToken = "fixture";');
new vm.Script(inline);
console.log('PASS: Complete inbox JavaScript parses');

const logic = source.slice(source.indexOf('const renderMessages ='), source.indexOf("messagesEl.addEventListener('click'"));
const state = {
    selectedId: 'own', messages: [], historyCursor: null, hasOlderMessages: false,
    hasLoadedHistory: false, isLoadingHistory: false, historyError: '', messageRequestId: 0,
};
const messagesEl = {
    innerHTML: '', scrollTop: 0, clientHeight: 200,
    get scrollHeight() { return (this.innerHTML.match(/data-message-id=/g) || []).length * 20 + 40; },
};
const context = vm.createContext({
    state, messagesEl, URL, window: { location: { origin: 'https://fixture.test' } },
    endpoints: { messages: '/messages/__CONVERSATION__' },
    endpointFor: (template, id) => template.replace('__CONVERSATION__', id),
    escapeHtml: (value) => String(value ?? '').replaceAll('<', '&lt;'),
    conversationLabel: () => 'Fixture', chatNameEl: {}, chatMetaEl: {}, chatAgentEl: {},
    setComposerEnabled: () => {}, setStatus: () => {}, markRead: async () => false,
    loadConversations: () => {}, fetch: null,
});
vm.runInContext(logic + '\nglobalThis.loadHistory = loadMessages;', context);
const row = (id) => ({ id: String(id), body: 'fixture', sort_key: `2026-10-08 00:00:00.000000|${String(id).padStart(6, '0')}` });
const page = (from, to, more) => ({ conversation: {}, messages: Array.from({ length: to - from + 1 }, (_, i) => row(from + i)),
    meta: { has_more: more, next_before_id: more ? String(from) : null, retention_cutoff: '2026-04-09 00:00:00.000000' } });
const respond = (payload) => { context.fetch = async () => ({ ok: true, json: async () => payload }); };

(async () => {
    respond(page(51, 100, true));
    await context.loadHistory('own');
    assert.equal(state.messages.length, 50);
    assert.equal(state.historyCursor, '51');
    assert.match(messagesEl.innerHTML, /wa-load-older/);
    console.log('PASS: Initial view loads 50 messages and exposes older-history control');
    messagesEl.scrollTop = 25;
    const oldHeight = messagesEl.scrollHeight;
    const olderPayload = page(1, 50, false);
    context.fetch = async (url) => {
        assert.equal(url.searchParams.get('before_id'), '51');
        return { ok: true, json: async () => olderPayload };
    };
    await context.loadHistory('own', { silent: true, older: true });
    assert.equal(state.messages.length, 100);
    assert.equal(state.messages[0].id, '1');
    assert.equal(messagesEl.scrollTop, 25 + messagesEl.scrollHeight - oldHeight);
    assert.equal(state.hasOlderMessages, false);
    console.log('PASS: Older history prepends chronologically and preserves scroll position');
    respond(page(52, 101, true));
    await context.loadHistory('own', { silent: true });
    assert.equal(state.messages.length, 101);
    assert.equal(new Set(state.messages.map((message) => message.id)).size, 101);
    assert.equal(state.messages[0].id, '1');
    assert.equal(state.hasOlderMessages, false);
    console.log('PASS: Polling retains loaded history without duplicates or reopening exhausted history');
    state.historyCursor = '1'; state.hasOlderMessages = true;
    context.fetch = async () => { throw new Error('fixture network failure'); };
    await context.loadHistory('own', { silent: true, older: true });
    assert.equal(state.messages.length, 101);
    assert.equal(state.isLoadingHistory, false);
    assert.match(messagesEl.innerHTML, /Please retry/);
    console.log('PASS: Failed history fetch leaves existing messages and permits retry');
    let release;
    context.fetch = () => new Promise((resolve) => { release = resolve; });
    const pending = context.loadHistory('own', { silent: true });
    state.selectedId = 'different'; state.messageRequestId++;
    release({ ok: true, json: async () => page(102, 151, true) });
    await pending;
    assert.equal(state.messages.length, 101);
    console.log('PASS: Late responses cannot overwrite a newly selected conversation');
})().catch((error) => { console.error(error); process.exitCode = 1; });
