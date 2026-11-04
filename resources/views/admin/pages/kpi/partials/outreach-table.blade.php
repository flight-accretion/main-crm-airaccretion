
<section class="do-queue-card">
    <div class="do-table-wrap">

        <table
            class="do-table table display kpi-outreach-datatable"
            width="100%"
        >
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
                @foreach($rows as $row)
                    @php
                        $outreachFormId = 'outreach-save-' . $row->id;
                    @endphp

                    <tr>
                        
                <td data-label="Customer / Mobile">
                    <span class="do-customer-name">
                        {{ optional($row->pool)->display_name ?: 'Customer' }}
                    </span>

                    @php
                        $displayPhone = trim((string) $row->normalized_phone);
                        $dialPhone = preg_replace('/[^0-9+]/', '', $displayPhone);
                    @endphp

                    @if($dialPhone !== '')
                        <a
                            href="tel:{{ $dialPhone }}"
                            class="do-phone do-phone-action"
                            data-outreach-phone="{{ $displayPhone }}"
                            title="Click to copy number"
                            aria-label="Mobile number {{ $displayPhone }}. Click to copy on desktop or call on mobile."
                        >
                            {{ $displayPhone }}
                        </a>
                    @else
                        <span class="do-phone">-</span>
                    @endif
                </td>


                        <td data-label="Last Called">
                            {{ $row->last_called_at_display
                                ? \Carbon\Carbon::parse($row->last_called_at_display)->format('d M Y, h:i A')
                                : 'Never' }}
                        </td>

                        <td data-label="Last Product">
                            {{ $row->last_product_display ?: '-' }}
                        </td>

                        <td data-label="DNP">
                            <form
                                id="{{ $outreachFormId }}"
                                class="do-outreach-save-form"
                                method="POST"
                                action="{{ route('admin.kpi.outreach.save', ['assignment' => $row->id]) }}"
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
                                        name="dnp"
                                        value="1"
                                        {{ $locked ? 'disabled' : '' }}
                                    >
                                    <span>DNP</span>
                                </label>
                            </form>
                        </td>

                        <td data-label="Remark">
                            <textarea
                                form="{{ $outreachFormId }}"
                                name="remark"
                                class="do-remark-input"
                                rows="3"
                                placeholder="Enter call outcome / remark"
                                maxlength="1000"
                                {{ $locked ? 'disabled' : '' }}
                            >{{ $row->latest_skyrec_summary ?: '' }}</textarea>

                            
                        </td>

                        <td data-label="Action">
                            <div class="do-action-box do-action-buttons">

                                <button
                                    type="submit"
                                    form="{{ $outreachFormId }}"
                                    class="do-button do-button--primary do-unified-save"
                                    {{ $locked ? 'disabled' : '' }}
                                >
                                    <i class="ri-save-line" aria-hidden="true"></i>
                                    Save
                                </button>

                                @if($locked)
                                    <button
                                        type="button"
                                        class="do-button do-button--success"
                                        disabled
                                    >
                                        Create Fresh Lead
                                    </button>
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
                @endforeach
            </tbody>
        </table>

        @if($rows->isEmpty())
            <p class="text-center text-muted py-4" role="status">
                No outreach numbers available.
            </p>
        @endif

    </div>
</section>
