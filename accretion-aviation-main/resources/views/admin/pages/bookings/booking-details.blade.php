@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
                <div class="block justify-between page-header md:flex">
                    <div>
                        <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">Booking Details</h3>
                    </div>
                    <ol class="flex items-center whitespace-nowrap min-w-0">
                        <li class="text-[0.813rem] ps-[0.5rem]">
                          <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate" href="javascript:void(0);">
                            Booking
                            <i class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                          </a>
                        </li>
                        <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 " aria-current="page">
                            Booking Details
                        </li>
                    </ol>
                </div>
                <!-- Page Header Close -->

                <!-- Start::row-1 -->
                <div class="grid grid-cols-12 gap-6">
                    <div class="xl:col-span-12 col-span-12">
                        <div class="box">
                            <div class="box-header md:flex block">
                                <div class="h5 mb-0 sm:flex bllock items-center">
                                    <div class="sm:ms-2 ms-0 sm:mt-0 mt-2">
                                        <div class="h6 font-semibold mb-0 ">Travel Receipt</div>
                                    </div>
                                </div>
                                <div class="ms-auto md:mt-0 mt-2">
                                    {{-- <button type="button" class="ti-btn !py-1 !px-2  text-white !text-[0.75rem] bg-secondary me-1" onclick="javascript:window.print();">Print<i class="ri-printer-line ms-1 align-middle inline-block"></i></button> --}}       {{--this one is by komal, this button is placed in booking-invoice-pdf.blade.php file --}}

                                    <a href="/generate-invoice" class="ti-btn !py-1 !px-2 text-white !text-[0.75rem] bg-primary" target="_blank"> Generate and View Invoice <i class="ri-file-pdf-line ms-1 align-middle inline-block"></i></a>

                                    {{-- <button type="button" class="ti-btn !py-1 !px-2 text-white !text-[0.75rem] bg-primary">Save As PDF<i class="ri-file-pdf-line ms-1 align-middle inline-block"></i></button>  --}}  {{--this one is by komal--}}
                                </div>
                            </div>
                            <div class="box-body">
                                <div class="grid grid-cols-12 gap-4">
                                    <div class="xl:col-span-12 col-span-12 mb-5">
                                        <div class="grid grid-cols-12 sm:gap-x-6 gap-y-6">
                                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-6 col-span-12">
                                                <h6 class="font-bold mb-1">
                                                    Komal wadkar
                                                </h6>
                                                <p class="mb-1 text-[#8c9097] dark:text-white/50">
                                                    Mig-1-11,Manroe street
                                                    Georgetown,Washington D.C,USA,200071
                                                </p>
                                                <p class="mb-1 text-[#8c9097] dark:text-white/50">
                                                    sprukotrust.ynex@gmail.com
                                                </p>
                                                <p class="mb-1 text-[#8c9097] dark:text-white/50">
                                                    (555) 555-1234
                                                </p>
                                            </div>
                                            
                                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-6 col-span-12 sm:ms-auto sm:mt-0 mt-4">
                                                <h6 class="font-bold mb-1 text-sm">
                                                    Travel From : <span class="font-medium">Delhi</span>
                                                </h6> 
                                                <h6 class="font-bold mb-1 text-sm">
                                                    Travel To : <span class="font-medium">Kerla</span>
                                                </h6> 
                                                <h6 class="font-bold mb-1 text-sm">
                                                    Travel Date From : <span class="font-medium">26/06/2025</span>
                                                </h6>  
                                                <h6 class="font-bold mb-1 text-sm">
                                                    Travel Date To : <span class="font-medium">26/06/2025</span>
                                                </h6>                                              
                                            </div>
                                        </div>
                                    </div>
                                    <div class="xl:col-span-12 col-span-12 mb-5">
                                        <div class="grid grid-cols-12 sm:gap-x-6 gap-y-6">
                                            <div class="xl:col-span-3 col-span-12">
                                                <h6 class="font-semibold mb-1">Invoice ID :</h6>
                                                <p class="text-[.9375rem] mb-1">#SPK120219890</p>
                                            </div>
                                            <div class="xl:col-span-3 col-span-12">
                                                <h6 class="font-semibold mb-1">Invoice Date :</h6>
                                                <p class="text-[.9375rem] mb-1">29,Nov 2022 - <span class="text-[#8c9097] dark:text-white/50 text-xs">12:42PM</span></p>
                                            </div>
                                            <div class="xl:col-span-3 col-span-12">
                                                <h6 class="font-semibold mb-1">Booking ID :</h6>
                                                <p class="text-[.9375rem] mb-1">MP65327</p>
                                            </div>
                                            <div class="xl:col-span-3 col-span-12">
                                                <h6 class="font-semibold mb-1">Booking Date :</h6>
                                                <p class="text-[.9375rem] mb-1">29,Dec 2022</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="xl:col-span-12 col-span-12">
                                        <h6 class="font-bold mb-1">Product Information</h6>
                                        <div class="table-responsive">
                                                <table class="table nowrap whitespace-nowrap border dark:border-defaultborder/10 mt-4 min-w-full">
                                                    <thead>
                                                        <tr>
                                                            <th scope="row" class="text-start">PRODUCT NAME</th>
                                                            <th scope="row" class="text-start">DESCRIPTION</th>
                                                            <th scope="row" class="text-start">SERVICE</th>
                                                            <th scope="row" class="text-start">PAYMENT MODE</th>
                                                            <th scope="row" class="text-start">AMOUNT</th>
                                                            <th scope="row" class="text-start">TOTAL</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr class="border border-defaultborder dark:border-defaultborder/10">
                                                            <td>
                                                                <div class="font-semibold">
                                                                    Dapzem &amp; Co (Sweatshirt)
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="text-[#8c9097] dark:text-white/50">
                                                                    Branded hoodie ethnic style
                                                                </div>
                                                            </td>
                                                            <td>Helicopter</td>
                                                            <td class="product-payment-mode">
                                                                UPI
                                                            </td>
                                                            <td>
                                                                $60
                                                            </td>
                                                            <td>
                                                                $120
                                                            </td>
                                                        </tr>
                                                        <tr class="border border-defaultborder dark:border-defaultborder/10">
                                                            <td>
                                                                <div class="font-semibold">
                                                                    Denim Winjo (Jacket)
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="text-[#8c9097] dark:text-white/50">
                                                                    Vintage pure leather Jacket
                                                                </div>
                                                            </td>
                                                            <td>Private Jet</td>
                                                            <td class="product-payment-mode">
                                                                CASH ON DELIVERY
                                                            </td>
                                                            <td>
                                                                $249
                                                            </td>
                                                            <td>
                                                                $249
                                                            </td>
                                                        </tr>
                                                        <tr class="border border-defaultborder dark:border-defaultborder/10">
                                                            <td colspan="4"></td>
                                                            <td colspan="2">
                                                                <table class="table table-sm whitespace-nowrap mb-0 table-borderless w-full">
                                                                    <tbody>
                                                                        <tr>
                                                                            <th scope="row">
                                                                                <p class="mb-0">Sub Total :</p>
                                                                            </th>
                                                                            <td>
                                                                                <p class="mb-0 font-semibold text-[.9375rem]">$2,364</p>
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th scope="row">
                                                                                <p class="mb-0">Avail Discount :</p>
                                                                            </th>
                                                                            <td>
                                                                                <p class="mb-0 font-semibold text-[.9375rem]">$29.98</p>
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th scope="row">
                                                                                <p class="mb-0">Coupon Discount <span class="text-success">(10%)</span> :</p>
                                                                            </th>
                                                                            <td>
                                                                                <p class="mb-0 font-semibold text-[.9375rem]">$236.40</p>
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th scope="row">
                                                                                <p class="mb-0">Vat <span class="text-danger">(20%)</span> :</p>
                                                                            </th>
                                                                            <td>
                                                                                <p class="mb-0 font-semibold text-[.9375rem]">$472.80</p>
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th scope="row">
                                                                                <p class="mb-0">Due Till Date :</p>
                                                                            </th>
                                                                            <td>
                                                                                <p class="mb-0 font-semibold text-[.9375rem]">$0</p>
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th scope="row">
                                                                                <p class="mb-0 text-[.875rem]">Total :</p>
                                                                            </th>
                                                                            <td>
                                                                                <p class="mb-0 font-semibold text-[1rem] text-success">$2,570.42</p>
                                                                            </td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                        </div>
                                    </div>
                                    <div class="xl:col-span-12 col-span-12">
                                        <div>
                                            <label for="invoice-note" class="form-label">Note:</label>
                                            <textarea class="form-control w-full !rounded-md !bg-light" id="invoice-note" rows="3">Once the invoice has been verified by the accounts payable team and recorded, the only task left is to send it for approval before releasing the payment</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {{-- <div class="box-footer text-end">
                                 <button type="button" class="ti-btn bg-success text-white">Download <i class="ri-download-2-line ms-1 align-middle"></i></button>
                                 <a href="/generate-invoice-pdf" class="ti-btn bg-success text-white">    Download PDF <i class="ri-download-2-line ms-1 align-middle"></i></a>
                            </div> --}}
                        </div>
                    </div>
                </div>
                <!--End::row-1 -->

@stop
