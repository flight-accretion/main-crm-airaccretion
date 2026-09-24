<div class="box">
    <div class="box-body overflow-x-auto">
        <table class="table display nowrap kpi-outreach-datatable whitespace-nowrap min-w-full" width="100%">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Mobile</th>
                    <th>Last Called</th>
                    <th>Last Product</th>
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
                        <td>
                        <form
                        method="POST"
                        action="{{ route('admin.kpi.outreach.dnp', ['assignment' => $row->id]) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="assignment_id"
                            value="{{ $row->id }}"
                        >

                        <label class="inline-flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="dnp_{{ $row->id }}"
                                name="dnp"
                                value="1"
                                required
                                {{ $locked ? 'disabled' : '' }}
                            >

                            <span>DNP</span>
                        </label>

                        <button
                            type="submit"
                            class="ti-btn ti-btn-warning mt-1"
                            {{ $locked ? 'disabled' : '' }}
                        >
                            Save
                        </button>
                    </form>
                        </td>
                        <td style="min-width:360px;">
                        <form
                    method="POST"
                    action="{{ route('admin.kpi.outreach.remark', ['assignment' => $row->id]) }}"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="assignment_id"
                        value="{{ $row->id }}"
                    >

                    <textarea
                        id="remark_{{ $row->id }}"
                        name="remark"
                        rows="3"
                        class="ti-form-input"
                        placeholder="Enter call outcome / remark"
                        maxlength="1000"
                        {{ $locked ? 'disabled' : '' }}
                    >{{ $row->latest_skyrec_summary ?: '' }}</textarea>

                    <button
                        type="submit"
                        class="ti-btn ti-btn-info mt-1"
                        {{ $locked ? 'disabled' : '' }}
                    >
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
                        <td colspan="7" class="text-center text-muted">No outreach numbers available.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
