@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3
                class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
                Leads Payment Details</h3>
        </div>
        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
                <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate"
                    href="javascript:void(0);">
                    Accounts
                    <i
                        class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                </a>
            </li>
            <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 "
                aria-current="page">
                Leads Payment Details
            </li>
        </ol>
    </div>
    <!-- Page Header Close -->

    <div class="grid grid-cols-12 gap-6 text-defaultsize">
        <div class="xl:col-span-12 col-span-12">
            <div class="box">
                {{--  <div class="box-header">
                                <div class="box-title">
                                    Mode Of Payment
                                </div>
                            </div>  --}}
                <div class="box-body">
                    <div class="grid grid-cols-12 sm:gap-6">
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="input-label"
                                class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Clients</label>
                            <select class="ti-form-select rounded-sm form-control-sm">
                                <option selected>Anish</option>
                                <option>Komal</option>
                                <option>Jafar</option>
                            </select>
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="input-label" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Leads</label>
                            <select class="ti-form-select rounded-sm form-control-sm">
                                <option selected>Pune</option>
                                <option>Mumbai</option>
                                <option>Goa</option>
                            </select>
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="input-label" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Date</label>
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
                            <label for="input-label" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Payment
                                Type</label>
                            <select class="ti-form-select rounded-sm form-control-sm">
                                <option selected>Payment not done</option>
                                <option>Completed</option>
                                <option>Pending</option>
                            </select>
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="input-label"
                                class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Description</label>
                            <textarea class="ti-form-input form-control-sm rounded-sm" id="text-area" rows="1"></textarea>
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="input-label" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Amount</label>
                            <input type="number" class="ti-form-input  rounded-sm form-control-sm" id="input">
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="input-label" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Note</label>
                            <textarea class="ti-form-input form-control-sm rounded-sm" id="text-area" rows="1"></textarea>
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
                                    <th>Date</th>
                                    <th>Booking Confirm</th>
                                    <th>Amount</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    
                                    <td>1</td>
                                    <td>18-06-2025</td>
                                    <td>20-06-2025</td>
                                    <td>10,000</td>
                                    <td>5000</td>
                                    <td>38,000</td>
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
