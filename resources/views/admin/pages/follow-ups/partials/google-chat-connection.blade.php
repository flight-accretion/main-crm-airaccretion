@if(config('services.google_chat.enabled'))
<div id="google-chat-connection" class="p-3 border-b border-defaultborder"
    data-options="{{ route('admin.lead-chat.google.options', $lead) }}"
    data-connect="{{ route('admin.lead-chat.google.connect', $lead) }}">
    <div class="flex flex-wrap items-center gap-2">
        <i class="ri-chat-3-line" aria-hidden="true"></i>
        <span class="text-sm font-semibold">Google Chat</span>
        <span data-google-status class="text-xs text-gray-500" role="status">Loading...</span>
    </div>
    <div data-google-controls class="flex flex-wrap items-end gap-2 mt-2" hidden>
        <label class="text-xs" style="display:flex; flex-direction:column; gap:4px; flex:1 1 180px; min-width:0">Operations
            <select data-google-user class="form-control form-control-sm w-full" aria-label="Operations user"></select>
        </label>
        <label class="text-xs" style="display:flex; flex-direction:column; gap:4px; flex:1 1 180px; min-width:0">Space
            <select data-google-space class="form-control form-control-sm w-full" aria-label="Google Space"></select>
        </label>
        <button data-google-connect type="button" class="ti-btn ti-btn-primary" disabled>
            <i class="ri-link" aria-hidden="true"></i> Connect
        </button>
    </div>
    <div data-google-error class="text-xs text-danger mt-1" role="alert"></div>
</div>
<script>
(function () {
    const panel = document.getElementById('google-chat-connection');
    if (!panel) return;
    const controls = panel.querySelector('[data-google-controls]');
    const status = panel.querySelector('[data-google-status]');
    const error = panel.querySelector('[data-google-error]');
    const button = panel.querySelector('[data-google-connect]');
    const users = panel.querySelector('[data-google-user]');
    const spaces = panel.querySelector('[data-google-space]');
    let configured = false;
    const spaceLabels = new Map();
    function render(connection) {
        const state = connection?.google_connection_status || 'unmapped';
        status.textContent = {unmapped: 'Not connected', pending: 'Connecting...', ready: 'Connected', failed: 'Connection failed'}[state] || state;
        if (connection?.google_space_name && spaceLabels.has(connection.google_space_name)) {
            status.textContent += ' - ' + spaceLabels.get(connection.google_space_name);
        }
        controls.hidden = Boolean(connection?.google_space_name);
        controls.style.display = controls.hidden ? 'none' : '';
        button.disabled = !configured;
    }
    document.addEventListener('lead-chat-google-state', event => render(event.detail));
    fetch(panel.dataset.options, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
        .then(async response => {
            if (!response.ok) throw new Error('Unable to load Google Chat connection.');
            const data = await response.json();
            users.replaceChildren(new Option('Select Operations user', ''));
            spaces.replaceChildren(new Option('Select Space', ''));
            (data.users || []).forEach(user => users.add(new Option(user.name, user.id)));
            const choices = data.space_options || (data.spaces || []).map(id => ({id, name: id}));
            choices.forEach(space => {
                spaceLabels.set(space.id, space.name);
                spaces.add(new Option(space.name, space.id));
            });
            configured = Boolean(data.enabled && data.users?.length && data.spaces?.length);
            render(data.connection);
            error.textContent = '';
            if (!configured && !data.connection?.google_space_name) error.textContent = 'Google Chat setup is incomplete.';
        }).catch(e => {status.textContent = 'Unavailable'; error.textContent = e.message;});
    button.addEventListener('click', async () => {
        if (!users.value || !spaces.value) { error.textContent = 'Select an Operations user and Space.'; return; }
        button.disabled = true;
        error.textContent = '';
        try {
            const response = await fetch(panel.dataset.connect, {
                method: 'POST', credentials: 'same-origin', headers: {
                    'Accept': 'application/json', 'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }, body: JSON.stringify({operations_user_id: users.value, space_name: spaces.value})
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Google Chat connection failed.');
            render(data.connection);
        } catch (e) { error.textContent = e.message; }
        finally {button.disabled = !configured;}
    });
})();
</script>
@endif
