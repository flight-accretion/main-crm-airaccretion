@extends('admin.layouts.header')

@section('content')
    @php
        $filters = $filters ?? [];
        $operationUsers = $operationUsers ?? collect();
        $caseTypes = $caseTypes ?? [];
        $caseStatuses = $caseStatuses ?? [];
        $tableId = 'operations-history-table';
    @endphp

    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3
                class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
                Work Done
            </h3>
            <p class="text-[0.75rem] text-gray-500 mt-1">
                Completed Operations cases.
            </p>
        </div>
    </div>

    @include('admin.pages.operations.partials.lead-filters', [
        'filterAction' => route('admin.operations.history'),
        'dateLabel' => 'Completed',
    ])

    @include('admin.pages.operations.partials.case-table', [
        'mode' => 'history',
        'listTitle' => 'Work Done List',
        'emptyMessage' => 'No completed Operations cases.',
    ])

    @include('admin.pages.operations.partials.lead-table-assets')
@endsection
