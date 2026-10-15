@extends('admin.layouts.header')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/targets/targets.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/responsive/targets-mobile.css') }}?v=1">
@php
    $months = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
    $periodLabel = $year || $month ? (($months[(int) $month] ?? 'All months').' '.($year ?: 'All years')) : 'Last 3 months';
    $totalTarget = $targets->sum('target_amount'); $totalAchieved = $targets->sum('achieved_amount');
    $totalIncentive = collect($incentiveRows)->sum('incentive');
    $elapsed = now()->day / now()->daysInMonth * 100;
    $initials = fn($name) => collect(preg_split('/\s+/u', trim($name)))->take(2)->map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
@endphp
<div class="crm-targets" data-base-url="{{ route('admin.targets.index') }}" data-open-create="{{ $errors->any() || $errors->getBag('add')->any() ? '1' : '0' }}">
    <header class="target-heading"><div><h1>Targets</h1><p>Sales targets vs achievement · {{ $periodLabel }}</p></div><div class="target-heading-actions">
        @if($canConfigure)<button type="button" class="target-button" data-open="target-export"><i class="ri-download-2-line" aria-hidden="true"></i>Download Incentive</button>@endif
        @if($canAssign)<button type="button" class="target-button primary" id="target-assign"><i class="ri-add-line" aria-hidden="true"></i>Assign Target</button>@endif
    </div></header>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
    @if($errors->any() || $errors->getBag('add')->any())<div class="alert alert-danger" role="alert">{{ $errors->first() ?: $errors->getBag('add')->first() }}</div>@endif
    <section class="target-filter-surface"><form method="GET" action="{{ route('admin.targets.index') }}" class="target-filter-grid">
        <label>Year<select name="year"><option value="">All Years</option>@for($i=2020;$i<=now()->year;$i++)<option value="{{ $i }}" {{ $year == $i ? 'selected' : '' }}>{{ $i }}</option>@endfor</select></label>
        <label>Month<select name="month"><option value="">All Months</option>@foreach($months as $key=>$label)<option value="{{ $key }}" {{ $month == $key ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></label>
        <label>Employee<select name="sales_executive_id"><option value="">All Employees</option>@foreach($assignableStaff as $staff)<option value="{{ $staff->id }}" {{ $salesExecutiveId == $staff->id ? 'selected' : '' }}>{{ $staff->name }} · {{ optional($staff->userType)->user_type }}</option>@endforeach</select></label>
        <button class="target-button primary" type="submit">Apply Filters</button>
    </form></section>
    <div class="target-stats">
        <article><strong>₹{{ number_format($totalTarget,2) }}</strong><span>Total target · {{ $periodLabel }}</span></article>
        <article><strong>₹{{ number_format($totalAchieved,2) }}</strong><span>Achieved · {{ $totalTarget > 0 ? round($totalAchieved/$totalTarget*100) : 0 }}% of target</span></article>
        <article><strong>{{ now()->day }} / {{ now()->daysInMonth }}</strong><span>{{ now()->format('M Y') }} · {{ round($elapsed) }}% elapsed</span></article>
        <article><strong>{{ $targets->where('status','active')->count() }}</strong><span>Active targets</span></article>
        <article><strong>₹{{ number_format($totalIncentive,2) }}</strong><span>Incentive · {{ $periodLabel }}</span></article>
    </div>
    <section class="target-table-surface"><div class="target-section-heading"><h2>Targets · {{ $periodLabel }}</h2><span>{{ $targets->count() + $unassignedStaff->count() }} records</span></div>
        <div class="target-table-tools"><input id="target-search" type="search" placeholder="Search employee or role" aria-label="Search targets"><label>Show<select id="target-page-size"><option>10</option><option selected>25</option><option>50</option><option value="all">All</option></select></label></div>
        <table class="target-table"><thead><tr>@foreach(['Person','Target','Achieved','Progress','Sales basis','Incentive'] as $column=>$heading)<th aria-sort="none"><button type="button" data-sort-column="{{ $column }}">{{ $heading }}<i class="ri-arrow-up-down-line" aria-hidden="true"></i></button></th>@endforeach<th>Action</th></tr></thead><tbody>
        @foreach($targets as $target)
            @php $result=$incentiveRows[$target->id]; $percent=$target->target_amount > 0 ? round($target->achieved_amount/$target->target_amount*100,2) : 0; @endphp
            <tr data-target-row>
                <td data-label="Person"><div class="target-person"><span class="target-avatar">{{ $initials($target->salesExecutive->name ?? '') }}</span><strong>{{ $target->salesExecutive->name ?? 'Unavailable employee' }}</strong></div><span class="target-meta">{{ optional(optional($target->salesExecutive)->userType)->user_type }} · {{ $target->month_name }} {{ $target->year }}</span><details class="target-details"><summary>Details</summary><p>Assigned by: {{ $target->assignedBy->name ?? 'N/A' }}</p><p>{{ $target->description }}</p><p>{{ ucfirst($target->status) }}</p></details></td>
                <td data-label="Target">₹{{ number_format($target->target_amount,2) }}</td><td data-label="Achieved">₹{{ number_format($target->achieved_amount,2) }}</td>
                <td data-label="Progress"><progress value="{{ min(100,max(0,$percent)) }}" max="100" aria-label="Target achievement"></progress><span class="target-meta">{{ $percent }}%</span><span class="target-tag {{ $target->status !== 'active' ? 'neutral' : ($percent >= 100 ? 'green' : 'blue') }}">{{ $target->status !== 'active' ? 'Inactive' : ($percent >= 100 ? 'Target met' : 'Active') }}</span></td>
                <td data-label="Sales basis">{{ $target->incentive_enabled ? ucfirst($target->incentive_basis) : 'Not configured' }}@if($target->incentive_basis==='company')<span class="target-meta">{{ collect($target->incentive_product_ids)->map(fn($id)=>optional($products->firstWhere('id',$id))->product ?? 'Unavailable product')->implode(', ') ?: 'All products' }}</span>@elseif($target->incentive_basis==='team')<span class="target-meta">Manager + assigned team</span>@endif</td>
                <td data-label="Incentive">
                    @if($target->incentive_enabled)
                        <strong class="target-incentive">₹{{ number_format($result['incentive'],2) }}</strong>
                        @if($target->incentive_basis==='team')
                            <span class="target-meta">Own: {{ rtrim(rtrim($target->incentive_multiplier,'0'),'.') }} × ₹{{ number_format($result['own_sales'] ?? 0,2) }}</span>
                            <span class="target-meta">Team: {{ rtrim(rtrim($target->incentive_team_multiplier,'0'),'.') }} × ₹{{ number_format($result['team_sales'] ?? 0,2) }}</span>
                        @else
                            <span class="target-meta">{{ rtrim(rtrim($target->incentive_multiplier,'0'),'.') }} × ₹{{ number_format($result['sales_amount'],2) }}</span>
                        @endif
                    @else
                        <span class="target-tag neutral">Not configured</span>
                    @endif
                </td>
                <td data-label="Action"><div class="target-actions">@if($canConfigure)<button type="button" class="target-edit" data-edit="{{ route('admin.targets.edit',$target->id) }}" title="Edit target" aria-label="Edit target for {{ $target->salesExecutive->name ?? '' }}"><i class="ri-edit-line" aria-hidden="true"></i></button><button type="button" class="target-delete" data-delete="{{ route('admin.targets.destroy',$target->id) }}" title="Delete target" aria-label="Delete target for {{ $target->salesExecutive->name ?? '' }}"><i class="ri-delete-bin-line" aria-hidden="true"></i></button>@endif</div></td>
            </tr>
        @endforeach
        @foreach($unassignedStaff as $staff)<tr data-target-row><td data-label="Person"><div class="target-person"><span class="target-avatar">{{ $initials($staff->name) }}</span><strong>{{ $staff->name }}</strong></div><span class="target-meta">{{ optional($staff->userType)->user_type }}</span></td><td data-label="Target">Not assigned</td><td data-label="Achieved">—</td><td data-label="Progress">—</td><td data-label="Sales basis">Not configured</td><td data-label="Incentive">—</td><td data-label="Action">@if($canAssign)<div class="target-actions"><button class="target-add" type="button" data-assign-person="{{ $staff->id }}" title="Assign target" aria-label="Assign target to {{ $staff->name }}"><i class="ri-add-line" aria-hidden="true"></i></button></div>@endif</td></tr>@endforeach
        </tbody></table><p class="target-empty" id="target-empty" hidden>No matching targets</p><footer class="target-table-footer"><span id="target-record-count"></span><div id="target-pagination"></div></footer>
    </section>
    @if($canAssign)
    <dialog id="target-editor" aria-labelledby="target-editor-title"><header class="target-modal-heading"><h2 id="target-editor-title">Assign Target</h2><button type="button" data-close title="Close" aria-label="Close target modal"><i class="ri-close-line" aria-hidden="true"></i></button></header>
        <form id="target-form" action="{{ route('admin.targets.store') }}" method="POST">@csrf<input type="hidden" name="_method" value="POST"><div class="target-form-error" role="alert" hidden></div>
        <div class="target-form-grid">
            <label class="wide">Assign to<select name="sales_executive_id" required><option value="">Select employee</option>@foreach($assignableStaff as $staff)<option value="{{ $staff->id }}" data-role="{{ optional($staff->userType)->user_type }}" {{ old('sales_executive_id') == $staff->id ? 'selected' : '' }}>{{ $staff->name }} · {{ optional($staff->userType)->user_type }}</option>@endforeach</select></label>
            <label>Year<select name="year">@for($i=2020;$i<=2050;$i++)<option {{ old('year',now()->year)==$i ? 'selected' : '' }}>{{ $i }}</option>@endfor</select></label>
            <label>Month<select name="month">@foreach($months as $key=>$label)<option value="{{ $key }}" {{ old('month',now()->month)==$key ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></label>
            <label>Target amount (₹)<input name="target_amount" type="number" step="0.01" min="0" required value="{{ old('target_amount') }}"></label>
            <label>Status<select name="status"><option value="active" {{ old('status')==='active'?'selected':'' }}>Active</option><option value="inactive" {{ old('status')==='inactive'?'selected':'' }}>Inactive</option></select></label>
            <label class="wide">Description<textarea name="description" maxlength="1000" rows="2">{{ old('description') }}</textarea></label>
        </div>
        @if($canConfigure)<section class="target-incentive-settings"><h3>Incentive</h3><input type="hidden" name="incentive_enabled" value="0"><label class="target-checkbox"><input type="checkbox" name="incentive_enabled" value="1" {{ old('incentive_enabled') ? 'checked' : '' }}>Enable incentive</label><div id="target-incentive-fields">
            <label><span id="target-multiplier-label">Incentive multiplier</span><div class="target-rate"><input type="number" name="incentive_multiplier" value="{{ old('incentive_multiplier','0.002') }}" min="0" max="1" step="0.00000001"><output id="target-rate-percent">0.20%</output></div></label>
            <fieldset class="target-basis"><legend>Sales amount basis</legend>@foreach(['individual'=>'Individual','team'=>'Team','company'=>'Company'] as $value=>$label)<label><input type="radio" name="incentive_basis" value="{{ $value }}" {{ old('incentive_basis','individual')===$value ? 'checked' : '' }}>{{ $label }}</label>@endforeach</fieldset>
            <label id="target-team-rate-field" hidden>Manager incentive multiplier on team sales<div class="target-rate"><input type="number" name="incentive_team_multiplier" value="{{ old('incentive_team_multiplier','0.002') }}" min="0" max="1" step="0.00000001"><output id="target-team-rate-percent">0.20%</output></div></label>
            <div id="target-product-field" hidden><span class="target-field-label">Products</span><button type="button" id="target-product-toggle" class="target-product-toggle" aria-expanded="false" aria-controls="target-product-options"><span>All products</span><i class="ri-arrow-down-s-line" aria-hidden="true"></i></button><div id="target-product-options" hidden><input type="hidden" name="incentive_all_products" value="0"><label class="target-checkbox all-products"><input type="checkbox" name="incentive_all_products" value="1" {{ old('incentive_all_products',1) ? 'checked' : '' }}>All products</label>@foreach($products as $product)<label class="target-checkbox"><input type="checkbox" name="incentive_product_ids[]" value="{{ $product->id }}" {{ old('incentive_all_products',1) || in_array($product->id,old('incentive_product_ids',[])) ? 'checked' : '' }}><span>{{ $product->product }}</span></label>@endforeach</div></div>
        </div></section>@endif
        <footer class="target-modal-footer"><button type="button" class="target-button" data-close>Cancel</button><button class="target-button primary" type="submit" id="target-save">Save Target</button></footer></form>
    </dialog>@endif
    @if($canConfigure)
    <dialog id="target-delete-dialog" aria-labelledby="target-delete-title"><header class="target-modal-heading"><h2 id="target-delete-title">Delete Target</h2><button type="button" data-close aria-label="Close delete modal"><i class="ri-close-line" aria-hidden="true"></i></button></header><form method="POST">@csrf @method('DELETE')<p>Delete this target and its incentive configuration?</p><footer class="target-modal-footer"><button type="button" class="target-button" data-close>Cancel</button><button class="target-button danger" type="submit">Delete Target</button></footer></form></dialog>
    <dialog id="target-export" aria-labelledby="target-export-title"><header class="target-modal-heading"><h2 id="target-export-title">Download Incentive</h2><button type="button" data-close aria-label="Close download modal"><i class="ri-close-line" aria-hidden="true"></i></button></header><form method="GET" action="{{ route('admin.targets.download-incentive') }}"><div class="target-form-grid"><label>Month<select name="month">@foreach($months as $key=>$label)<option value="{{ $key }}" {{ ($month ?: now()->month)==$key?'selected':'' }}>{{ $label }}</option>@endforeach</select></label><label>Year<select name="year">@for($i=2020;$i<=2050;$i++)<option {{ ($year ?: now()->year)==$i?'selected':'' }}>{{ $i }}</option>@endfor</select></label><fieldset class="target-export-types wide"><legend>File type</legend><label><input type="radio" name="file_type" value="excel" checked>Excel (.xlsx)</label><label><input type="radio" name="file_type" value="csv">CSV</label></fieldset></div><footer class="target-modal-footer"><button type="button" class="target-button" data-close>Cancel</button><button type="submit" class="target-button primary"><i class="ri-download-2-line" aria-hidden="true"></i>Download</button></footer></form></dialog>
    @endif
    <nav class="target-mobile-nav" aria-label="Mobile navigation"><a data-target-bottom="dashboard"><i class="ri-home-4-line" aria-hidden="true"></i>Dashboard</a><a href="{{ route('admin.clients.index') }}"><i class="ri-user-3-line" aria-hidden="true"></i>Leads</a><a data-target-bottom="followup"><i class="ri-time-line" aria-hidden="true"></i>Follow-up</a><a href="{{ route('admin.kpi.index') }}"><i class="ri-bar-chart-line" aria-hidden="true"></i>KPI</a><button id="target-more-toggle" type="button" aria-expanded="false" aria-controls="target-mobile-more"><i class="ri-apps-line" aria-hidden="true"></i>More</button></nav>
    <nav id="target-mobile-more" aria-label="More navigation" hidden></nav>
</div>
<script src="{{ asset('assets/admin/js/crm/targets.js') }}?v=1" defer></script>
@endsection
