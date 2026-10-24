@php($navigationPendingCount = app(\App\Services\SidebarPendingCounts::class)->countFor(auth()->user(), $queue))
@if ($navigationPendingCount > 0)
    <span class="badge bg-danger/10 text-danger ms-auto crm-nav-count" data-pending-queue="{{ $queue }}"
        aria-label="{{ $navigationPendingCount }} pending" title="{{ $navigationPendingCount }} pending">{{ $navigationPendingCount }}</span>
@endif
