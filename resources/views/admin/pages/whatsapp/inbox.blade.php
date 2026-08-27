@extends('admin.layouts.header')

@section('content')
    <style>
        body {
            overflow: hidden;
        }

        .wa-inbox-page {
            height: calc(100vh - 60px);
            height: calc(100dvh - 60px);
            min-height: 0;
            overflow: hidden;
        }

        .wa-inbox-shell {
            display: grid;
            grid-template-columns: minmax(300px, 390px) minmax(0, 1fr);
            height: 100%;
            min-height: 0;
            overflow: hidden;
            border: 1px solid rgb(226 232 240);
            background: #fff;
        }

        .wa-list-pane {
            order: 1;
            border-right: 1px solid rgb(226 232 240);
            min-width: 0;
            min-height: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .wa-chat-pane {
            order: 2;
            min-width: 0;
            min-height: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background: #f8fafc;
        }

        .wa-toolbar {
            padding: 14px;
            border-bottom: 1px solid rgb(226 232 240);
            display: grid;
            gap: 10px;
            background: #fff;
            flex: 0 0 auto;
        }

        .wa-filter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .wa-conversation-list {
            flex: 1 1 auto;
            overflow: auto;
            min-height: 0;
        }

        .wa-show-more-wrap {
            padding: 12px 14px;
            background: #fff;
        }

        .wa-show-more {
            width: 100%;
            justify-content: center;
        }

        .wa-contact-row {
            width: 100%;
            border: 0;
            border-bottom: 1px solid rgb(241 245 249);
            background: #fff;
            padding: 12px 14px;
            text-align: left;
            display: grid;
            gap: 5px;
            cursor: pointer;
        }

        .wa-contact-row:hover,
        .wa-contact-row.is-active {
            background: #ecfdf5;
        }

        .wa-contact-top {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
            align-items: center;
        }

        .wa-contact-name,
        .wa-contact-number,
        .wa-contact-message {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .wa-contact-name {
            font-weight: 600;
            color: #0f172a;
        }

        .wa-contact-number {
            color: #64748b;
            font-size: 12px;
        }

        .wa-contact-message {
            color: #475569;
            font-size: 13px;
        }

        .wa-unread-pill {
            min-width: 24px;
            height: 24px;
            border-radius: 999px;
            padding: 0 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: #16a34a;
            font-size: 12px;
            font-weight: 700;
        }

        .wa-chat-header {
            min-height: 74px;
            padding: 14px 18px;
            border-bottom: 1px solid rgb(226 232 240);
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex: 0 0 auto;
        }

        .wa-chat-title {
            min-width: 0;
        }

        .wa-chat-title h4 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
        }

        .wa-chat-title p {
            margin: 3px 0 0;
            color: #64748b;
            font-size: 13px;
        }

        .wa-messages {
            flex: 1;
            min-height: 0;
            overflow: auto;
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .wa-message-row {
            display: flex;
        }

        .wa-message-row.is-outgoing {
            justify-content: flex-end;
        }

        .wa-bubble {
            max-width: min(72%, 680px);
            border: 1px solid rgb(226 232 240);
            background: #fff;
            color: #0f172a;
            padding: 10px 12px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .wa-message-row.is-outgoing .wa-bubble {
            background: #dcfce7;
            border-color: #bbf7d0;
        }

        .wa-bubble-body {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            line-height: 1.45;
        }

        .wa-bubble-meta {
            margin-top: 7px;
            font-size: 11px;
            color: #64748b;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .wa-empty-state {
            padding: 28px 18px;
            color: #64748b;
            text-align: center;
        }

        .wa-composer {
            border-top: 1px solid rgb(226 232 240);
            background: #fff;
            padding: 12px 14px;
            display: grid;
            gap: 10px;
            flex: 0 0 auto;
        }

        .wa-composer-grid {
            display: grid;
            grid-template-columns: 150px minmax(0, 1fr) auto;
            gap: 10px;
            align-items: stretch;
        }

        .wa-composer textarea {
            min-height: 46px;
            max-height: 120px;
            resize: none;
        }

        .wa-extra-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .wa-extra-fields.is-hidden,
        .wa-contact-fields.is-hidden,
        .wa-location-fields.is-hidden {
            display: none;
        }

        .wa-contact-fields,
        .wa-location-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .wa-send-status {
            min-height: 18px;
            color: #64748b;
            font-size: 12px;
        }

        .wa-send-status.is-error {
            color: #dc2626;
        }

        .wa-send-status.is-success {
            color: #16a34a;
        }

        @media (max-width: 991px) {
            .wa-inbox-shell {
                grid-template-columns: 1fr;
                grid-template-rows: minmax(180px, 34%) minmax(0, 1fr);
            }

            .wa-list-pane {
                border-right: 0;
                border-bottom: 1px solid rgb(226 232 240);
                max-height: none;
            }

            .wa-chat-pane {
                min-height: 0;
            }

            .wa-composer-grid,
            .wa-extra-fields,
            .wa-contact-fields,
            .wa-location-fields {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="wa-inbox-page">
        <div class="wa-inbox-shell">
        <section class="wa-chat-pane">
            <div class="wa-chat-header">
                <div class="wa-chat-title">
                    <h4 id="wa-chat-name">
                        Select a conversation
                    </h4>
                    <p id="wa-chat-meta">
                        -
                    </p>
                </div>

                <span
                    class="badge bg-success/10 text-success"
                    id="wa-chat-agent"
                >
                    -
                </span>
            </div>

            <div class="wa-messages" id="wa-messages">
                <div class="wa-empty-state">
                    No conversation selected.
                </div>
            </div>

            <form class="wa-composer" id="wa-send-form">
                <div class="wa-composer-grid">
                    <select
                        id="wa-message-type"
                        class="ti-form-select"
                    >
                        <option value="text">Text</option>
                        <option value="image">Image</option>
                        <option value="video">Video</option>
                        <option value="audio">Audio</option>
                        <option value="contact">Contact</option>
                        <option value="location">Location</option>
                    </select>

                    <textarea
                        id="wa-message-body"
                        class="form-control"
                        placeholder="Type a message"
                        rows="1"
                        disabled
                    ></textarea>

                    <button
                        type="submit"
                        id="wa-send-button"
                        class="ti-btn ti-btn-primary"
                        disabled
                    >
                        Send
                    </button>
                </div>

                <div
                    class="wa-extra-fields is-hidden"
                    id="wa-media-fields"
                >
                    <input
                        type="url"
                        id="wa-media-url"
                        class="form-control"
                        placeholder="Media URL"
                        disabled
                    >
                    <input
                        type="text"
                        id="wa-media-caption"
                        class="form-control"
                        placeholder="Caption"
                        disabled
                    >
                </div>

                <div
                    class="wa-contact-fields is-hidden"
                    id="wa-contact-fields"
                >
                    <input
                        type="text"
                        id="wa-contact-name"
                        class="form-control"
                        placeholder="Contact name"
                        disabled
                    >
                    <input
                        type="text"
                        id="wa-contact-phone"
                        class="form-control"
                        placeholder="Contact phone"
                        disabled
                    >
                </div>

                <div
                    class="wa-location-fields is-hidden"
                    id="wa-location-fields"
                >
                    <input
                        type="number"
                        step="any"
                        id="wa-location-latitude"
                        class="form-control"
                        placeholder="Latitude"
                        disabled
                    >
                    <input
                        type="number"
                        step="any"
                        id="wa-location-longitude"
                        class="form-control"
                        placeholder="Longitude"
                        disabled
                    >
                    <input
                        type="text"
                        id="wa-location-name"
                        class="form-control"
                        placeholder="Location name"
                        disabled
                    >
                    <input
                        type="text"
                        id="wa-location-address"
                        class="form-control"
                        placeholder="Address"
                        disabled
                    >
                </div>

                <div
                    class="wa-send-status"
                    id="wa-send-status"
                ></div>
            </form>
        </section>

        <aside class="wa-list-pane">
            <div class="wa-toolbar">
                <input
                    type="search"
                    id="wa-search"
                    class="form-control"
                    placeholder="Search number or name"
                    autocomplete="off"
                >

                <div class="wa-filter-grid">
                    <select id="wa-status-filter" class="ti-form-select">
                        <option value="">All chats</option>
                        <option value="unread">Unread</option>
                        <option value="read">Read</option>
                    </select>

                    <select id="wa-agent-filter" class="ti-form-select">
                        <option value="">All agents</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}">
                                {{ $agent->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div
                class="wa-conversation-list"
                id="wa-conversations"
            >
                <div class="wa-empty-state">
                    Loading conversations...
                </div>
            </div>
        </aside>
        </div>
    </div>

    <script>
        (() => {
            const endpoints = {
                conversations: @json(route('admin.whatsapp.conversations')),
                messages: @json(route('admin.whatsapp.messages', ['conversation' => '__CONVERSATION__'])),
                send: @json(route('admin.whatsapp.send', ['conversation' => '__CONVERSATION__'])),
                read: @json(route('admin.whatsapp.read', ['conversation' => '__CONVERSATION__'])),
            };

            const csrfToken = @json(csrf_token());
            const pollIntervalMs = 5000;
            const conversationsEl = document.getElementById('wa-conversations');
            const messagesEl = document.getElementById('wa-messages');
            const searchEl = document.getElementById('wa-search');
            const statusEl = document.getElementById('wa-status-filter');
            const agentEl = document.getElementById('wa-agent-filter');
            const chatNameEl = document.getElementById('wa-chat-name');
            const chatMetaEl = document.getElementById('wa-chat-meta');
            const chatAgentEl = document.getElementById('wa-chat-agent');
            const sendFormEl = document.getElementById('wa-send-form');
            const messageTypeEl = document.getElementById('wa-message-type');
            const messageBodyEl = document.getElementById('wa-message-body');
            const sendButtonEl = document.getElementById('wa-send-button');
            const sendStatusEl = document.getElementById('wa-send-status');
            const mediaFieldsEl = document.getElementById('wa-media-fields');
            const mediaUrlEl = document.getElementById('wa-media-url');
            const mediaCaptionEl = document.getElementById('wa-media-caption');
            const contactFieldsEl = document.getElementById('wa-contact-fields');
            const contactNameEl = document.getElementById('wa-contact-name');
            const contactPhoneEl = document.getElementById('wa-contact-phone');
            const locationFieldsEl = document.getElementById('wa-location-fields');
            const locationLatitudeEl = document.getElementById('wa-location-latitude');
            const locationLongitudeEl = document.getElementById('wa-location-longitude');
            const locationNameEl = document.getElementById('wa-location-name');
            const locationAddressEl = document.getElementById('wa-location-address');

            const state = {
                conversations: [],
                selectedId: null,
                searchTimer: null,
                pollTimer: null,
                isPolling: false,
                pageSize: 50,
                nextOffset: 0,
                hasMore: false,
                total: 0,
                isLoadingMore: false,
            };

            const escapeHtml = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const endpointFor = (template, id) =>
                template.replace('__CONVERSATION__', encodeURIComponent(id));

            const conversationLabel = (conversation) =>
                conversation.contact_name && conversation.contact_name !== 'Unknown'
                    ? conversation.contact_name
                    : (conversation.number || 'Unknown');

            const buildQuery = ({
                limit = state.pageSize,
                offset = 0,
            } = {}) => {
                const params = new URLSearchParams();
                const search = searchEl.value.trim();

                params.set('limit', String(limit));
                params.set('offset', String(offset));

                if (search) {
                    params.set('search', search);
                }

                if (statusEl.value) {
                    params.set('status', statusEl.value);
                }

                if (agentEl.value) {
                    params.set('agent_id', agentEl.value);
                }

                return params.toString();
            };

            const mergeConversations = (incoming, mode = 'replace') => {
                if (mode === 'replace') {
                    state.conversations = incoming;
                    return;
                }

                if (mode === 'append') {
                    const existingIds = new Set(state.conversations.map((conversation) => conversation.id));
                    state.conversations = [
                        ...state.conversations,
                        ...incoming.filter((conversation) => !existingIds.has(conversation.id)),
                    ];
                    return;
                }

                const incomingIds = new Set(incoming.map((conversation) => conversation.id));
                state.conversations = [
                    ...incoming,
                    ...state.conversations.filter((conversation) => !incomingIds.has(conversation.id)),
                ];
            };

            const renderConversations = () => {
                if (!state.conversations.length) {
                    conversationsEl.innerHTML = '<div class="wa-empty-state">No conversations found.</div>';
                    return;
                }

                const rows = state.conversations.map((conversation) => {
                    const activeClass = conversation.id === state.selectedId ? ' is-active' : '';
                    const unread = Number(conversation.unread_count || 0);
                    const unreadBadge = unread > 0
                        ? `<span class="wa-unread-pill">${unread}</span>`
                        : '';

                    return `
                        <button
                            type="button"
                            class="wa-contact-row${activeClass}"
                            data-id="${escapeHtml(conversation.id)}"
                        >
                            <span class="wa-contact-top">
                                <span class="wa-contact-name">${escapeHtml(conversationLabel(conversation))}</span>
                                ${unreadBadge}
                            </span>
                            <span class="wa-contact-number">${escapeHtml(conversation.number || conversation.raw_phone || '-')}</span>
                            <span class="wa-contact-message">${escapeHtml(conversation.last_message || '-')}</span>
                        </button>
                    `;
                }).join('');

                const showMore = state.hasMore
                    ? `
                        <div class="wa-show-more-wrap">
                            <button
                                type="button"
                                class="ti-btn ti-btn-light wa-show-more"
                                id="wa-show-more"
                                ${state.isLoadingMore ? 'disabled' : ''}
                            >
                                ${state.isLoadingMore ? 'Loading...' : 'Show more'}
                            </button>
                        </div>
                    `
                    : '';

                conversationsEl.innerHTML = rows + showMore;
            };

            const clearChat = () => {
                chatNameEl.textContent = 'Select a conversation';
                chatMetaEl.textContent = '-';
                chatAgentEl.textContent = '-';
                messagesEl.innerHTML = '<div class="wa-empty-state">No conversation selected.</div>';
                setComposerEnabled(false);
            };

            const setStatus = (message, type = '') => {
                sendStatusEl.textContent = message || '';
                sendStatusEl.classList.toggle('is-error', type === 'error');
                sendStatusEl.classList.toggle('is-success', type === 'success');
            };

            const setComposerEnabled = (enabled) => {
                messageBodyEl.disabled = !enabled;
                messageTypeEl.disabled = !enabled;
                sendButtonEl.disabled = !enabled;
                renderComposerFields();
            };

            const setGroupEnabled = (element, enabled) => {
                element
                    .querySelectorAll('input, textarea, select')
                    .forEach((field) => {
                        field.disabled = !enabled;
                    });
            };

            const renderComposerFields = () => {
                const type = messageTypeEl.value;
                const hasConversation = Boolean(state.selectedId);
                const isMedia = ['image', 'video', 'audio'].includes(type);
                const isContact = type === 'contact';
                const isLocation = type === 'location';

                mediaFieldsEl.classList.toggle('is-hidden', !isMedia);
                contactFieldsEl.classList.toggle('is-hidden', !isContact);
                locationFieldsEl.classList.toggle('is-hidden', !isLocation);

                setGroupEnabled(mediaFieldsEl, hasConversation && isMedia);
                setGroupEnabled(contactFieldsEl, hasConversation && isContact);
                setGroupEnabled(locationFieldsEl, hasConversation && isLocation);

                messageBodyEl.placeholder = isMedia
                    ? 'Caption'
                    : (
                        isContact || isLocation
                            ? 'Optional note'
                            : 'Type a message'
                    );
            };

            const loadConversations = async ({
                selectFirst = false,
                silent = false,
                append = false,
                preserveExisting = false,
                offset = 0,
            } = {}) => {
                const query = buildQuery({
                    offset,
                });
                const url = query
                    ? `${endpoints.conversations}?${query}`
                    : endpoints.conversations;

                if (!silent) {
                    conversationsEl.innerHTML = '<div class="wa-empty-state">Loading conversations...</div>';
                }

                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    if (!silent) {
                        conversationsEl.innerHTML = '<div class="wa-empty-state">Unable to load conversations.</div>';
                    }

                    return;
                }

                const payload = await response.json();
                const incoming = payload.data || [];
                const meta = payload.meta || {};

                mergeConversations(
                    incoming,
                    append
                        ? 'append'
                        : (preserveExisting ? 'preserve' : 'replace')
                );

                state.total = Number(meta.total || state.conversations.length);
                state.nextOffset = state.conversations.length;
                state.hasMore = state.nextOffset < state.total;

                if (
                    state.selectedId
                    && !state.conversations.some((conversation) => conversation.id === state.selectedId)
                ) {
                    state.selectedId = null;
                    clearChat();
                }

                renderConversations();

                if (
                    (selectFirst || (!silent && !state.selectedId))
                    && state.conversations.length
                ) {
                    selectConversation(state.conversations[0].id);
                }
            };

            const loadMoreConversations = async () => {
                if (
                    !state.hasMore
                    || state.isLoadingMore
                ) {
                    return;
                }

                state.isLoadingMore = true;
                renderConversations();

                try {
                    await loadConversations({
                        silent: true,
                        append: true,
                        offset: state.nextOffset,
                    });
                } finally {
                    state.isLoadingMore = false;
                    renderConversations();
                }
            };

            const markRead = async (conversationId) => {
                const response = await fetch(endpointFor(endpoints.read, conversationId), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });

                if (!response.ok) {
                    return false;
                }

                const payload = await response.json();
                return Boolean(payload.read_cleared);
            };

            const loadMessages = async (
                conversationId,
                {
                    silent = false,
                } = {}
            ) => {
                const shouldStickToBottom =
                    messagesEl.scrollHeight
                    - messagesEl.scrollTop
                    - messagesEl.clientHeight
                    < 140;

                if (!silent) {
                    messagesEl.innerHTML = '<div class="wa-empty-state">Loading messages...</div>';
                }

                const response = await fetch(endpointFor(endpoints.messages, conversationId), {
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    if (!silent) {
                        messagesEl.innerHTML = '<div class="wa-empty-state">Unable to load messages.</div>';
                    }

                    return;
                }

                const payload = await response.json();

                if (state.selectedId !== conversationId) {
                    return;
                }

                const conversation = payload.conversation || {};
                const messages = payload.messages || [];

                chatNameEl.textContent = conversationLabel(conversation);
                chatMetaEl.textContent = conversation.number || conversation.raw_phone || '-';
                chatAgentEl.textContent = conversation.assigned_user_name || 'Unassigned';
                setComposerEnabled(true);
                setStatus('');

                if (!messages.length) {
                    messagesEl.innerHTML = '<div class="wa-empty-state">No messages yet.</div>';
                } else {
                    messagesEl.innerHTML = messages.map((message) => {
                        const outgoingClass = message.direction === 'outgoing'
                            ? ' is-outgoing'
                            : '';

                        return `
                            <div class="wa-message-row${outgoingClass}">
                                <div class="wa-bubble">
                                    <div class="wa-bubble-body">${escapeHtml(message.body || '-')}</div>
                                    <div class="wa-bubble-meta">
                                        <span>${escapeHtml(message.sender_name || '')}</span>
                                        <span>${escapeHtml(message.message_at || '')}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');

                    if (!silent || shouldStickToBottom) {
                        messagesEl.scrollTop = messagesEl.scrollHeight;
                    }
                }

                if (await markRead(conversationId)) {
                    loadConversations({
                        silent: true,
                        preserveExisting: true,
                    });
                }
            };

            const selectConversation = (conversationId) => {
                state.selectedId = conversationId;
                renderConversations();
                loadMessages(conversationId);
            };

            const payloadForComposer = () => {
                const type = messageTypeEl.value;
                const payload = {
                    message_type: type,
                    message: messageBodyEl.value.trim(),
                };

                if (['image', 'video', 'audio'].includes(type)) {
                    payload.media_url = mediaUrlEl.value.trim();
                    payload.caption = mediaCaptionEl.value.trim() || payload.message;
                }

                if (type === 'contact') {
                    payload.contacts = [{
                        name: {
                            formatted_name: contactNameEl.value.trim(),
                        },
                        phones: [{
                            phone: contactPhoneEl.value.trim(),
                            type: 'CELL',
                        }],
                    }];
                }

                if (type === 'location') {
                    payload.latitude = locationLatitudeEl.value;
                    payload.longitude = locationLongitudeEl.value;
                    payload.name = locationNameEl.value.trim();
                    payload.address = locationAddressEl.value.trim();
                }

                return payload;
            };

            const resetComposer = () => {
                messageBodyEl.value = '';
                mediaUrlEl.value = '';
                mediaCaptionEl.value = '';
                contactNameEl.value = '';
                contactPhoneEl.value = '';
                locationLatitudeEl.value = '';
                locationLongitudeEl.value = '';
                locationNameEl.value = '';
                locationAddressEl.value = '';
            };

            const sendCurrentMessage = async () => {
                if (!state.selectedId) {
                    return;
                }

                setStatus('Sending...');
                sendButtonEl.disabled = true;

                const response = await fetch(endpointFor(endpoints.send, state.selectedId), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payloadForComposer()),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok || payload.success === false) {
                    setStatus(payload.message || 'Unable to send message.', 'error');
                    sendButtonEl.disabled = false;
                    return;
                }

                resetComposer();
                setStatus('Sent', 'success');
                await loadMessages(state.selectedId);
                await loadConversations({
                    silent: true,
                    preserveExisting: true,
                });
                sendButtonEl.disabled = false;
            };

            const pollInbox = async () => {
                if (document.hidden || state.isPolling) {
                    return;
                }

                state.isPolling = true;

                try {
                    const selectedId = state.selectedId;

                    await loadConversations({
                        silent: true,
                        preserveExisting: true,
                    });

                    if (selectedId && state.selectedId === selectedId) {
                        await loadMessages(selectedId, { silent: true });
                    }
                } finally {
                    state.isPolling = false;
                }
            };

            const startPolling = () => {
                if (state.pollTimer) {
                    window.clearInterval(state.pollTimer);
                }

                state.pollTimer = window.setInterval(
                    pollInbox,
                    pollIntervalMs
                );
            };

            conversationsEl.addEventListener('click', (event) => {
                const showMoreButton = event.target.closest('#wa-show-more');

                if (showMoreButton) {
                    loadMoreConversations();
                    return;
                }

                const row = event.target.closest('.wa-contact-row');

                if (!row) {
                    return;
                }

                selectConversation(row.dataset.id);
            });

            searchEl.addEventListener('input', () => {
                window.clearTimeout(state.searchTimer);
                state.searchTimer = window.setTimeout(() => {
                    loadConversations({ selectFirst: true });
                }, 250);
            });

            statusEl.addEventListener('change', () => {
                loadConversations({ selectFirst: true });
            });

            agentEl.addEventListener('change', () => {
                loadConversations({ selectFirst: true });
            });

            messageTypeEl.addEventListener('change', renderComposerFields);

            sendFormEl.addEventListener('submit', (event) => {
                event.preventDefault();
                sendCurrentMessage();
            });

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    pollInbox();
                }
            });

            setComposerEnabled(false);
            loadConversations({ selectFirst: true });
            startPolling();
        })();
    </script>
@endsection
