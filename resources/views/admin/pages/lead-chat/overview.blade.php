@extends('admin.layouts.header')

@section('content')
<style>
    .lead-chat-monitor { max-width: 1180px; margin: 0 auto; padding: 26px 18px 70px; }
    .lead-chat-monitor__head { display:flex; justify-content:space-between; align-items:flex-end; gap:16px; margin-bottom:20px; }
    .lead-chat-monitor__head h1 { margin:0; font-size:24px; color:#102a43; font-weight:700; }
    .lead-chat-monitor__head p { margin:5px 0 0; color:#718096; font-size:13px; }
    .lead-chat-monitor__notice { border:1px solid #d8e7fb; background:#edf5ff; color:#355b85; border-radius:10px; padding:10px 13px; font-size:12px; }
    .lead-chat-monitor__filters { display:flex; gap:10px; align-items:center; padding:14px; margin-bottom:14px; background:#fff; border:1px solid #e1e8f0; border-radius:12px; }
    .lead-chat-monitor__filters input { flex:1; min-width:180px; height:40px; border:1px solid #d8e1eb; border-radius:8px; padding:0 12px; }
    .lead-chat-monitor__filters label { display:flex; align-items:center; gap:7px; color:#43566e; font-size:13px; white-space:nowrap; }
    .lead-chat-monitor__filters button { height:40px; border:0; border-radius:8px; padding:0 16px; background:#12395b; color:#fff; font-weight:600; }
    .lead-chat-monitor__list { overflow:hidden; background:#fff; border:1px solid #e1e8f0; border-radius:12px; }
    .lead-chat-monitor__row { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(0,1.7fr) 125px 125px; align-items:center; gap:16px; padding:16px 18px; border-bottom:1px solid #edf1f5; }
    .lead-chat-monitor__row:last-child { border-bottom:0; }
    .lead-chat-monitor__name { display:block; color:#102a43; font-weight:700; text-decoration:none; }
    .lead-chat-monitor__meta, .lead-chat-monitor__message { margin-top:4px; color:#74849a; font-size:12px; }
    .lead-chat-monitor__message { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .lead-chat-monitor__time { color:#5d6e82; font-size:12px; }
    .lead-chat-monitor__task { display:inline-flex; align-items:center; justify-content:center; min-height:28px; padding:4px 9px; border-radius:999px; background:#fff4dd; color:#a56300; font-size:12px; font-weight:700; }
    .lead-chat-monitor__empty { padding:44px 20px; color:#718096; text-align:center; }
    @media (max-width: 760px) { .lead-chat-monitor { padding:18px 12px 90px; } .lead-chat-monitor__head { display:block; } .lead-chat-monitor__notice { margin-top:12px; } .lead-chat-monitor__filters { flex-wrap:wrap; } .lead-chat-monitor__filters input { width:100%; flex-basis:100%; } .lead-chat-monitor__row { grid-template-columns:1fr auto; gap:9px; } .lead-chat-monitor__message { grid-column:1 / -1; } .lead-chat-monitor__time { grid-column:1; } .lead-chat-monitor__task { grid-column:2; grid-row:1; } }
</style>

<main class="lead-chat-monitor">
    <header class="lead-chat-monitor__head">
        <div>
            <h1>Lead Chat</h1>
            <p>Read-only oversight of Sales ↔ Operations conversations, activity, and open tasks.</p>
        </div>
        <div class="lead-chat-monitor__notice">Open a conversation to view its lead workspace. Owner actions remain read-only.</div>
    </header>

    <form class="lead-chat-monitor__filters" method="GET" action="{{ route('admin.lead-chat.overview') }}">
        <input type="search" name="search" value="{{ $search }}" placeholder="Search by client name or lead ID" aria-label="Search lead chat">
        <label><input type="checkbox" name="state" value="needs_attention" {{ $needsAttention ? 'checked' : '' }}> Open tasks only</label>
        <button type="submit">Filter</button>
    </form>

    <section class="lead-chat-monitor__list" aria-label="Lead conversations">
        @forelse($conversations as $conversation)
            @php($lead = $conversation->lead)
            <article class="lead-chat-monitor__row">
                <div>
                    <a class="lead-chat-monitor__name" href="{{ route('admin.leads.follow-up.create', $conversation->lead_id) }}?chat=1#lead-chat-panel">
                        {{ $lead?->client?->name ?: 'Unnamed lead' }}
                    </a>
                    <div class="lead-chat-monitor__meta">{{ $lead?->representative?->name ?: 'Unassigned sales representative' }}</div>
                </div>
                <div class="lead-chat-monitor__message">{{ $conversation->latest_message_body ?: 'No messages recorded yet.' }}</div>
                <div class="lead-chat-monitor__time">{{ $conversation->last_message_at?->format('d M Y, h:i A') ?: 'No recent activity' }}</div>
                <div><span class="lead-chat-monitor__task">{{ $conversation->active_tasks_count }} open {{ $conversation->active_tasks_count === 1 ? 'task' : 'tasks' }}</span></div>
            </article>
        @empty
            <div class="lead-chat-monitor__empty">No lead conversations match these filters.</div>
        @endforelse
    </section>

    @if($conversations->hasPages())
        <div class="mt-4">{{ $conversations->links() }}</div>
    @endif
</main>
@endsection
