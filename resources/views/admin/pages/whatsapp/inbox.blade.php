@extends('admin.layouts.header')

@section('content')
    <style>
        .wa-inbox-shell {
            display: grid;
            grid-template-columns: minmax(300px, 390px) minmax(0, 1fr);
            height: calc(100vh - 185px);
            min-height: 620px;
            border: 1px solid rgb(226 232 240);
            background: #fff;
        }

        .wa-list-pane {
            border-right: 1px solid rgb(226 232 240);
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .wa-chat-pane {
            min-width: 0;
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
        }

        .wa-filter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .wa-conversation-list {
            overflow: auto;
            min-height: 0;
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

        @media (max-width: 991px) {
            .wa-inbox-shell {
                grid-template-columns: 1fr;
                height: auto;
            }

            .wa-list-pane {
                border-right: 0;
                border-bottom: 1px solid rgb(226 232 240);
                max-height: 430px;
            }

            .wa-chat-pane {
                min-height: 560px;
            }
        }
    </style>

    <div class="block justify-between page-header md:flex">
        <div>
            <h3 class="text-xl font-semibold">
                WhatsApp Inbox
            </h3>
            <p class="text-sm text-gray-500 mt-1">
                Synced from WhatCRM
            </p>
        </div>
    </div>

    <div class="wa-inbox-shell">
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
        </section>
    </div>

    <script>
        (() => {
            const endpoints = {
                conversations: @json(route('admin.whatsapp.conversations')),
                messages: @json(route('admin.whatsapp.messages', ['conversation' => '__CONVERSATION__'])),
                read: @json(route('admin.whatsapp.read', ['conversation' => '__CONVERSATION__'])),
            };

            const csrfToken = @json(csrf_token());
            const conversationsEl = document.getElementById('wa-conversations');
            const messagesEl = document.getElementById('wa-messages');
            const searchEl = document.getElementById('wa-search');
            const statusEl = document.getElementById('wa-status-filter');
            const agentEl = document.getElementById('wa-agent-filter');
            const chatNameEl = document.getElementById('wa-chat-name');
            const chatMetaEl = document.getElementById('wa-chat-meta');
            const chatAgentEl = document.getElementById('wa-chat-agent');

            const state = {
                conversations: [],
                selectedId: null,
                searchTimer: null,
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

            const buildQuery = () => {
                const params = new URLSearchParams();
                const search = searchEl.value.trim();

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

            const renderConversations = () => {
                if (!state.conversations.length) {
                    conversationsEl.innerHTML = '<div class="wa-empty-state">No conversations found.</div>';
                    return;
                }

                conversationsEl.innerHTML = state.conversations.map((conversation) => {
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
            };

            const clearChat = () => {
                chatNameEl.textContent = 'Select a conversation';
                chatMetaEl.textContent = '-';
                chatAgentEl.textContent = '-';
                messagesEl.innerHTML = '<div class="wa-empty-state">No conversation selected.</div>';
            };

            const loadConversations = async ({ selectFirst = false } = {}) => {
                const query = buildQuery();
                const url = query
                    ? `${endpoints.conversations}?${query}`
                    : endpoints.conversations;

                conversationsEl.innerHTML = '<div class="wa-empty-state">Loading conversations...</div>';

                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    conversationsEl.innerHTML = '<div class="wa-empty-state">Unable to load conversations.</div>';
                    return;
                }

                const payload = await response.json();
                state.conversations = payload.data || [];

                if (
                    state.selectedId
                    && !state.conversations.some((conversation) => conversation.id === state.selectedId)
                ) {
                    state.selectedId = null;
                    clearChat();
                }

                renderConversations();

                if (
                    (selectFirst || !state.selectedId)
                    && state.conversations.length
                ) {
                    selectConversation(state.conversations[0].id);
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

            const loadMessages = async (conversationId) => {
                messagesEl.innerHTML = '<div class="wa-empty-state">Loading messages...</div>';

                const response = await fetch(endpointFor(endpoints.messages, conversationId), {
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    messagesEl.innerHTML = '<div class="wa-empty-state">Unable to load messages.</div>';
                    return;
                }

                const payload = await response.json();
                const conversation = payload.conversation || {};
                const messages = payload.messages || [];

                chatNameEl.textContent = conversationLabel(conversation);
                chatMetaEl.textContent = conversation.number || conversation.raw_phone || '-';
                chatAgentEl.textContent = conversation.assigned_user_name || 'Unassigned';

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

                    messagesEl.scrollTop = messagesEl.scrollHeight;
                }

                if (await markRead(conversationId)) {
                    loadConversations();
                }
            };

            const selectConversation = (conversationId) => {
                state.selectedId = conversationId;
                renderConversations();
                loadMessages(conversationId);
            };

            conversationsEl.addEventListener('click', (event) => {
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

            loadConversations({ selectFirst: true });
        })();
    </script>
@endsection
