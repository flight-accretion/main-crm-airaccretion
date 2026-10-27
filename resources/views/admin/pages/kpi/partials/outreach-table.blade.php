<section class="do-queue-card">
    <div class="do-table-wrap">
        <table class="do-table table display kpi-outreach-datatable" width="100%">
            <thead>
                <tr>
                    <th>Customer / Mobile</th>
                    <th>Last Called</th>
                    <th>Last Product</th>
                    <th>DNP</th>
                    <th>Remark</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td data-label="Customer / Mobile">
                            <span class="do-customer-name">{{ optional($row->pool)->display_name ?: 'Customer' }}</span>
                            <span class="do-phone">{{ $row->normalized_phone }}</span>
                            <button type="button" class="do-copy" data-copy-phone="{{ $row->normalized_phone }}">Copy</button>
                        </td>
                        <td data-label="Last Called">
                            {{ $row->last_called_at_display ? \Carbon\Carbon::parse($row->last_called_at_display)->format('d M Y, h:i A') : 'Never' }}
                        </td>
                        <td data-label="Last Product">{{ $row->last_product_display ?: '-' }}</td>
                        <td data-label="DNP">
                        <form class="do-dnp-form"
                        method="POST"
                        action="{{ route('admin.kpi.outreach.dnp', ['assignment' => $row->id]) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="assignment_id"
                            value="{{ $row->id }}"
                        >

                        <label class="do-dnp-check">
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
                            class="do-button"
                            {{ $locked ? 'disabled' : '' }}
                        >
                            Save
                        </button>
                    </form>
                        </td>
                        <td data-label="Remark">
                        <form class="do-remark-form"
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
                        placeholder="Enter call outcome / remark"
                        maxlength="1000"
                        {{ $locked ? 'disabled' : '' }}
                    >{{ $row->latest_skyrec_summary ?: '' }}</textarea>

                    <button
                        type="submit"
                        class="do-button do-button--primary"
                        {{ $locked ? 'disabled' : '' }}
                    >
                        Save Remark
                    </button>
                </form>
                        </td>
                        <td data-label="Action">
                            <div class="do-action-box">
                            @if($locked)
                                <button class="do-button do-button--success" disabled>Create Fresh Lead</button>
                            @else
                                <a
                                    class="do-button do-button--success"
                                    href="{{ route('admin.kpi.outreach.create-lead', $row) }}"
                                >
                                    Create Fresh Lead
                                </a>
                            @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">No outreach numbers available.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
