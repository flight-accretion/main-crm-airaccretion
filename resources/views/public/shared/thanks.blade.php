@extends('layouts.app')

@section('content')
    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-2 col-span-12"></div>
        <div class="xl:col-span-8 col-span-12">
            <div class="box">
                <div class="box-body text-center py-16">
                    <div class="mb-6">
                        <span class="avatar avatar-xl p-4 !rounded-full bg-success/10 m-0">
                            <i class="ri-check-line text-5xl text-success"></i>
                        </span>
                    </div>
                    
                    <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-4">
                        Registration Submitted Successfully!
                    </h2>
                    
                    <p class="text-gray-600 dark:text-gray-400 text-lg mb-6">
                        Thank you for submitting your passenger details. We have received your information and will process it shortly.
                    </p>
                    
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-6 mb-6">
                        <h5 class="font-semibold mb-3 text-gray-800 dark:text-white">What happens next?</h5>
                        <ul class="text-left text-gray-600 dark:text-gray-400 space-y-2">
                            <li class="flex items-start">
                                <i class="ri-check-circle-line text-success mt-1 me-2"></i>
                                Your passenger information has been saved
                            </li>
                            <li class="flex items-start">
                                <i class="ri-check-circle-line text-success mt-1 me-2"></i>
                                Our team will review and process your details
                            </li>
                            <li class="flex items-start">
                                <i class="ri-check-circle-line text-success mt-1 me-2"></i>
                                You will be contacted for any additional requirements
                            </li>
                            <li class="flex items-start">
                                <i class="ri-check-circle-line text-success mt-1 me-2"></i>
                                We'll keep you updated on the booking status
                            </li>
                        </ul>
                    </div>
                    
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        <p>If you have any questions, please don't hesitate to contact us.</p>
                        <p class="mt-2">
                            <i class="ri-phone-line me-1"></i> Phone:  +91-9575340786  | 
                            <i class="ri-mail-line me-1"></i> Email: ops@accretionaviation.com 
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="xl:col-span-2 col-span-12"></div>
    </div>
@endsection