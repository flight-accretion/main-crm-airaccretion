@extends('admin.layouts.header')

@section('content')

<div class="block justify-between page-header md:flex">
    <div>
        <h3 class="text-xl font-semibold">
            Assign More Leads
        </h3>
    </div>
</div>

<div class="box">
    <div class="box-body">

        @if($isSalesRole)

            <div class="text-center py-6">

                <h5 class="text-lg font-semibold mb-2">
                    Do you want to receive more leads?
                </h5>

                <p class="text-gray-500 mb-6">
                    Select Yes if you are available to receive
                    new automatically assigned leads.
                </p>

                <div class="flex justify-center gap-3">

                    <form
                        method="POST"
                        action="{{ route('admin.sales-dashboard.popup.decline') }}"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="ti-btn ti-btn-danger"
                        >
                            No
                        </button>
                    </form>

                    <form
                        method="POST"
                        action="{{ route('admin.sales-dashboard.popup.accept') }}"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="ti-btn ti-btn-primary"
                        >
                            Yes, Assign More Leads
                        </button>
                    </form>

                </div>

            </div>

        @else

            <div class="text-center py-6">

                <h5 class="text-lg font-semibold mb-2">
                    Lead Allocation
                </h5>

                <p class="text-gray-500">
                    Automatic lead allocation is available only
                    for Sales Executives and Sales Managers.
                </p>

            </div>

        @endif

    </div>
</div>

@endsection