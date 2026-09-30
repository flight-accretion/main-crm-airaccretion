@extends('admin.layouts.header')

@section('content')
    <div class="flex items-center justify-between p-5 pb-2">
        <div>
            <h3 class="text-[1.125rem] font-semibold">HR Dashboard</h3>
            <p class="text-sm text-gray-500">Employee, attendance, KPI and sales payment overview.</p>
        </div>

        <form method="GET" action="{{ route('admin.hr.dashboard') }}" class="flex items-end gap-2">
            <div>
                <label for="hr-dashboard-month" class="ti-form-label">Month</label>
                <input
                    id="hr-dashboard-month"
                    type="month"
                    name="month"
                    value="{{ $month }}"
                    class="form-control"
                >
            </div>
            <button type="submit" class="ti-btn ti-btn-primary">Apply</button>
        </form>
    </div>

    <div class="grid grid-cols-12 gap-4 p-5 pt-2">
        <div class="xl:col-span-3 md:col-span-6 col-span-12">
            <div class="box">
                <div class="box-body">
                    <p class="text-sm text-gray-500">Active Employees</p>
                    <h4 class="text-2xl font-semibold mt-1">{{ number_format($activeEmployees) }}</h4>
                </div>
            </div>
        </div>

        <div class="xl:col-span-3 md:col-span-6 col-span-12">
            <div class="box">
                <div class="box-body">
                    <p class="text-sm text-gray-500">Confirmed Attendance Imports</p>
                    <h4 class="text-2xl font-semibold mt-1">{{ number_format($attendanceImports) }}</h4>
                </div>
            </div>
        </div>

        <div class="xl:col-span-3 md:col-span-6 col-span-12">
            <div class="box">
                <div class="box-body">
                    <p class="text-sm text-gray-500">Pending Manual Operations KPI</p>
                    <h4 class="text-2xl font-semibold mt-1">{{ number_format($pendingManualKpis) }}</h4>
                </div>
            </div>
        </div>

        <div class="xl:col-span-3 md:col-span-6 col-span-12">
            <div class="box">
                <div class="box-body">
                    <p class="text-sm text-gray-500">Approved Payments</p>
                    <h4 class="text-2xl font-semibold mt-1">Rs. {{ number_format($paymentsApproved, 2) }}</h4>
                </div>
            </div>
        </div>

        <div class="xl:col-span-6 col-span-12">
            <div class="box">
                <div class="box-header">
                    <h5 class="box-title">Sales Target Summary</h5>
                </div>
                <div class="box-body">
                    <div class="grid grid-cols-12 gap-4">
                        <div class="md:col-span-6 col-span-12">
                            <p class="text-sm text-gray-500">Target</p>
                            <h4 class="text-xl font-semibold mt-1">Rs. {{ number_format($salesTarget, 2) }}</h4>
                        </div>
                        <div class="md:col-span-6 col-span-12">
                            <p class="text-sm text-gray-500">Achieved</p>
                            <h4 class="text-xl font-semibold mt-1">Rs. {{ number_format($salesAchieved, 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="xl:col-span-6 col-span-12">
            <div class="box">
                <div class="box-header">
                    <h5 class="box-title">HR Access</h5>
                </div>
                <div class="box-body flex flex-wrap gap-2">
                    <a href="{{ route('admin.attendance.import.index') }}" class="ti-btn ti-btn-light">
                        Attendance Import
                    </a>
                    <a href="{{ route('admin.attendance.settings.index') }}" class="ti-btn ti-btn-light">
                        Attendance Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
