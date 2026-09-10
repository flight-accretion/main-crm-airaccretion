<div class="box">
    <div class="box-body overflow-x-auto">
        <table class="table whitespace-nowrap min-w-full">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Mobile</th>
                    <th>Last Called</th>
                    <th>Last Product</th>
                    <th>Skyrec Summary</th>
                    <th>DNP</th>
                    <th>Remark</th>
                    <th>Fresh Lead</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ optional($row->pool)->display_name ?: 'Customer' }}</td>
                        <td>{{ $row->normalized_phone }}</td>
                        <td>
                            {{ $row->last_called_at_display ? \Carbon\Carbon::parse($row->last_called_at_display)->format('d M Y, h:i A') : 'Never' }}
                        </td>
                        <td>{{ $row->last_product_display ?: '-' }}</td>
                        <td style="white-space:normal;min-width:260px;max-width:420px;">
                            {{ $row->latest_skyrec_summary ?: 'No connected call summary yet' }}
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.kpi.outreach.dnp', $row) }}">
                                @csrf
                                <label class="inline-flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        name="dnp"
                                        value="1"
                                        required
                                        {{ $locked ? 'disabled' : '' }}
                                    >
                                    <span>DNP</span>
                                </label>
                                <button class="ti-btn ti-btn-warning mt-1" {{ $locked ? 'disabled' : '' }}>
                                    Save DNP
                                </button>
                            </form>
                        </td>
                        <td style="min-width:280px;">
                            <form method="POST" action="{{ route('admin.kpi.outreach.remark', $row) }}">
                                @csrf
                                <textarea
                                    name="remark"
                                    rows="2"
                                    class="ti-form-input"
                                    placeholder="Outcome / next context"
                                    {{ $locked ? 'disabled' : '' }}
                                ></textarea>
                                <button class="ti-btn ti-btn-info mt-1" {{ $locked ? 'disabled' : '' }}>
                                    Save Remark
                                </button>
                            </form>
                        </td>
                        <td>
                            @if($locked)
                                <button class="ti-btn ti-btn-success" disabled>Create Fresh Lead</button>
                            @else
                                <a
                                    class="ti-btn ti-btn-success"
                                    href="{{ route('admin.kpi.outreach.create-lead', $row) }}"
                                >
                                    Create Fresh Lead
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted">No outreach numbers available.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
