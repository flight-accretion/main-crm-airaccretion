@extends('admin.layouts.header')

@section('content')
    @php
        $filters = $filters ?? [];
        $operationUsers = $operationUsers ?? collect();
        $caseStatuses = $caseStatuses ?? [];
        $tableId = 'operations-queue-table';
        $queueLabel = ucfirst(str_replace('_', ' ', $type));
    @endphp

    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3
                class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
                {{ $queueLabel }} Queue
            </h3>
            <p class="text-[0.75rem] text-gray-500 mt-1">
                Completing this queue item only closes the Operations case.
            </p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger mb-4">{{ session('error') }}</div>
    @endif

    @include('admin.pages.operations.partials.lead-filters', [
        'filterAction' => route('admin.operations.queue', $type),
        'dateLabel' => 'Opened',
    ])

    @include('admin.pages.operations.partials.case-table', [
        'mode' => 'queue',
        'listTitle' => $queueLabel . ' List',
        'emptyMessage' => 'No pending records.',
    ])

    @include('admin.pages.operations.partials.complete-modal')
    @include('admin.pages.operations.partials.lead-table-assets')
@endsection
