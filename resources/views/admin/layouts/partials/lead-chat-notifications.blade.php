@php

    $notificationUser =
        auth()->user();


    $notificationRole =
        $notificationUser
            ?->userType
            ?->user_type;


    $showChatNotification =

        /*
         * Super Admin
         */
        (
            $notificationUser
            &&
            $notificationUser->isSuperAdmin()
        )

        ||

        /*
         * Sales Executive
         */
        (
            $notificationRole
            ===
            \App\Models\UserType::SALES_EXECUTIVE
        )

        ||

        /*
         * Operations users
         */
        in_array(
            $notificationRole,
            \App\Models\UserType::OPERATIONS_ROLES,
            true
        );

@endphp


@if($showChatNotification)

<div
    id="lead-chat-notification-root"
    class="
        header-element
        py-[1rem]
        md:px-[0.65rem]
        px-2
        hs-dropdown
        ti-dropdown
        [--placement:bottom-right]
    "

    data-list-url="{{
        route(
            'admin.chat-notifications.index'
        )
    }}"

    data-read-all-url="{{
        route(
            'admin.chat-notifications.mark-all-read'
        )
    }}"

    data-clear-all-url="{{
        route(
            'admin.chat-notifications.clear-all'
        )
    }}"
>

    <button
        id="lead-chat-notification-button"
        type="button"
        class="
            hs-dropdown-toggle
            relative
            ti-dropdown-toggle
            !p-0
            !border-0
            !shadow-none
        "
    >

        <i
            class="bx bx-bell header-link-icon text-[1.125rem]"
        ></i>


        <span
            id="lead-chat-notification-badge"
            class="
                hidden
                absolute
                -top-1
                -right-2
                min-w-[18px]
                h-[18px]
                px-1
                rounded-full
                bg-danger
                text-white
                text-[10px]
                leading-[18px]
                text-center
            "
        >
            0
        </span>

    </button>


    <div
        class="
            main-header-dropdown
            hs-dropdown-menu
            ti-dropdown-menu
            bg-white
            !w-[25rem]
            border
            border-defaultborder
            hidden
            !m-0
            !p-0
        "
    >

        <div
            class="
                p-4
                flex
                justify-between
                items-center
                gap-3
                border-b
                border-defaultborder
            "
        >

            <div>

                <div
                    class="font-semibold text-[1rem]"
                >
                    Notifications
                </div>


                <div
                    id="lead-chat-notification-unread-label"
                    class="text-xs text-gray-500"
                >
                    0 unread
                </div>

            </div>


            <div class="flex gap-2">

                <button
                    type="button"
                    id="lead-chat-mark-all-read"
                    class="ti-btn ti-btn-sm ti-btn-light"
                    style="width:auto;"
                >
                    Mark all as read
                </button>


                <button
                    type="button"
                    id="lead-chat-clear-all"
                    class="ti-btn ti-btn-sm ti-btn-light"
                     style="width:auto;"
                >
                    Clear all
                </button>

            </div>

        </div>


        <div
            id="lead-chat-notification-list"
            class="max-h-[420px] overflow-y-auto"
        >

            <div
                class="p-5 text-center text-gray-400"
            >
                Loading...
            </div>

        </div>

    </div>

</div>


<script>
(function () {

    const root =
        document.getElementById(
            'lead-chat-notification-root'
        );


    if (!root) {
        return;
    }


    if (
        root.dataset.initialized
        ===
        '1'
    ) {
        return;
    }


    root.dataset.initialized =
        '1';


    const list =
        document.getElementById(
            'lead-chat-notification-list'
        );

    const badge =
        document.getElementById(
            'lead-chat-notification-badge'
        );

    const unreadLabel =
        document.getElementById(
            'lead-chat-notification-unread-label'
        );

    const markAllButton =
        document.getElementById(
            'lead-chat-mark-all-read'
        );

    const clearAllButton =
        document.getElementById(
            'lead-chat-clear-all'
        );


    /*
     * Controlled inline error.
     */

    const errorBox =
        document.createElement(
            'div'
        );


    errorBox.className =
        'hidden px-3 py-2 text-xs text-danger border-b border-defaultborder';


    list.parentNode.insertBefore(
        errorBox,
        list
    );


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


    function showError(
        message = ''
    ) {

        errorBox.textContent =
            message;


        if (
            message
        ) {

            errorBox
                .classList
                .remove(
                    'hidden'
                );

        } else {

            errorBox
                .classList
                .add(
                    'hidden'
                );
        }
    }


    /*
     * YouTube-style relative time.
     */

    function relativeTime(value)
    {
        if (
            !value
        ) {

            return '';
        }


        const created =
            new Date(value);


        if (
            Number.isNaN(
                created.getTime()
            )
        ) {

            return '';
        }


        const seconds =
            Math.max(
                0,
                Math.floor(
                    (
                        Date.now()
                        -
                        created.getTime()
                    )
                    /
                    1000
                )
            );


        if (
            seconds < 45
        ) {
            return 'Just now';
        }


        if (
            seconds < 3600
        ) {

            const minutes =
                Math.floor(
                    seconds / 60
                );


            return `${minutes}m ago`;
        }


        if (
            seconds < 86400
        ) {

            const hours =
                Math.floor(
                    seconds / 3600
                );


            return `${hours}h ago`;
        }


        const days =
            Math.floor(
                seconds / 86400
            );


        if (
            days === 1
        ) {

            return 'Yesterday';
        }


        if (
            days < 30
        ) {

            return `${days}d ago`;
        }


        return created.toLocaleDateString(
            'en-IN',
            {
                day:
                    '2-digit',

                month:
                    'short',
            }
        );
    }


    async function post(url)
    {
        const response =
            await fetch(
                url,
                {
                    method:
                        'POST',

                    headers: {

                        'Accept':
                            'application/json',

                        'X-CSRF-TOKEN':
                            csrfToken(),

                        'X-Requested-With':
                            'XMLHttpRequest',
                    },

                    credentials:
                        'same-origin',
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

            throw new Error(
                data.message
                ||
                'Notification action failed.'
            );
        }


        return data;
    }


    async function loadNotifications()
    {
        try {

            const response =
                await fetch(
                    root.dataset.listUrl,
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
                    'Unable to load notifications.'
                );
            }


            const data =
                await response.json();


            const unread =
                Number(
                    data.unread_count
                    ||
                    0
                );


            unreadLabel.textContent =
                `${unread} unread`;


            if (
                unread > 0
            ) {

                badge.textContent =
                    unread > 99
                        ? '99+'
                        : unread;


                badge.classList
                    .remove(
                        'hidden'
                    );

            } else {

                badge.classList
                    .add(
                        'hidden'
                    );
            }


            const notifications =
                data.notifications
                ||
                [];


            if (
                !notifications.length
            ) {

                list.innerHTML = `

                    <div
                        class="
                            p-8
                            text-center
                            text-gray-400
                        "
                    >
                        No notifications.
                    </div>
                `;


                showError('');


                return;
            }


            list.innerHTML =
                notifications
                    .map(
                        item => {

                            const unreadBackground =
                                item.is_read

                                    ? ''

                                    : 'bg-primary/5';


                            const dot =
                                item.is_read

                                    ? ''

                                    : `

                                        <span
                                            class="
                                                inline-block
                                                w-2
                                                h-2
                                                rounded-full
                                                bg-primary
                                                mt-2
                                            "
                                        ></span>
                                    `;


                            return `

                                <div
                                    class="
                                        ${unreadBackground}
                                        border-b
                                        border-defaultborder
                                        p-3
                                    "
                                >

                                    <div
                                        class="
                                            flex
                                            gap-3
                                        "
                                    >

                                        <div>
                                            ${dot}
                                        </div>


                                        <a
                                            href="${escapeHtml(item.open_url)}"
                                            class="
                                                grow
                                                min-w-0
                                            "
                                        >

                                            <div
                                                class="
                                                    text-sm
                                                    font-semibold
                                                "
                                            >
                                                ${escapeHtml(item.title)}
                                            </div>


                                            <div
                                                class="
                                                    text-xs
                                                    font-medium
                                                    mt-1
                                                "
                                            >
                                                ${escapeHtml(item.customer_name)}
                                            </div>


                                            <div
                                                class="
                                                    text-xs
                                                    text-gray-500
                                                    mt-1
                                                    line-clamp-2
                                                "
                                            >
                                                ${escapeHtml(item.body || '')}
                                            </div>


                                            <div
                                                class="
                                                    text-[11px]
                                                    text-gray-400
                                                    mt-1
                                                "
                                            >
                                                ${escapeHtml(relativeTime(item.created_at))}
                                            </div>

                                        </a>


                                        <div
                                            class="
                                                flex
                                                flex-col
                                                gap-1
                                                shrink-0
                                            "
                                        >

                                            ${
                                                !item.is_read

                                                ? `

                                                    <button
                                                        type="button"
                                                        class="
                                                            chat-notification-read
                                                            text-xs
                                                            text-primary
                                                        "
                                                        data-url="${escapeHtml(item.read_url)}"
                                                    >
                                                        Read
                                                    </button>

                                                `

                                                : ''
                                            }


                                            <button
                                                type="button"
                                                class="
                                                    chat-notification-clear
                                                    text-xs
                                                    text-gray-500
                                                "
                                                data-url="${escapeHtml(item.clear_url)}"
                                            >
                                                Clear
                                            </button>

                                        </div>

                                    </div>

                                </div>
                            `;
                        }
                    )
                    .join('');


            showError('');


        } catch (error) {

            console.error(
                'Chat notification load failed:',
                error
            );


            showError(
                error.message
                ||
                'Unable to load notifications.'
            );
        }
    }


    /*
     * Individual actions
     */

    list.addEventListener(
        'click',
        async function (event) {

            const readButton =
                event.target.closest(
                    '.chat-notification-read'
                );


            const clearButton =
                event.target.closest(
                    '.chat-notification-clear'
                );


            if (
                !readButton
                &&
                !clearButton
            ) {

                return;
            }


            event.preventDefault();

            event.stopPropagation();


            const button =
                readButton
                ||
                clearButton;


            button.disabled =
                true;


            try {

                await post(
                    button.dataset.url
                );


                await loadNotifications();


            } catch (error) {

                console.error(
                    'Notification action failed:',
                    error
                );


                showError(
                    error.message
                    ||
                    'Notification action failed.'
                );


            } finally {

                button.disabled =
                    false;
            }
        }
    );


    /*
     * Mark all read
     */

    markAllButton.addEventListener(
        'click',
        async function () {

            markAllButton.disabled =
                true;


            try {

                await post(
                    root.dataset.readAllUrl
                );


                await loadNotifications();


            } catch (error) {

                console.error(
                    'Mark all read failed:',
                    error
                );


                showError(
                    error.message
                    ||
                    'Unable to mark notifications as read.'
                );


            } finally {

                markAllButton.disabled =
                    false;
            }
        }
    );


    /*
     * Clear all
     */

    clearAllButton.addEventListener(
        'click',
        async function () {

            if (
                !confirm(
                    'Clear all notifications? Chat messages and tasks will remain.'
                )
            ) {

                return;
            }


            clearAllButton.disabled =
                true;


            try {

                await post(
                    root.dataset.clearAllUrl
                );


                await loadNotifications();


            } catch (error) {

                console.error(
                    'Clear all failed:',
                    error
                );


                showError(
                    error.message
                    ||
                    'Unable to clear notifications.'
                );


            } finally {

                clearAllButton.disabled =
                    false;
            }
        }
    );


    loadNotifications();


    window.setInterval(
        loadNotifications,
        10000
    );

})();

</script>

@endif