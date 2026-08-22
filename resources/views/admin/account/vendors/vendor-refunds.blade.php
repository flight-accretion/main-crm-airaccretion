@extends('admin.layouts.header')


@section('content')

<div class="box">

    <div
        class="box-header flex items-center justify-between"
    >

        <h5 class="box-title">
            Vendor Refund List
        </h5>


        <form
            method="GET"
            action="{{ route(
                'admin.account.vendor-refunds'
            ) }}"
        >

            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                class="ti-form-input"
                placeholder="Search client or vendor"
            >

        </form>

    </div>


    <div class="box-body">

        <div class="table-responsive">

            <table class="table">

                <thead class="bg-primary text-white">

                    <tr>

                        <th>S.No</th>

                        <th>
                            Client Name
                        </th>

                        <th>
                            Vendor Name
                        </th>

                        <th>
                            Vendor Amount
                        </th>

                        <th>
                            Cancellation Amount
                        </th>

                        <th>
                            Gross Paid
                        </th>

                        <th>
                            Received Amount
                        </th>

                        <th>
                            Net Paid
                        </th>

                        <th>
                            Refund Due
                        </th>

                        <th>
                            Refund Type
                        </th>

                        <th>
                            Refund Date
                        </th>

                        <th>
                            Reason
                        </th>

                        <th>
                            Proof
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse (
                        $vendorRefunds
                        as $index =>
                        $refund
                    )

                        @php

                            $lvp =
                                $refund
                                    ->leadVendorPayment;

                            $paid =
                                $lvp
                                    ? $lvp
                                        ->vendorPayments()
                                        ->sum(
                                            'paid_amount'
                                        )
                                    : 0;

                            $netPaid =
                                $lvp
                                    ? $lvp
                                        ->net_paid_to_vendor
                                    : max(
                                        0,
                                        $paid
                                        -
                                        (
                                            $refund
                                                ->refund_amount
                                            ?? 0
                                        )
                                    );

                            $refundDue =
                                $lvp
                                    ? $lvp
                                        ->vendor_refund_due
                                    : 0;

                        @endphp


                        <tr>

                            <td>

                                {{
                                    $vendorRefunds
                                        ->firstItem()
                                    +
                                    $index
                                }}

                            </td>

                            <td>

                                {{
                                    optional(
                                        optional(
                                            $refund
                                                ->lead
                                        )->client
                                    )->name
                                    ?? 'N/A'
                                }}

                            </td>

                            <td>

                                {{
                                    optional(
                                        $refund
                                            ->vendor
                                    )->name
                                    ?? 'N/A'
                                }}

                            </td>


                            <td>

                                ₹{{
                                    number_format(
                                        $lvp
                                            ->total_vendor_service_amount
                                        ?? 0,
                                        2
                                    )
                                }}

                            </td>


                            <td>

                                ₹{{
                                    number_format(
                                        $refund
                                            ->cancellation_amount
                                        ?? 0,
                                        2
                                    )
                                }}

                            </td>


                            <td>

                                ₹{{
                                    number_format(
                                        $paid,
                                        2
                                    )
                                }}

                            </td>


                            <td class="text-success">

                                ₹{{
                                    number_format(
                                        $refund
                                            ->refund_amount
                                        ?? 0,
                                        2
                                    )
                                }}

                            </td>

                            <td>

                                ₹{{
                                    number_format(
                                        $netPaid,
                                        2
                                    )
                                }}

                            </td>

                            <td class="text-danger">

                                ₹{{
                                    number_format(
                                        $refundDue,
                                        2
                                    )
                                }}

                            </td>


                            <td>

                                {{
                                    $refund
                                        ->refund_type
                                    ?? 'N/A'
                                }}

                            </td>


                            <td>

                                {{
                                    $refund
                                        ->refund_date
                                        ? $refund
                                            ->refund_date
                                            ->format(
                                                'd-m-Y'
                                            )
                                        : 'N/A'
                                }}

                            </td>


                            <td>

                                {{
                                    $refund
                                        ->refund_reason
                                    ?? 'N/A'
                                }}

                            </td>


                            <td>

                                @if (
                                    $refund
                                        ->refund_proof
                                )

                                    <a
                                        href="{{
                                            Storage::url(
                                                $refund
                                                    ->refund_proof
                                            )
                                        }}"
                                        target="_blank"
                                        class="ti-btn ti-btn-primary ti-btn-sm"
                                    >

                                        View

                                    </a>

                                @else

                                    N/A

                                @endif

                            </td>

                        </tr>


                    @empty

                        <tr>

                            <td
                                colspan="13"
                                class="text-center"
                            >

                                No vendor refunds found.

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        <div class="mt-4">

            {{
                $vendorRefunds
                    ->links()
            }}

        </div>

    </div>

</div>

@endsection
