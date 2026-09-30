@php

    $leadChatUser = auth()->user();

    $leadChatRole = $leadChatUser
        ?->userType
        ?->user_type;


    $leadChatAllowed =

        /*
         * Super Admin
         */
        (
            $leadChatUser
            &&
            $leadChatUser->isSuperAdmin()
        )

        ||

        /*
         * Sales Executive - own Lead only
         */
        (
            $leadChatRole
            ===
            \App\Models\UserType::SALES_EXECUTIVE

            &&

            (string) $lead->representative_user_id
            ===
            (string) $leadChatUser?->id
        )

        ||

        /*
         * Operations
         */
        in_array(
            $leadChatRole,
            \App\Models\UserType::OPERATIONS_ROLES,
            true
        );

@endphp


@if($leadChatAllowed)

<div
    id="lead-chat-panel"
    class="box custom-box h-full"

    data-load-url="{{ route('admin.lead-chat.index', $lead) }}"
    data-send-url="{{ route('admin.lead-chat.store', $lead) }}"
    data-task-url="{{ route('admin.lead-chat.task.store', $lead) }}"
    data-google-retry-base="{{ url('/admin/lead-chat/lead/'.$lead->id.'/google/retry') }}"

    data-message-base="{{ url('/admin/lead-chat/messages') }}"
    data-task-base="{{ url('/admin/lead-chat/tasks') }}"

    data-highlight-message="{{ request('message') }}"
    data-highlight-task="{{ request('task') }}"
>

    <div
        class="
            box-header
            !py-3
            flex
            items-center
            justify-between
            gap-3
        "
    >

        <div>

            <h5 class="box-title mb-0">
                Sales ↔ Operations Chat
            </h5>

            <div class="text-xs text-gray-500 mt-1">
                Internal conversation for this Lead
            </div>

        </div>


        <div class="flex items-center gap-2 flex-wrap">

            <span
                id="lead-chat-task-count"
                class="badge bg-warning/10 text-warning"
            >
                0 Active Tasks
            </span>

            <button
                type="button"
                id="lead-chat-pinned-button"
                class="badge bg-primary/10 text-primary cursor-pointer"
            >
                <span id="lead-chat-pinned-count">0</span>
                Pinned
            </button>

        </div>

    </div>


    <div class="box-body !p-0">

        @include('admin.pages.follow-ups.partials.google-chat-connection', ['lead' => $lead])

        {{-- Search --}}
        <div class="p-3 border-b border-defaultborder">

            <div class="input-group">

                <div class="input-group-text">
                    <i class="ri-search-line"></i>
                </div>

                <input
                    type="text"
                    id="lead-chat-search"
                    class="form-control form-control-sm"
                    placeholder="Search this conversation..."
                >

            </div>

        </div>


        {{-- Messages --}}
        <div
            id="lead-chat-messages"
            class="
                overflow-y-auto
                bg-gray-50
                dark:bg-bodybg
                p-3
            "
            style="height: 200px;"
        >

            <div class="text-center text-gray-400 py-10">
                Loading conversation...
            </div>

        </div>


        {{-- Reply Preview --}}
        <div
            id="lead-chat-reply-box"
            class="
                hidden
                mx-3
                mt-2
                p-2
                rounded-sm
                bg-gray-100
                dark:bg-black/20
                border
                border-defaultborder
            "
        >

            <div class="flex justify-between gap-3">

                <div class="min-w-0">

                    <div class="text-xs font-semibold">

                        Replying to

                        <span id="lead-chat-reply-user"></span>

                    </div>

                    <div
                        id="lead-chat-reply-text"
                        class="text-xs text-gray-500 truncate mt-1"
                    ></div>

                </div>


                <button
                    type="button"
                    id="lead-chat-reply-cancel"
                    class="text-gray-500"
                    aria-label="Cancel reply"
                     style="width:auto;"
                >

                    <i class="ri-close-line"></i>

                </button>

            </div>

        </div>


        {{-- Task Creator --}}
        <div
            id="lead-chat-task-form"
            class="
                hidden
                mx-3
                mt-3
                p-3
                border
                border-defaultborder
                rounded-sm
                bg-white
                dark:bg-bodybg
            "
        >

            <div class="font-semibold text-sm mb-3">
                Create Task
            </div>


            <div class="grid grid-cols-12 gap-3">

                <div class="col-span-12">

                    <label class="form-label">
                        Task Title
                    </label>

                    <input
                        type="text"
                        id="lead-chat-task-title"
                        class="ti-form-input form-control-sm"
                        maxlength="255"
                        placeholder="Enter task title"
                    >

                </div>


                <div class="col-span-12">

                    <label class="form-label">
                        Description
                    </label>

                    <textarea
                        id="lead-chat-task-description"
                        class="ti-form-input form-control-sm"
                        rows="2"
                        maxlength="3000"
                        placeholder="Task details (optional)"
                    ></textarea>

                </div>


                <div class="sm:col-span-6 col-span-12">

                    <label class="form-label">
                        Priority
                    </label>

                    <select
                        id="lead-chat-task-priority"
                        class="ti-form-select form-control-sm"
                    >

                        <option value="normal">
                            Normal
                        </option>

                        <option value="low">
                            Low
                        </option>

                        <option value="high">
                            High
                        </option>

                        <option value="urgent">
                            Urgent
                        </option>

                    </select>

                </div>


                <div class="sm:col-span-6 col-span-12">

                    <label class="form-label">
                        Due Date
                    </label>

                    <input
                        type="datetime-local"
                        id="lead-chat-task-due"
                        class="ti-form-input form-control-sm"
                    >

                </div>

            </div>


            <div class="flex justify-end gap-2 mt-3">

                <button
                    type="button"
                    id="lead-chat-task-cancel"
                    class="ti-btn ti-btn-sm ti-btn-light"
                    style="width:auto;"
                >
                    Cancel
                </button>

                <button
                    type="button"
                    id="lead-chat-task-create"
                    class="ti-btn ti-btn-sm ti-btn-primary"
                     style="width:auto;"
                >
                    Create Task
                </button>

            </div>

        </div>


        {{-- Composer --}}
        {{-- IMPORTANT: DIV, not FORM. Prevents nested form issue. --}}
        <div
            id="lead-chat-composer"
            class="p-3 border-t border-defaultborder bg-white dark:bg-bodybg"
        >

            <textarea
                id="lead-chat-input"
                class="ti-form-input form-control"
                rows="2"
                maxlength="5000"
                placeholder="Type message to Sales / Operations..."
            ></textarea>


            <input
                type="file"
                id="lead-chat-files"
                class="hidden"
                multiple
                accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.txt"
            >


            <div
                id="lead-chat-file-preview"
                class="hidden text-xs text-gray-500 mt-2"
            ></div>


            <div
                id="lead-chat-error"
                class="hidden text-danger text-xs mt-2"
            ></div>


            <div
                class="
                    flex
                    justify-between
                    items-center
                    gap-3
                    mt-2
                "
            >

                <div class="flex gap-2">

                    <button
                        type="button"
                        id="lead-chat-attach"
                        class="ti-btn ti-btn-sm ti-btn-light"
                        style="width: auto;"
                    >
                        <i class="ri-attachment-2"></i>
                        Attach
                    </button>


                    <button
                        type="button"
                        id="lead-chat-task-toggle"
                        class="ti-btn ti-btn-sm ti-btn-light"
                         style="width: auto;"
                    >
                        <i class="ri-task-line"></i>
                        Task
                    </button>

                </div>


                <button
                    type="button"
                    id="lead-chat-send"
                    class="ti-btn ti-btn-sm ti-btn-primary"
                     style="width: auto;"
                >
                    <i class="ri-send-plane-2-line"></i>
                    Send
                </button>

            </div>

        </div>

    </div>

</div>


<script>
(function () {

    const root =
        document.getElementById(
            'lead-chat-panel'
        );


    if (!root) {
        return;
    }


    /*
     * Prevent accidental double initialization.
     */

    if (
        root.dataset.initialized
        ===
        '1'
    ) {
        return;
    }


    root.dataset.initialized =
        '1';


    const messagesEl =
        document.getElementById(
            'lead-chat-messages'
        );

    const input =
        document.getElementById(
            'lead-chat-input'
        );

    const sendButton =
        document.getElementById(
            'lead-chat-send'
        );

    const attachButton =
        document.getElementById(
            'lead-chat-attach'
        );

    const fileInput =
        document.getElementById(
            'lead-chat-files'
        );

    const filePreview =
        document.getElementById(
            'lead-chat-file-preview'
        );

    const errorEl =
        document.getElementById(
            'lead-chat-error'
        );

    const searchInput =
        document.getElementById(
            'lead-chat-search'
        );

    const pinnedButton =
        document.getElementById(
            'lead-chat-pinned-button'
        );

    const pinnedCount =
        document.getElementById(
            'lead-chat-pinned-count'
        );

    const taskCount =
        document.getElementById(
            'lead-chat-task-count'
        );


    const replyBox =
        document.getElementById(
            'lead-chat-reply-box'
        );

    const replyUser =
        document.getElementById(
            'lead-chat-reply-user'
        );

    const replyText =
        document.getElementById(
            'lead-chat-reply-text'
        );

    const replyCancel =
        document.getElementById(
            'lead-chat-reply-cancel'
        );


    const taskForm =
        document.getElementById(
            'lead-chat-task-form'
        );

    const taskToggle =
        document.getElementById(
            'lead-chat-task-toggle'
        );

    const taskCancel =
        document.getElementById(
            'lead-chat-task-cancel'
        );

    const taskCreate =
        document.getElementById(
            'lead-chat-task-create'
        );

    const taskTitle =
        document.getElementById(
            'lead-chat-task-title'
        );

    const taskDescription =
        document.getElementById(
            'lead-chat-task-description'
        );

    const taskPriority =
        document.getElementById(
            'lead-chat-task-priority'
        );

    const taskDue =
        document.getElementById(
            'lead-chat-task-due'
        );


    const loadUrl =
        root.dataset.loadUrl;

    const sendUrl =
        root.dataset.sendUrl;

    const taskUrl =
        root.dataset.taskUrl;

    const messageBase =
        root.dataset.messageBase;

    const taskBase =
        root.dataset.taskBase;


    let messages = [];

    let replyTo =
        null;

    let showPinnedOnly =
        false;

    let sending =
        false;

    let loading =
        false;

    let creatingTask =
        false;

    let lastSignature =
        '';

    let targetHighlighted =
        false;


    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    function csrfToken()
    {
        return document
            .querySelector(
                'meta[name="csrf-token"]'
            )
            ?.getAttribute(
                'content'
            )
            || '';
    }


    function escapeHtml(value)
    {
        return String(
            value ?? ''
        )

            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }


    function nl2br(value)
    {
        return escapeHtml(
            value
        )
            .replace(
                /\n/g,
                '<br>'
            );
    }


    function formatDate(value)
    {
        if (!value) {
            return '';
        }


        const date =
            new Date(value);


        if (
            Number.isNaN(
                date.getTime()
            )
        ) {
            return '';
        }


        return date.toLocaleString(
            'en-IN',
            {
                day:
                    '2-digit',

                month:
                    'short',

                hour:
                    '2-digit',

                minute:
                    '2-digit',
            }
        );
    }


    function formatFileSize(bytes)
    {
        bytes =
            Number(
                bytes || 0
            );


        if (
            bytes < 1024
        ) {
            return `${bytes} B`;
        }


        if (
            bytes < 1024 * 1024
        ) {
            return `${Math.round(bytes / 1024)} KB`;
        }


        return `${(
            bytes
            /
            (1024 * 1024)
        ).toFixed(1)} MB`;
    }


    function showError(message = '')
    {
        errorEl.textContent =
            message;


        if (message) {

            errorEl
                .classList
                .remove(
                    'hidden'
                );

        } else {

            errorEl
                .classList
                .add(
                    'hidden'
                );
        }
    }


    /*
     * Makes frontend tolerant of both the
     * existing and newer response property names.
     */

    function normaliseMessage(raw)
    {
        const mine =
            Boolean(
                raw.is_mine
                ??
                raw.mine
                ??
                false
            );


        const deleted =
            Boolean(
                raw.is_deleted
                ??
                raw.deleted
                ??
                false
            );


        const type =
            raw.message_type
            ??
            raw.type
            ??
            'text';


        return {
            syncStatus: raw.google_sync_status || '',

            id:
                raw.id,

            type:
                type,

            body:
                raw.body
                ??
                '',

            senderName:
                raw.sender?.name
                ??
                raw.sender_name
                ??
                'System',

            senderRole:
                raw.sender?.role
                ??
                raw.sender_role
                ??
                '',

            mine:
                mine,

            deleted:
                deleted,

            edited:
                Boolean(
                    raw.is_edited
                    ??
                    raw.edited
                    ??
                    false
                ),

            pinned:
                Boolean(
                    raw.is_pinned
                    ??
                    raw.pinned
                    ??
                    false
                ),

            createdAt:
                raw.created_at,

            replyTo:
                raw.reply_to
                ? {

                    id:
                        raw.reply_to.id,

                    sender:
                        raw.reply_to.sender_name
                        ??
                        raw.reply_to.sender
                        ??
                        'User',

                    body:
                        raw.reply_to.body
                        ??
                        '',
                }
                : null,

            attachments:
                (
                    raw.attachments
                    ||
                    []
                )
                .map(
                    file => ({

                        id:
                            file.id,

                        name:
                            file.file_name
                            ??
                            file.name
                            ??
                            'Attachment',

                        mime:
                            file.mime_type
                            ??
                            file.mime
                            ??
                            '',

                        size:
                            file.size_bytes
                            ??
                            file.size
                            ??
                            0,

                        url:
                            file.url,
                    })
                ),

            task:
                raw.task
                ??
                null,

            reactions:
                raw.reactions
                ??
                [],

            canEdit:
                Boolean(
                    raw.can_edit
                    ??
                    (
                        mine
                        &&
                        type === 'text'
                        &&
                        !deleted
                    )
                ),

            canDelete:
                Boolean(
                    raw.can_delete
                    ??
                    (
                        mine
                        &&
                        type === 'text'
                        &&
                        !deleted
                    )
                ),
        };
    }


    async function jsonRequest(
        url,
        method = 'POST',
        body = null
    ) {

        const response =
            await fetch(
                url,
                {
                    method:
                        method,

                    headers: {

                        'Accept':
                            'application/json',

                        'Content-Type':
                            'application/json',

                        'X-CSRF-TOKEN':
                            csrfToken(),

                        'X-Requested-With':
                            'XMLHttpRequest',
                    },

                    credentials:
                        'same-origin',

                    body:
                        body === null
                            ? null
                            : JSON.stringify(body),
                }
            );


        const data =
            await response
                .json()
                .catch(
                    () => ({})
                );


        if (
            !response.ok
        ) {

            const validation =
                data.errors

                    ? Object.values(
                        data.errors
                    )
                        .flat()
                        .join(' ')

                    : null;


            throw new Error(
                validation
                ||
                data.message
                ||
                'Request failed.'
            );
        }


        return data;
    }


    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    */

    function renderAttachments(message)
    {
        if (
            !message
                .attachments
                .length
        ) {

            return '';
        }


        return `

            <div class="mt-2 space-y-2">

                ${
                    message.attachments
                        .map(
                            file => {

                                const isImage =
                                    String(
                                        file.mime
                                        || ''
                                    )
                                    .startsWith(
                                        'image/'
                                    );


                                if (
                                    isImage
                                ) {

                                    return `

                                        <a
                                            href="${escapeHtml(file.url)}"
                                            target="_blank"
                                            class="block"
                                        >

                                            <img
                                                src="${escapeHtml(file.url)}"
                                                alt="${escapeHtml(file.name)}"
                                                class="
                                                    max-w-[150px]
                                                    max-h-[150px]
                                                    rounded
                                                    border
                                                    border-defaultborder
                                                "
                                                loading="lazy"
                                            >

                                        </a>
                                    `;
                                }


                                return `

                                    <a
                                        href="${escapeHtml(file.url)}"
                                        target="_blank"
                                        class="
                                            flex
                                            items-center
                                            gap-2
                                            text-xs
                                            underline
                                        "
                                    >

                                        <i class="ri-file-line"></i>

                                        <span>
                                            ${escapeHtml(file.name)}
                                        </span>

                                        ${
                                            file.size
                                            ? `
                                                <span class="opacity-60">
                                                    ${escapeHtml(formatFileSize(file.size))}
                                                </span>
                                            `
                                            : ''
                                        }

                                    </a>
                                `;
                            }
                        )
                        .join('')
                }

            </div>
        `;
    }


    function renderTask(message)
    {
        const task =
            message.task;


        if (!task) {
            return '';
        }


        const active =
            task.status
            ===
            'active';


        const badgeClass =
            active

                ? 'bg-warning/10 text-warning'

                : 'bg-success/10 text-success';


        return `

            <div
                class="
                    lead-chat-task-card
                    mt-2
                    border
                    border-defaultborder
                    rounded-sm
                    p-3
                    bg-white
                    dark:bg-bodybg
                "
                data-task-id="${escapeHtml(task.id)}"
            >

                <div
                    class="
                        flex
                        justify-between
                        items-start
                        gap-3
                    "
                >

                    <div class="font-semibold text-sm">
                        ${escapeHtml(task.title || '')}
                    </div>

                    <span class="badge ${badgeClass}">
                        ${escapeHtml(task.status || '')}
                    </span>

                </div>


                ${
                    task.description
                    ? `
                        <div class="text-xs text-gray-600 mt-2">
                            ${nl2br(task.description)}
                        </div>
                    `
                    : ''
                }


                <div
                    class="
                        grid
                        grid-cols-2
                        gap-2
                        mt-3
                        text-[11px]
                        text-gray-500
                    "
                >

                    <div>
                        Priority:
                        ${escapeHtml(task.priority || 'normal')}
                    </div>

                    <div>
                        Assigned:
                        ${escapeHtml(task.assigned_role || '-')}
                    </div>

                    <div>
                        Due:
                        ${
                            task.due_at
                                ? escapeHtml(formatDate(task.due_at))
                                : 'No due date'
                        }
                    </div>

                </div>


                <div class="mt-3">

                    ${
                        active

                        ? `
                            <button
                                type="button"
                                class="
                                    lead-chat-task-action
                                    ti-btn
                                    ti-btn-sm
                                    ti-btn-success
                                "
                                data-task-id="${escapeHtml(task.id)}"
                                data-task-action="complete"
                                style="width:auto;"
                            >
                                Complete
                            </button>
                        `

                        : `
                            <button
                                type="button"
                                class="
                                    lead-chat-task-action
                                    ti-btn
                                    ti-btn-sm
                                    ti-btn-light
                                "
                                data-task-id="${escapeHtml(task.id)}"
                                data-task-action="reopen"
                                 style="width:auto;"
                            >
                                Reopen
                            </button>
                        `
                    }

                </div>

            </div>
        `;
    }


    function renderReactions(message)
    {
        const existing =
            message.reactions
            || [];


        const counts =
            existing
                .map(
                    reaction => `

                        <button
                            type="button"
                            class="
                                lead-chat-reaction
                                rounded-full
                                px-2
                                py-0.5
                                text-[11px]
                                ${
                                    reaction.mine
                                    ? 'bg-primary/10 text-primary'
                                    : 'bg-gray-100'
                                }
                            "
                            data-message-id="${escapeHtml(message.id)}"
                            data-reaction="${escapeHtml(reaction.reaction)}"
                        >
                            ${escapeHtml(reaction.reaction)}
                            ${Number(reaction.count || 0)}
                        </button>
                    `
                )
                .join('');


        const quick =
            ['👍', '❤️', '✅', '🙏', '😂']
                .map(
                    emoji => `

                        <button
                            type="button"
                            class="
                                lead-chat-reaction
                                text-[11px]
                                opacity-50
                                hover:opacity-100
                            "
                            data-message-id="${escapeHtml(message.id)}"
                            data-reaction="${emoji}"
                        >
                            ${emoji}
                        </button>
                    `
                )
                .join('');


        return counts
            +
            quick;
    }


    function renderMessages(
        forceBottom = false
    ) {

        const oldTop =
            messagesEl.scrollTop;


        const distanceFromBottom =
            messagesEl.scrollHeight
            -
            messagesEl.scrollTop
            -
            messagesEl.clientHeight;


        const wasNearBottom =
            distanceFromBottom
            <
            140;


        const search =
            searchInput
                .value
                .trim()
                .toLowerCase();


        let filtered =
            messages.slice();


        if (
            showPinnedOnly
        ) {

            filtered =
                filtered.filter(
                    message =>
                        message.pinned
                );
        }


        if (
            search
        ) {

            filtered =
                filtered.filter(
                    message => {

                        const text = [

                            message.body,

                            message.senderName,

                            message.senderRole,

                            message.task?.title,

                            message.task?.description,

                        ]
                            .filter(Boolean)
                            .join(' ')
                            .toLowerCase();


                        return text.includes(
                            search
                        );
                    }
                );
        }


        if (
            !filtered.length
        ) {

            messagesEl.innerHTML = `

                <div class="text-center text-gray-400 py-10">

                    ${
                        search
                        || showPinnedOnly

                            ? 'No matching messages.'

                            : 'No conversation yet.'
                    }

                </div>
            `;


            return;
        }


        messagesEl.innerHTML =
            filtered
                .map(
                    message => {

                        /*
                         * System messages
                         */

                        if (
                            message.type
                            ===
                            'system'
                        ) {

                            return `

                                <div
                                    id="lead-chat-message-${escapeHtml(message.id)}"
                                    class="
                                        lead-chat-message-row
                                        text-center
                                        py-2
                                    "
                                    data-message-id="${escapeHtml(message.id)}"
                                >

                                    <span
                                        class="
                                            text-[11px]
                                            text-gray-500
                                            bg-gray-100
                                            rounded-full
                                            px-3
                                            py-1
                                        "
                                    >
                                        ${escapeHtml(message.body)}
                                    </span>

                                </div>
                            `;
                        }


                        const alignment =
                            message.mine

                                ? 'justify-end'

                                : 'justify-start';


                        const bubble =
                            message.mine

                                ? 'bg-primary text-white'

                                : 'bg-white border border-defaultborder';


                        const reply =
                            message.replyTo

                                ? `

                                    <div
                                        class="
                                            mb-2
                                            p-2
                                            rounded
                                            bg-black/5
                                            text-[11px]
                                        "
                                    >

                                        <div class="font-semibold">
                                            ${escapeHtml(message.replyTo.sender)}
                                        </div>

                                        <div class="opacity-70 truncate">
                                            ${escapeHtml(message.replyTo.body)}
                                        </div>

                                    </div>
                                `

                                : '';


                        return `

                            <div
                                id="lead-chat-message-${escapeHtml(message.id)}"
                                class="
                                    lead-chat-message-row
                                    flex
                                    ${alignment}
                                    mb-3
                                "
                                data-message-id="${escapeHtml(message.id)}"
                            >

                                <div class="max-w-[86%]">

                                    <div
                                        class="
                                            flex
                                            items-center
                                            gap-2
                                            mb-1
                                            ${
                                                message.mine
                                                ? 'justify-end'
                                                : ''
                                            }
                                        "
                                    >

                                        <span
                                            class="
                                                text-[11px]
                                                font-semibold
                                                text-gray-600
                                            "
                                        >
                                            ${escapeHtml(message.senderName)}
                                        </span>

                                        ${
                                            message.senderRole

                                            ? `
                                                <span
                                                    class="
                                                        text-[10px]
                                                        text-gray-400
                                                    "
                                                >
                                                    ${escapeHtml(message.senderRole)}
                                                </span>
                                            `

                                            : ''
                                        }

                                        ${
                                            message.pinned

                                            ? `
                                                <i
                                                    class="
                                                        ri-pushpin-fill
                                                        text-primary
                                                        text-xs
                                                    "
                                                ></i>
                                            `

                                            : ''
                                        }

                                    </div>


                                    <div
                                        class="
                                            rounded-lg
                                            px-3
                                            py-2
                                            ${bubble}
                                        "
                                    >

                                        ${reply}


                                        ${
                                            message.type !== 'task'

                                            ? `

                                                <div
                                                    class="
                                                        text-sm
                                                        break-words
                                                    "
                                                    style="
                                                        white-space: pre-wrap;
                                                    "
                                                >${escapeHtml(message.body)}</div>

                                            `

                                            : ''
                                        }


                                        ${renderAttachments(message)}

                                        ${renderTask(message)}

                                        ${message.syncStatus && message.syncStatus !== 'not_required' ? `
                                            <div class="text-xs mt-1">Google: ${escapeHtml(message.syncStatus)}
                                                ${['pending', 'failed'].includes(message.syncStatus) ? `
                                                    <button type="button" class="lead-chat-google-retry ti-btn ti-btn-icon ti-btn-sm"
                                                        data-message-id="${escapeHtml(message.id)}" title="Retry Google delivery" aria-label="Retry Google delivery">
                                                        <i class="ri-refresh-line" aria-hidden="true"></i>
                                                    </button>` : ''}
                                            </div>` : ''}


                                        <div
                                            class="
                                                text-[10px]
                                                mt-2
                                                opacity-70
                                                text-right
                                            "
                                        >

                                            ${escapeHtml(formatDate(message.createdAt))}

                                            ${
                                                message.edited
                                                ? ' · edited'
                                                : ''
                                            }

                                        </div>

                                    </div>


                                    <div
                                        class="
                                            flex
                                            flex-wrap
                                            gap-1
                                            mt-1
                                            ${
                                                message.mine
                                                ? 'justify-end'
                                                : ''
                                            }
                                        "
                                    >

                                        ${renderReactions(message)}


                                        ${
                                            !message.deleted

                                            ? `

                                                <button
                                                    type="button"
                                                    class="
                                                        lead-chat-action
                                                        text-[11px]
                                                        text-gray-500
                                                    "
                                                    data-action="reply"
                                                    data-message-id="${escapeHtml(message.id)}"
                                                     style="width:auto;"
                                                >
                                                    Reply
                                                </button>

                                                <button
                                                    type="button"
                                                    class="
                                                        lead-chat-action
                                                        text-[11px]
                                                        text-gray-500
                                                    "
                                                    data-action="pin"
                                                    data-message-id="${escapeHtml(message.id)}"
                                                     style="width:auto;"
                                                >
                                                    ${
                                                        message.pinned
                                                            ? 'Unpin'
                                                            : 'Pin'
                                                    }
                                                </button>

                                            `

                                            : ''
                                        }


                                        ${
                                            message.canEdit

                                            ? `

                                                <button
                                                    type="button"
                                                    class="
                                                        lead-chat-action
                                                        text-[11px]
                                                        text-gray-500
                                                    "
                                                    data-action="edit"
                                                    data-message-id="${escapeHtml(message.id)}"
                                                     style="width:auto;"
                                                >
                                                    Edit
                                                </button>

                                            `

                                            : ''
                                        }


                                        ${
                                            message.canDelete

                                            ? `

                                                <button
                                                    type="button"
                                                    class="
                                                        lead-chat-action
                                                        text-[11px]
                                                        text-danger
                                                    "
                                                    data-action="delete"
                                                    data-message-id="${escapeHtml(message.id)}"
                                                     style="width:auto;"
                                                >
                                                    Delete
                                                </button>

                                            `

                                            : ''
                                        }

                                    </div>

                                </div>

                            </div>
                        `;
                    }
                )
                .join('');


        /*
         * Notification deep-link wins over normal scroll.
         */

        if (
            highlightTarget()
        ) {

            return;
        }


        if (
            forceBottom
            ||
            wasNearBottom
        ) {

            messagesEl.scrollTop =
                messagesEl.scrollHeight;

        } else {

            /*
             * Preserve old reading position.
             */

            messagesEl.scrollTop =
                oldTop;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Notification Target Highlight
    |--------------------------------------------------------------------------
    */

    function highlightTarget()
    {
        if (
            targetHighlighted
        ) {

            return false;
        }


        const messageId =
            root.dataset
                .highlightMessage;


        const taskId =
            root.dataset
                .highlightTask;


        let target =
            null;


        if (
            messageId
        ) {

            target =
                document.getElementById(
                    `lead-chat-message-${messageId}`
                );
        }


        if (
            !target
            &&
            taskId
        ) {

            target =
                messagesEl.querySelector(
                    `[data-task-id="${CSS.escape(taskId)}"]`
                );
        }


        if (
            !target
        ) {

            return false;
        }


        targetHighlighted =
            true;


        setTimeout(
            function () {

                target.scrollIntoView({
                    behavior:
                        'smooth',

                    block:
                        'center',
                });


                target.classList.add(
                    'ring-2',
                    'ring-warning',
                    'rounded-sm'
                );


                setTimeout(
                    function () {

                        target.classList.remove(
                            'ring-2',
                            'ring-warning',
                            'rounded-sm'
                        );

                    },
                    3500
                );

            },
            200
        );


        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | Load Messages
    |--------------------------------------------------------------------------
    */

    async function loadMessages()
    {
        if (
            loading
        ) {
            return;
        }


        loading =
            true;


        try {

            const response =
                await fetch(
                    loadUrl,
                    {
                        headers: {

                            'Accept':
                                'application/json',

                            'X-Requested-With':
                                'XMLHttpRequest',
                        },

                        credentials:
                            'same-origin',
                    }
                );


            if (
                !response.ok
            ) {

                throw new Error(
                    'Unable to load Lead chat.'
                );
            }


            const data =
                await response.json();


            const nextMessages =
                (
                    data.messages
                    ||
                    []
                )
                .map(
                    normaliseMessage
                );

            document.dispatchEvent(new CustomEvent('lead-chat-google-state', {detail: data.google_connection}));


            const signature =
                JSON.stringify(
                    nextMessages.map(
                        message => [

                            message.id,
                            message.syncStatus,

                            message.body,

                            message.edited,

                            message.deleted,

                            message.pinned,

                            message.task?.status,

                            message.reactions
                                .map(
                                    reaction => [

                                        reaction.reaction,

                                        reaction.count,

                                        reaction.mine,
                                    ]
                                ),
                        ]
                    )
                );


            messages =
                nextMessages;


            taskCount.textContent =
                `${Number(data.active_tasks || 0)} Active Tasks`;


            pinnedCount.textContent =
                Number(
                    data.pinned_count
                    || 0
                );


            if (
                signature
                !==
                lastSignature
            ) {

                lastSignature =
                    signature;


                renderMessages();
            }


            showError('');


        } catch (error) {

            showError(
                error.message
                ||
                'Unable to load Lead chat.'
            );


        } finally {

            loading =
                false;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Reply
    |--------------------------------------------------------------------------
    */

    function setReply(message)
    {
        replyTo =
            message;


        replyUser.textContent =
            message.senderName;


        replyText.textContent =
            message.body;


        replyBox
            .classList
            .remove(
                'hidden'
            );


        input.focus();
    }


    function clearReply()
    {
        replyTo =
            null;


        replyBox
            .classList
            .add(
                'hidden'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    function refreshFilePreview()
    {
        const files =
            Array.from(
                fileInput.files
                ||
                []
            );


        if (
            !files.length
        ) {

            filePreview.textContent =
                '';


            filePreview
                .classList
                .add(
                    'hidden'
                );


            return;
        }


        filePreview.textContent =
            files
                .map(
                    file =>
                        `${file.name} (${formatFileSize(file.size)})`
                )
                .join(' · ');


        filePreview
            .classList
            .remove(
                'hidden'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Send
    |--------------------------------------------------------------------------
    */

    async function sendMessage()
    {
        /*
         * Prevent double Enter / double click.
         */

        if (
            sending
        ) {
            return;
        }


        const body =
            input
                .value
                .trim();


        const files =
            Array.from(
                fileInput.files
                ||
                []
            );


        if (
            !body
            &&
            !files.length
        ) {

            return;
        }


        sending =
            true;


        sendButton.disabled =
            true;


        showError('');


        const formData =
            new FormData();


        formData.append(
            '_token',
            csrfToken()
        );


        formData.append(
            'message',
            body
        );


        if (
            replyTo
        ) {

            formData.append(
                'reply_to_message_id',
                replyTo.id
            );
        }


        files.forEach(
            file => {

                formData.append(
                    'attachments[]',
                    file
                );
            }
        );


        try {

            const response =
                await fetch(
                    sendUrl,
                    {
                        method:
                            'POST',

                        body:
                            formData,

                        credentials:
                            'same-origin',

                        headers: {

                            'Accept':
                                'application/json',

                            'X-Requested-With':
                                'XMLHttpRequest',
                        },
                    }
                );


            const data =
                await response
                    .json()
                    .catch(
                        () => ({})
                    );


            if (
                !response.ok
            ) {

                const validation =
                    data.errors

                        ? Object.values(
                            data.errors
                        )
                            .flat()
                            .join(' ')

                        : null;


                throw new Error(
                    validation
                    ||
                    data.message
                    ||
                    'Unable to send message.'
                );
            }


            input.value =
                '';


            fileInput.value =
                '';


            refreshFilePreview();

            clearReply();


            /*
             * Force next render so new message loads.
             */

            lastSignature =
                '';


            await loadMessages();


            /*
             * Own send must always scroll to newest message.
             */

            renderMessages(
                true
            );


        } catch (error) {

            showError(
                error.message
                ||
                'Unable to send message.'
            );


        } finally {

            sending =
                false;


            sendButton.disabled =
                false;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Task
    |--------------------------------------------------------------------------
    */

    async function createTask()
    {
        if (
            creatingTask
        ) {
            return;
        }


        const title =
            taskTitle
                .value
                .trim();


        if (
            !title
        ) {

            showError(
                'Task title is required.'
            );


            return;
        }


        creatingTask =
            true;


        taskCreate.disabled =
            true;


        try {

            await jsonRequest(
                taskUrl,
                'POST',
                {
                    title:
                        title,

                    description:
                        taskDescription
                            .value
                            .trim(),

                    priority:
                        taskPriority.value,

                    due_at:
                        taskDue.value
                        ||
                        null,
                }
            );


            taskTitle.value =
                '';

            taskDescription.value =
                '';

            taskPriority.value =
                'normal';

            taskDue.value =
                '';


            taskForm
                .classList
                .add(
                    'hidden'
                );


            lastSignature =
                '';


            await loadMessages();


            renderMessages(
                true
            );


        } catch (error) {

            showError(
                error.message
            );


        } finally {

            creatingTask =
                false;


            taskCreate.disabled =
                false;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    */

    sendButton.addEventListener(
        'click',
        sendMessage
    );


    input.addEventListener(
        'keydown',
        function (event) {

            /*
             * Enter = Send
             * Shift+Enter = newline
             */

            if (
                event.key
                ===
                'Enter'

                &&

                !event.shiftKey
            ) {

                event.preventDefault();


                if (
                    !sending
                ) {

                    sendMessage();
                }
            }
        }
    );


    attachButton.addEventListener(
        'click',
        function () {

            fileInput.click();
        }
    );


    fileInput.addEventListener(
        'change',
        refreshFilePreview
    );


    replyCancel.addEventListener(
        'click',
        clearReply
    );


    searchInput.addEventListener(
        'input',
        function () {

            renderMessages();
        }
    );


    pinnedButton.addEventListener(
        'click',
        function () {

            showPinnedOnly =
                !showPinnedOnly;


            pinnedButton.classList.toggle(
                'bg-primary',
                showPinnedOnly
            );


            pinnedButton.classList.toggle(
                'text-white',
                showPinnedOnly
            );


            renderMessages();
        }
    );


    taskToggle.addEventListener(
        'click',
        function () {

            taskForm
                .classList
                .toggle(
                    'hidden'
                );


            if (
                !taskForm
                    .classList
                    .contains(
                        'hidden'
                    )
            ) {

                taskTitle.focus();
            }
        }
    );


    taskCancel.addEventListener(
        'click',
        function () {

            taskForm
                .classList
                .add(
                    'hidden'
                );
        }
    );


    taskCreate.addEventListener(
        'click',
        createTask
    );


    /*
     * Message / Task delegation
     */

    messagesEl.addEventListener(
        'click',
        async function (event) {

            try {
                const googleRetry = event.target.closest('.lead-chat-google-retry');
                if (googleRetry) {
                    googleRetry.disabled = true;
                    try {
                        await jsonRequest(`${root.dataset.googleRetryBase}/${encodeURIComponent(googleRetry.dataset.messageId)}`);
                        await loadMessages();
                    } finally { googleRetry.disabled = false; }
                    return;
                }

                /*
                 * Complete / reopen task
                 */

                const taskAction =
                    event.target.closest(
                        '.lead-chat-task-action'
                    );


                if (
                    taskAction
                ) {

                    const taskId =
                        taskAction.dataset.taskId;


                    const action =
                        taskAction.dataset.taskAction;


                    const endpoint =
                        action === 'complete'

                            ? `${taskBase}/${taskId}/complete`

                            : `${taskBase}/${taskId}/reopen`;


                    taskAction.disabled =
                        true;


                    await jsonRequest(
                        endpoint,
                        'POST'
                    );


                    lastSignature =
                        '';


                    await loadMessages();


                    return;
                }


                /*
                 * Reaction
                 */

                const reactionButton =
                    event.target.closest(
                        '.lead-chat-reaction'
                    );


                if (
                    reactionButton
                ) {

                    await jsonRequest(

                        `${messageBase}/${reactionButton.dataset.messageId}/react`,

                        'POST',

                        {
                            reaction:
                                reactionButton.dataset.reaction,
                        }
                    );


                    lastSignature =
                        '';


                    await loadMessages();


                    return;
                }


                /*
                 * Message action
                 */

                const actionButton =
                    event.target.closest(
                        '.lead-chat-action'
                    );


                if (
                    !actionButton
                ) {

                    return;
                }


                const id =
                    actionButton.dataset.messageId;


                const action =
                    actionButton.dataset.action;


                const message =
                    messages.find(
                        item =>
                            item.id === id
                    );


                if (
                    !message
                ) {
                    return;
                }


                if (
                    action
                    ===
                    'reply'
                ) {

                    setReply(
                        message
                    );


                    return;
                }


                if (
                    action
                    ===
                    'pin'
                ) {

                    await jsonRequest(

                        `${messageBase}/${id}/pin`,

                        'POST'
                    );


                    lastSignature =
                        '';


                    await loadMessages();


                    return;
                }


                if (
                    action
                    ===
                    'edit'
                ) {

                    const updated =
                        prompt(
                            'Edit message',
                            message.body
                        );


                    if (
                        updated === null
                    ) {

                        return;
                    }


                    const clean =
                        updated.trim();


                    if (
                        !clean
                    ) {

                        return;
                    }


                    await jsonRequest(

                        `${messageBase}/${id}`,

                        'PATCH',

                        {
                            message:
                                clean,
                        }
                    );


                    lastSignature =
                        '';


                    await loadMessages();


                    return;
                }


                if (
                    action
                    ===
                    'delete'
                ) {

                    if (
                        !confirm(
                            'Delete this message? The audit record will remain.'
                        )
                    ) {

                        return;
                    }


                    await jsonRequest(

                        `${messageBase}/${id}`,

                        'DELETE'
                    );


                    lastSignature =
                        '';


                    await loadMessages();


                    return;
                }


            } catch (error) {

                showError(
                    error.message
                    ||
                    'Chat action failed.'
                );
            }
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Notification Deep Link
    |--------------------------------------------------------------------------
    */

    const query =
        new URLSearchParams(
            window.location.search
        );


    if (
        query.get('chat')
        ===
        '1'
    ) {

        setTimeout(
            function () {

                root.scrollIntoView({

                    behavior:
                        'smooth',

                    block:
                        'center',
                });

            },
            250
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Start
    |--------------------------------------------------------------------------
    */

    loadMessages();


    window.setInterval(
        loadMessages,
        4000
    );

})();

</script>

@endif
