<dialog id="target-export" aria-labelledby="target-export-title">
    <header class="target-modal-heading"><h2 id="target-export-title">Download Incentive</h2><button type="button" data-close aria-label="Close download modal"><i class="ri-close-line" aria-hidden="true"></i></button></header>
    <form method="GET" action="{{ route($downloadRoute ?? 'admin.targets.download-incentive') }}">
        <div class="target-form-grid">
            <label>Month<select name="month">@foreach($months as $key=>$label)<option value="{{ $key }}" {{ ($month ?: now()->month)==$key?'selected':'' }}>{{ $label }}</option>@endforeach</select></label>
            <label>Year<select name="year">@for($i=2020;$i<=now()->year;$i++)<option {{ ($year ?: now()->year)==$i?'selected':'' }}>{{ $i }}</option>@endfor</select></label>
            <label class="wide">Person<select name="person_id"><option value="">All employees</option>@foreach($assignableStaff as $person)<option value="{{ $person->id }}" {{ $salesExecutiveId === $person->id ? 'selected' : '' }}>{{ $person->name }}</option>@endforeach</select></label>
            <fieldset class="target-export-types wide"><legend>File type</legend><label><input type="radio" name="file_type" value="excel" checked>Excel (.xlsx)</label><label><input type="radio" name="file_type" value="csv">CSV</label></fieldset>
        </div>
        <footer class="target-modal-footer"><button type="button" class="target-button" data-close>Cancel</button><button type="submit" class="target-button primary"><i class="ri-download-2-line" aria-hidden="true"></i>Download</button></footer>
    </form>
</dialog>
