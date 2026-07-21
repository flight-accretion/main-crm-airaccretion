@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3
                class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
                Payment Details</h3>
        </div>
        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
                <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate"
                    href="javascript:void(0);">
                    Payments
                    <i
                        class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                </a>
            </li>
            <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 "
                aria-current="page">
                Payment Details
            </li>
        </ol>
    </div>
    <!-- Page Header Close -->

    <div class="grid grid-cols-12 gap-6 text-defaultsize">
        <div class="xl:col-span-12 col-span-12">
            <div class="box">
                <div class="box-header">
                    <div class="box-title">
                        Mode Of Payment
                    </div>
                </div>
                <div class="box-body">

                    <div class="grid grid-cols-12 sm:gap-6">
                        <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="input-label" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Choose Your
                                Payment Method</label>
                            <div class="sm:flex block " role="group" aria-label="Basic radio toggle button group">
                                <input type="radio" class="btn-check " name="btnradio" id="btnradio1">
                                <label
                                    class="w-full ti-btn ti-btn-outline-light !text-defaulttextcolor sm:!border-e-0 dark:!text-defaulttextcolor/70 dark:!border-defaultborder/10 hover:!bg-light !rounded-e-none"
                                    for="btnradio1">C.O.D(Cash on delivery)</label>
                                <input type="radio" class="btn-check" name="btnradio" id="btnradio2">
                                <label
                                    class="w-full ti-btn ti-btn-outline-light !text-defaulttextcolor dark:!text-defaulttextcolor/70 sm:!border-e-0 dark:!border-defaultborder/10 hover:!bg-light sm:mt-0 mt-1 !rounded-none"
                                    for="btnradio2">UPI</label>
                                <input type="radio" class="btn-check" name="btnradio" id="btnradio3" checked>
                                <label
                                    class="w-full ti-btn ti-btn-light !text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:!border-defaultborder/10 hover:!bg-light !rounded-s-none sm:mt-0 mt-1"
                                    for="btnradio3">Credit/Debit Card</label>
                            </div>
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="input-label" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Name On
                                Card</label>
                            <input type="text" class="ti-form-input  rounded-sm form-control-sm" id="input">
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="input-label" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Total
                                Amount</label>
                            <input type="number" class="ti-form-input  rounded-sm form-control-sm" id="input">
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="input-label" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Due
                                Date</label>
                            <div class="form-group">
                                <div class="input-group">
                                    <div class="input-group-text text-[#8c9097] dark:text-white/50"> <i
                                            class="ri-calendar-line"></i> </div>
                                    <input type="text" class="form-control ti-form-input  rounded-sm form-control-sm"
                                        id="datetime" placeholder="Choose date with time">
                                </div>
                            </div>
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="input-label" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Invoice
                                Status</label>
                            <select class="ti-form-select ti-form-input  rounded-sm form-control-sm">
                                <option selected>Pending</option>
                                <option>Complete</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="ti-btn ti-btn-primary-full ti-custom-validate-btn">Submit</button>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6 mt-4">
        <div class="col-span-12">
            <div class="box custom-box">
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table display responsive nowrap table-datatable" id="userRolesTable" width="100%">
                            <thead class="bg-primary text-white">
                                <tr class="border-b border-defaultborder">
                                    
                                    <th>Sr.No</th>
                                    <th>Name On Card</th>
                                    <th>Payment Method</th>
                                    <th>Amount</th>
                                    <th>Due Date</th>
                                    <th>Invoice Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    
                                    <td>1</td>
                                    <td>UPI</td>
                                    <td>Komal Wadkar</td>
                                    <td>10,000</td>
                                    <td>18-06-2025 12:00</td>
                                    <td><span class="badge bg-success/10 text-success">Active</span></td>
                                    <td>
                                        <div class="hstack flex gap-3 text-[.9375rem]">
                                            <a aria-label="anchor" href=""
                                                class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full"><i
                                                    class="ri-eye-line"></i></a>
                                            <a aria-label="anchor" href=""
                                                class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full"><i
                                                    class="ri-edit-line"></i></a>
                                            <a aria-label="anchor" href="javascript:void(0);"
                                                class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full"><i
                                                    class="ri-lock-line"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


@stop
