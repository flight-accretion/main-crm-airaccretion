<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeadRide;
use App\Models\Lead;
use App\Models\Client;
use App\Models\User;
use App\Models\Product;
use App\Models\Service;
use App\Models\ExtraService;
use App\Models\LeadFollowup;
use App\Models\LeadRefund;
use App\Models\PaymentAuditTrail;
use App\Models\UserType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\AirpointsIntegrationService;
use App\Mail\RefundMail;
use Illuminate\Support\Facades\Mail;
use App\Models\NotificationMaster;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RideStatusExport;

class RideController extends Controller
{

    private function formatRideTime($from, $to)
    {
        if (!$from || !$to) {
            return 'TBA';
        }

        try {
            $fromStr = $from->format('H:i');
            $toStr = $to->format('H:i');
        } catch (\Exception $e) {
            return 'TBA';
        }

        // Treat both 00:00 as TBA
        if ($fromStr === '00:00' && $toStr === '00:00') {
            return 'TBA';
        }

        if ($fromStr === $toStr) {
            return $fromStr;
        }

        return $fromStr . ' - ' . $toStr;
    }
    private function isTba($from, $to)
    {
        if (!$from || !$to) {
            return true;
        }
        try {
            return ($from->format('H:i') === '00:00' && $to->format('H:i') === '00:00');
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Display upcoming rides that have vouchers generated
     * Now includes pending amount calculation
     */
    public function upcomingRides(Request $request)
    {
        // Get date filters
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date')) : Carbon::today();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date')) : Carbon::today()->addDays(7);

        // If no to_date provided, default to 7 days from from_date for better UX
        if (!$toDate) {
            $toDate = $fromDate->copy()->addDays(7);
        }

        // Determine statuses filter (dynamic). If request provides 'status' use it, otherwise use defaults
        $requestedStatuses = $request->input('status');
        if ($requestedStatuses) {
            $statuses = is_array($requestedStatuses) ? array_map('intval', $requestedStatuses) : [intval($requestedStatuses)];
        } else {
            // Default to showing most common active statuses
            $statuses = [1, 3, 4, 5, 7]; // Active, Full Payment, Partial Payment, Complete, Reschedule
        }

        $productId = $request->input('product_id');
        $tbaFilter = $request->input('is_tba');

        $currentFilters = [
            'status'                 => $requestedStatuses,
            'from_date'              => $request->input('from_date'),
            'to_date'                => $request->input('to_date'),
            // 'representative_user_id' => $request->input('representative_user_id'), ,
            'representative_user_id' => $request->input('representative_user_id'),
            'product_id'             => $productId,
            'is_tba'                 => $tbaFilter,
        ];

        // Eager load vouchers, payments and paymentDetails and service/extra service relations
        $upcomingRidesQuery = LeadRide::with([
            'enquiry.client',
            'enquiry.representative',
            'enquiry.vouchers.payment.paymentDetails',
            'enquiry.vouchers.servicePaymentDetails.service',
            'enquiry.vouchers.extraServicePaymentDetails.extraService',
            'enquiry.vouchers.vendorPayments.paymentDetails.service',
            'enquiry.vouchers.vendorPayments.paymentDetails.extraService',
            'enquiry.leadFollowups' => function ($query) {
                $query->orderByDesc('created_at');
            },
            'enquiry.latestFollowup'
        ])
            ->whereBetween(DB::raw('DATE(from_date)'), [$fromDate->toDateString(), $toDate->toDateString()])
            ->whereHas('enquiry.vouchers');

        // Apply status filter
        if (!empty($statuses)) {
            $upcomingRidesQuery->whereHas('enquiry.latestFollowup', function ($q) use ($statuses) {
                $q->whereIn('status', $statuses);
            });
        }

        // Apply sales representative filter
        // $representativeId = $request->input('representative_user_id');
        // if ($representativeId) {
        //     $upcomingRidesQuery->whereHas('enquiry', function ($q) use ($representativeId) {
        //         $q->where('representative_user_id', $representativeId);
        //     });
        // }

        // $representativeId = $request->input('representative_user_id');
        // if ($representativeId === 'unassigned') {
        //     $upcomingRidesQuery->whereHas('enquiry', function ($q) {
        //         $q->whereNull('representative_user_id');
        //     });
        // } elseif ($representativeId) {
        //     $upcomingRidesQuery->whereHas('enquiry', function ($q) use ($representativeId) {
        //         $q->where('representative_user_id', $representativeId);
        //     });
        // }

        // Apply sales representative filter
        $representativeId = $request->input('representative_user_id');
        if ($representativeId) {
            $upcomingRidesQuery->whereHas('enquiry', function ($q) use ($representativeId) {
                $q->where('representative_user_id', $representativeId);
            });
        }

        // sales representative filter removed from UI

        //$upcomingRides = $upcomingRidesQuery->orderBy('from_date', 'asc')->get();
        // sales representative filter removed from UI

        // Product filter
        // Product filter — safe for text/json column
        if ($productId) {
            $upcomingRidesQuery->whereHas('enquiry', function ($q) use ($productId) {
                $q->where('product_ids', 'like', '%' . $productId . '%');
            });
        }

        // TBA filter (PostgreSQL compatible)
        if ($tbaFilter !== null && $tbaFilter !== '') {
            if ($tbaFilter == '1') {
                $upcomingRidesQuery->where(function ($q) {
                    $q->whereRaw("EXTRACT(HOUR FROM from_date) = 0")
                        ->whereRaw("EXTRACT(MINUTE FROM from_date) = 0")
                        ->whereRaw("EXTRACT(HOUR FROM to_date) = 0")
                        ->whereRaw("EXTRACT(MINUTE FROM to_date) = 0");
                });
            } elseif ($tbaFilter == '0') {
                $upcomingRidesQuery->where(function ($q) {
                    $q->where(function ($inner) {
                        $inner->whereRaw("EXTRACT(HOUR FROM from_date) != 0")
                            ->orWhereRaw("EXTRACT(MINUTE FROM from_date) != 0")
                            ->orWhereRaw("EXTRACT(HOUR FROM to_date) != 0")
                            ->orWhereRaw("EXTRACT(MINUTE FROM to_date) != 0");
                    });
                });
            }
        }

        $upcomingRides = $upcomingRidesQuery->orderBy('from_date', 'asc')->get();

        // Transform the data to include required fields
        $ridesData = $upcomingRides->map(function ($ride) {
            $lead = $ride->enquiry;
            $client = $lead->client ?? null;
            $representative = $lead->representative ?? null;
            $latestFollowup = $lead->leadFollowups->first();

            // Get product names (use Lead accessor which handles formats)
            $productNames = [];
            try {
                $pn = $lead->product_names ?? [];
                if (is_string($pn)) {
                    $pn = json_decode($pn, true) ?: [];
                }
                $productNames = is_array($pn) ? $pn : [];
            } catch (\Exception $e) {
                Log::warning('Error fetching product names for lead ' . ($lead->id ?? 'unknown') . ': ' . $e->getMessage());
                $productNames = [];
            }

            // Get service / extra-service names from vouchers (preferred over followups)
            // Use finalized vendor payments like in PaymentReviewController
            $serviceNames = [];
            $extraServiceNames = [];

            $vouchers = $lead->vouchers ?? collect();
            foreach ($vouchers as $voucher) {
                // First try vendor payments (finalized services)
                if (!empty($voucher->vendorPayments) && $voucher->vendorPayments->isNotEmpty()) {
                    foreach ($voucher->vendorPayments as $vp) {
                        if (!empty($vp->paymentDetails) && $vp->paymentDetails->isNotEmpty()) {
                            foreach ($vp->paymentDetails as $pd) {
                                if (!empty($pd->service_id) && !$pd->is_extra_service) {
                                    if (isset($pd->service) && $pd->service) {
                                        $serviceNames[] = $pd->service->service;
                                    }
                                }

                                if (!empty($pd->service_id) && $pd->is_extra_service) {
                                    if (isset($pd->extraService) && $pd->extraService) {
                                        $extraServiceNames[] = $pd->extraService->extra_service;
                                    }
                                }
                            }
                        }
                    }
                }

                // Fallback to servicePaymentDetails if vendorPayments not available
                if (empty($serviceNames) && $voucher->servicePaymentDetails && $voucher->servicePaymentDetails->isNotEmpty()) {
                    foreach ($voucher->servicePaymentDetails as $pd) {
                        if (isset($pd->service) && $pd->service) {
                            $serviceNames[] = $pd->service->service;
                        }
                    }
                }

                if (empty($extraServiceNames) && $voucher->extraServicePaymentDetails && $voucher->extraServicePaymentDetails->isNotEmpty()) {
                    foreach ($voucher->extraServicePaymentDetails as $pd) {
                        if (isset($pd->extraService) && $pd->extraService) {
                            $extraServiceNames[] = $pd->extraService->extra_service;
                        }
                    }
                }
            }

            // Final fallback to latest followup if voucher does not provide services
            if (empty($serviceNames) && $latestFollowup && $latestFollowup->service_ids) {
                $serviceIds = is_array($latestFollowup->service_ids) ? $latestFollowup->service_ids : json_decode($latestFollowup->service_ids, true);
                if ($serviceIds) {
                    $serviceNames = Service::whereIn('id', $serviceIds)->pluck('service')->toArray();
                }
            }

            if (empty($extraServiceNames) && $latestFollowup && $latestFollowup->extra_service_ids) {
                $extraServiceIds = is_array($latestFollowup->extra_service_ids) ? $latestFollowup->extra_service_ids : json_decode($latestFollowup->extra_service_ids, true);
                if ($extraServiceIds) {
                    $extraServiceNames = ExtraService::whereIn('id', $extraServiceIds)->pluck('extra_service')->toArray();
                }
            }

            // Calculate pending amount using a more efficient method
            $pendingAmount = $this->calculatePendingAmount($lead);

            // Get status information
            $statusId = $latestFollowup ? $latestFollowup->status : 0;

            return [
                'id' => $ride->id,
                'client_name' => $client ? $client->name : 'N/A',
                'contact_number' => $client ? $client->contact_number : 'N/A',
                'ride_date' => $ride->from_date ? $ride->from_date->format('d-m-Y') : 'N/A',
                'ride_time' => $this->formatRideTime($ride->from_date, $ride->to_date),
                'is_tba' => $this->isTba($ride->from_date, $ride->to_date),
                'from_place' => $ride->from_place ?? 'N/A',
                'to_place' => $ride->to_place ?? 'N/A',
                'service_names' => implode(', ', $serviceNames),
                'extra_service_names' => implode(', ', $extraServiceNames),
                'product_names' => implode(', ', $productNames),
                'sales_person_name' => $representative ? $representative->name : 'N/A',
                'pending_amount' => number_format($pendingAmount, 2),
                'description' => $lead ? $lead->description : '',
                'number_of_passengers' => $lead ? $lead->number_of_passengers : 0,
                'occasion' => $lead ? $lead->occasion : '',
                'status_id' => $statusId,
                'status_name' => '', // Will be filled by view using availableStatuses
            ];
        });

        // Get sales representatives (users with sales roles from UserType)
        $salesReps = User::with('userType')
            ->whereHas('userType', function ($query) {
                $query->whereIn('user_type', [
                    UserType::SENIOR_SALES_MANAGER,
                    UserType::SALES_MANAGER,
                    UserType::SALES_EXECUTIVE
                ]);
            })
            ->where('status', 1)
            ->select('id', 'name', 'user_type_id')
            ->orderBy('name')
            ->get();

        // Get available statuses based on LeadFollowup status values
        $availableStatuses = [
            // 0 => 'New Lead',
            1 => 'Active',
            // 2 => 'Cancelled',
            // 3 => 'Full Payment',
            // 4 => 'Partial Payment',
            5 => 'Completed',
            // 6 => 'Pending',
            7 => 'Reschedule',
            // 8 => 'Payment Approved',
            // 9 => 'Payment Rejected'
        ];
        $products = Product::where('status', 1)->orderBy('product')->get();
        return view('admin.pages.rides.upcoming-rides', compact('ridesData', 'salesReps', 'availableStatuses', 'currentFilters', 'products'));
    }

    /**
     * Get calendar events for all rides (past and future)
     */
    public function getCalendarEvents(Request $request)
    {
        // Get filter parameters
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $salesRepFilter = $request->input('representative_user_id');
        $statusFilter = $request->input('status');
        $productId = $request->input('product_id');
        $tbaFilter = $request->input('is_tba');

        // Only include rides whose lead has at least one voucher generated
        $ridesQuery = LeadRide::with([
            'enquiry.client',
            'enquiry.representative',
            // load lead's vouchers and their vendor payments + payment details which finalise services
            'enquiry.vouchers.vendorPayments.paymentDetails.service',
            'enquiry.vouchers.vendorPayments.paymentDetails.extraService',
            'enquiry.leadFollowups' => function ($query) {
                $query->orderByDesc('created_at');
            }
        ])
            ->whereNotNull('from_date')
            ->whereHas('enquiry.vouchers')
            ->orderBy('from_date', 'asc');

        // Apply date filters if provided
        if ($fromDate && $toDate) {
            $ridesQuery->whereBetween(DB::raw('DATE(from_date)'), [
                Carbon::parse($fromDate)->toDateString(),
                Carbon::parse($toDate)->toDateString()
            ]);
        }

        // Apply sales representative filter
        // if ($salesRepFilter) {
        //     $ridesQuery->whereHas('enquiry', function ($q) use ($salesRepFilter) {
        //         $q->where('representative_user_id', $salesRepFilter);
        //     });
        // }

        if ($salesRepFilter === 'unassigned') {
            $ridesQuery->whereHas('enquiry', function ($q) {
                $q->whereNull('representative_user_id');
            });
        } elseif ($salesRepFilter) {
            $ridesQuery->whereHas('enquiry', function ($q) use ($salesRepFilter) {
                $q->where('representative_user_id', $salesRepFilter);
            });
        }

        // Apply status filter
        if ($statusFilter) {
            $statusFilter = is_array($statusFilter) ? $statusFilter : [$statusFilter];
            $ridesQuery->whereHas('enquiry.latestFollowup', function ($q) use ($statusFilter) {
                $q->whereIn('status', $statusFilter);
            });
        }
        // Product filter — safe for text/json column
        if ($productId) {
            $ridesQuery->whereHas('enquiry', function ($q) use ($productId) {
                $q->where('product_ids', 'like', '%' . $productId . '%');
            });
        }

        // TBA filter (PostgreSQL compatible)
        if ($tbaFilter !== null && $tbaFilter !== '') {
            if ($tbaFilter == '1') {
                $ridesQuery->where(function ($q) {
                    $q->whereRaw("EXTRACT(HOUR FROM from_date) = 0")
                        ->whereRaw("EXTRACT(MINUTE FROM from_date) = 0")
                        ->whereRaw("EXTRACT(HOUR FROM to_date) = 0")
                        ->whereRaw("EXTRACT(MINUTE FROM to_date) = 0");
                });
            } elseif ($tbaFilter == '0') {
                $ridesQuery->where(function ($q) {
                    $q->where(function ($inner) {
                        $inner->whereRaw("EXTRACT(HOUR FROM from_date) != 0")
                            ->orWhereRaw("EXTRACT(MINUTE FROM from_date) != 0")
                            ->orWhereRaw("EXTRACT(HOUR FROM to_date) != 0")
                            ->orWhereRaw("EXTRACT(MINUTE FROM to_date) != 0");
                    });
                });
            }
        }

        $rides = $ridesQuery->get();

        //$rides = $ridesQuery->get();

        $events = $rides->map(function ($ride) {
            $lead = $ride->enquiry;
            $client = $lead->client ?? null;
            $representative = $lead->representative ?? null;
            $latestFollowup = $lead->leadFollowups->first();

            // Prefer finalized services from LeadVendorPayment -> paymentDetails
            $serviceNames = [];
            $extraServiceNames = [];

            $vouchers = $lead->vouchers ?? collect();
            foreach ($vouchers as $voucher) {
                if (!empty($voucher->vendorPayments) && $voucher->vendorPayments->isNotEmpty()) {
                    foreach ($voucher->vendorPayments as $vp) {
                        if (!empty($vp->paymentDetails) && $vp->paymentDetails->isNotEmpty()) {
                            foreach ($vp->paymentDetails as $pd) {
                                if (!empty($pd->service_id) && !$pd->is_extra_service) {
                                    // try to read related Service model if loaded
                                    if (isset($pd->service) && $pd->service) {
                                        $serviceNames[] = $pd->service->service;
                                    } else {
                                        $serviceNames[] = $pd->service_id;
                                    }
                                }

                                if (!empty($pd->service_id) && $pd->is_extra_service) {
                                    if (isset($pd->extraService) && $pd->extraService) {
                                        $extraServiceNames[] = $pd->extraService->extra_service;
                                    } else {
                                        $extraServiceNames[] = $pd->service_id;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Fallback to followup services if vendorPayments not present
            if (empty($serviceNames) && $latestFollowup && $latestFollowup->service_ids) {
                $serviceIds = is_array($latestFollowup->service_ids) ? $latestFollowup->service_ids : json_decode($latestFollowup->service_ids, true);
                if ($serviceIds) {
                    $serviceNames = Service::whereIn('id', $serviceIds)->pluck('service')->toArray();
                }
            }

            // Fallback to followup extra services if vendorPayments not present
            if (empty($extraServiceNames) && $latestFollowup && $latestFollowup->extra_service_ids) {
                $extraServiceIds = is_array($latestFollowup->extra_service_ids) ? $latestFollowup->extra_service_ids : json_decode($latestFollowup->extra_service_ids, true);
                if ($extraServiceIds) {
                    $extraServiceNames = ExtraService::whereIn('id', $extraServiceIds)->pluck('extra_service')->toArray();
                }
            }

            // Determine event color based on date
            $currentDate = Carbon::now();
            $rideDate = $ride->from_date;
            $backgroundColor = '#3788d8'; // Default blue

            // If this ride is TBA (time not announced) prefer a distinct color
            $isTba = $this->isTba($ride->from_date, $ride->to_date);
            if ($isTba) {
                $backgroundColor = '#8e44ad'; // Purple for TBA rides
            } else {
                if ($rideDate->lt($currentDate)) {
                    $backgroundColor = '#6c757d'; // Gray for past rides
                } elseif ($rideDate->isToday()) {
                    $backgroundColor = '#3ce7e7ff'; // Red for today's rides
                } elseif ($rideDate->diffInDays($currentDate) <= 7) {
                    $backgroundColor = '#f39c12'; // Orange for rides within a week
                }
            }

            // Calculate pending amount for this ride's lead
            $pendingAmount = $this->calculatePendingAmount($lead);

            // Get status information using the same mapping as the main method
            $availableStatuses = [
                // 0 => 'New Lead',
                1 => 'Active',
                //2 => 'Cancelled',
                // 3 => 'Full Payment',
                // 4 => 'Partial Payment',
                5 => 'Completed',
                //6 => 'Pending',
                7 => 'Reschedule',
                // 8 => 'Payment Approved',
                //9 => 'Payment Rejected'
            ];

            $statusId = $latestFollowup ? $latestFollowup->status : 0;
            $statusName = $availableStatuses[$statusId] ?? 'Pending';

            return [
                'id' => $ride->id,
                'title' => $client ? $client->name : 'Ride',
                // If TBA (no specific time) return date-only strings; frontend should set allDay=true when isTba is true
                'start' => $ride->from_date ? ($isTba ? $ride->from_date->toDateString() : $ride->from_date->toISOString()) : null,
                'end' => $ride->to_date ? ($isTba ? $ride->to_date->toDateString() : $ride->to_date->toISOString()) : null,
                'allDay' => $isTba,
                'backgroundColor' => $backgroundColor,
                'borderColor' => $backgroundColor,
                'textColor' => '#fff',
                'extendedProps' => [
                    'clientName' => $client ? $client->name : 'N/A',
                    'contactNumber' => $client ? $client->contact_number : 'N/A',
                    'rideDate' => $ride->from_date ? $ride->from_date->format('d-m-Y') : 'N/A',
                    'rideTime' => $this->formatRideTime($ride->from_date, $ride->to_date),
                    'isTba' => $this->isTba($ride->from_date, $ride->to_date),
                    'fromPlace' => $ride->from_place ?? 'N/A',
                    'toPlace' => $ride->to_place ?? 'N/A',
                    'serviceNames' => implode(', ', $serviceNames),
                    'extraServiceNames' => implode(', ', $extraServiceNames),
                    'salesPersonName' => $representative ? $representative->name : 'N/A',
                    'pendingAmount' => number_format($pendingAmount, 2),
                    'description' => $lead ? $lead->description : '',
                    'numberOfPassengers' => $lead ? $lead->number_of_passengers : 0,
                    'occasion' => $lead ? $lead->occasion : '',
                    'statusName' => $statusName ?? 'Pending',
                    'statusId' => $statusId,
                ]
            ];
        })->filter(function ($event) {
            return $event['start'] !== null;
        });

        return response()->json($events->values());
    }

    /**
     * Get ride details by ID for calendar event clicks
     */
    public function getRideDetails($rideId)
    {
        try {
            // Validate that rideId is provided
            if (empty($rideId)) {
                return response()->json(['error' => 'Ride ID is required'], 400);
            }

            $ride = LeadRide::with([
                'enquiry.client',
                'enquiry.representative',
                'enquiry.vouchers.vendorPayments.paymentDetails.service',
                'enquiry.vouchers.vendorPayments.paymentDetails.extraService',
                'enquiry.leadFollowups' => function ($query) {
                    $query->orderByDesc('created_at');
                }
            ])->find($rideId);

            if (!$ride) {
                // Log the error for debugging
                Log::error("Ride not found with ID: " . $rideId);

                // Also check if any rides exist at all
                $totalRides = LeadRide::count();
                return response()->json([
                    'error' => 'Ride not found',
                    'ride_id' => $rideId,
                    'total_rides_in_db' => $totalRides,
                    'message' => 'The requested ride could not be found in the database.'
                ], 404);
            }

            $lead = $ride->enquiry;
            $client = $lead->client ?? null;
            $representative = $lead->representative ?? null;
            $latestFollowup = $lead->leadFollowups->first();

            // Get ALL trips/rides for this lead (same lead_id)
            $allTrips = LeadRide::where('lead_id', $ride->lead_id)
                ->orderBy('from_date', 'asc')
                ->get()
                ->map(function ($trip) {
                    return [
                        'id' => $trip->id,
                        'from_place' => $trip->from_place ?? 'N/A',
                        'to_place' => $trip->to_place ?? 'N/A',
                        'from_date' => $trip->from_date ? $trip->from_date->format('d-m-Y H:i') : 'N/A',
                        'to_date' => $trip->to_date ? $trip->to_date->format('d-m-Y H:i') : 'N/A',
                    ];
                });

            // Get product names (use Lead accessor which handles multiple formats)
            $productNames = [];
            try {
                $pn = $lead->product_names ?? [];
                if (is_string($pn)) {
                    $pn = json_decode($pn, true) ?: [];
                }
                $productNames = is_array($pn) ? $pn : [];
            } catch (\Exception $e) {
                Log::warning("Error fetching product names for ride {$rideId}: " . $e->getMessage());
                $productNames = [];
            }

            // Get service and extra service names from vendor payments (finalized services)
            $serviceNames = [];
            $extraServiceNames = [];

            $vouchers = $lead->vouchers ?? collect();
            foreach ($vouchers as $voucher) {
                // Use vendor payments (finalized services)
                if (!empty($voucher->vendorPayments) && $voucher->vendorPayments->isNotEmpty()) {
                    foreach ($voucher->vendorPayments as $vp) {
                        if (!empty($vp->paymentDetails) && $vp->paymentDetails->isNotEmpty()) {
                            foreach ($vp->paymentDetails as $pd) {
                                if (!empty($pd->service_id) && !$pd->is_extra_service) {
                                    if (isset($pd->service) && $pd->service) {
                                        $serviceNames[] = $pd->service->service;
                                    }
                                }

                                if (!empty($pd->service_id) && $pd->is_extra_service) {
                                    if (isset($pd->extraService) && $pd->extraService) {
                                        $extraServiceNames[] = $pd->extraService->extra_service;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Fallback to latest followup if vendor payments not available
            // if (empty($serviceNames) && $latestFollowup) {
            //     if ($latestFollowup->service_ids) {
            //         $serviceIds = is_array($latestFollowup->service_ids) ? $latestFollowup->service_ids : json_decode($latestFollowup->service_ids, true);
            //         if ($serviceIds && is_array($serviceIds)) {
            //             $serviceNames = Service::whereIn('id', $serviceIds)->pluck('service')->toArray();
            //         }
            //     }
            //     if ($latestFollowup->extra_service_ids) {
            //         $extraServiceIds = is_array($latestFollowup->extra_service_ids) ? $latestFollowup->extra_service_ids : json_decode($latestFollowup->extra_service_ids, true);
            //         if ($extraServiceIds && is_array($extraServiceIds)) {
            //             $extraServiceNames = ExtraService::whereIn('id', $extraServiceIds)->pluck('extra_service')->toArray();
            //         }
            //     }
            // }

            // Fallback to latest followup (handle services and extra services separately)
            if ($latestFollowup) {

                // Fallback for services
                if (empty($serviceNames) && $latestFollowup->service_ids) {
                    $serviceIds = is_array($latestFollowup->service_ids)
                        ? $latestFollowup->service_ids
                        : json_decode($latestFollowup->service_ids, true);

                    if ($serviceIds && is_array($serviceIds)) {
                        $serviceNames = Service::whereIn('id', $serviceIds)
                            ->pluck('service')
                            ->toArray();
                    }
                }

                // 🔥 Fallback for extra services (THIS WAS MISSING PROPERLY)
                if (empty($extraServiceNames) && $latestFollowup->extra_service_ids) {
                    $extraServiceIds = is_array($latestFollowup->extra_service_ids)
                        ? $latestFollowup->extra_service_ids
                        : json_decode($latestFollowup->extra_service_ids, true);

                    if ($extraServiceIds && is_array($extraServiceIds)) {
                        $extraServiceNames = ExtraService::whereIn('id', $extraServiceIds)
                            ->pluck('extra_service')
                            ->toArray();
                    }
                }
            }


            // Calculate pending amount using Payment Review logic
            $pendingAmount = $this->calculatePendingAmount($lead);

            // Compute status name from latest followup
            $statusMap = [1 => 'Active', 5 => 'Completed', 7 => 'Reschedule'];
            $statusName = null;
            if ($latestFollowup && isset($latestFollowup->status) && isset($statusMap[$latestFollowup->status])) {
                $statusName = $statusMap[$latestFollowup->status];
            }

            $rideData = [
                'id' => $ride->id,
                'lead_id' => $ride->lead_id,
                'client_name' => $client ? $client->name : 'N/A',
                'contact_number' => $client ? $client->contact_number : 'N/A',
                'status' => $latestFollowup && isset($latestFollowup->status) ? $latestFollowup->status : null,
                'status_name' => $statusName,
                'ride_date' => $ride->from_date ? $ride->from_date->format('d-m-Y') : 'N/A',
                'ride_time' => $this->formatRideTime($ride->from_date, $ride->to_date),
                'from_place' => $ride->from_place ?? 'N/A',
                'to_place' => $ride->to_place ?? 'N/A',
                'service_names' => implode(', ', $serviceNames),
                'extra_service_names' => implode(', ', $extraServiceNames),
                'product_names' => implode(', ', $productNames),
                'sales_person_name' => $representative ? $representative->name : 'N/A',
                'pending_amount' => number_format($pendingAmount, 2),
                'description' => $lead ? $lead->description : '',
                'number_of_passengers' => $lead ? $lead->number_of_passengers : 0,
                'occasion' => $lead ? $lead->occasion : '',
                'trips' => $allTrips, // All trips for this lead/enquiry
                'total_trips' => $allTrips->count(),
            ];

            return response()->json($rideData);
        } catch (\Exception $e) {
            Log::error("Error in getRideDetails for ride {$rideId}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while fetching ride details.',
                'debug_message' => $e->getMessage() // Remove this in production
            ], 500);
        }
    }

    /**
     * Debug method to test data availability
     */
    public function debugTestData()
    {
        try {
            // Test basic data access
            $ridesCount = LeadRide::count();
            $leadsCount = Lead::count();
            $clientsCount = Client::count();

            // Get a sample ride with all relationships
            $sampleRide = LeadRide::with([
                'enquiry.client',
                'enquiry.representative',
                'enquiry.leadFollowups'
            ])
                ->whereNotNull('from_date')
                ->first();

            $debug = [
                'total_rides' => $ridesCount,
                'total_leads' => $leadsCount,
                'total_clients' => $clientsCount,
                'sample_ride' => $sampleRide ? [
                    'id' => $sampleRide->id,
                    'from_date' => $sampleRide->from_date,
                    'to_date' => $sampleRide->to_date,
                    'from_place' => $sampleRide->from_place,
                    'to_place' => $sampleRide->to_place,
                    'lead_id' => $sampleRide->lead_id,
                    'enquiry' => $sampleRide->enquiry ? [
                        'id' => $sampleRide->enquiry->id,
                        'client_id' => $sampleRide->enquiry->client_id,
                        'representative_user_id' => $sampleRide->enquiry->representative_user_id,
                        'client' => $sampleRide->enquiry->client ? [
                            'id' => $sampleRide->enquiry->client->id,
                            'name' => $sampleRide->enquiry->client->name,
                            'contact_number' => $sampleRide->enquiry->client->contact_number,
                        ] : null,
                        'representative' => $sampleRide->enquiry->representative ? [
                            'id' => $sampleRide->enquiry->representative->id,
                            'name' => $sampleRide->enquiry->representative->name,
                        ] : null,
                        'followups_count' => $sampleRide->enquiry->leadFollowups->count(),
                    ] : null,
                ] : 'No rides found',
            ];

            return response()->json($debug, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Debug failed',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function rideStatus(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        try {
            // Read filter inputs (from_date, to_date, representative_user_id, status)
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');
            $salesRepFilter = $request->input('representative_user_id');
            $statusFilter = $request->input('status');

            $nameFilter    = $request->input('name');
            $phoneFilter   = $request->input('phone');
            $productFilter = $request->input('product_id');
            $serviceFilter = $request->input('service_id');
            $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

            // Build base query (only leads that have vouchers)
            $ridesQuery = LeadRide::with([
                'enquiry.client',
                'enquiry.representative',
                'enquiry.vouchers.invoice',
                'enquiry.leadVendorPayments.vendor',
                'enquiry.leadFollowups' => function ($query) {
                    $query->with('paymentAuditTrail')->orderByDesc('created_at');
                }
            ])->whereHas('enquiry.vouchers');

            // Apply date range filter when provided (filter by ride from_date)
            if ($fromDate && $toDate) {
                $ridesQuery->whereBetween(DB::raw('DATE(from_date)'), [
                    Carbon::parse($fromDate)->toDateString(),
                    Carbon::parse($toDate)->toDateString(),
                ]);
            }

            // Apply status filter if provided, otherwise include common statuses
            if ($statusFilter !== null && $statusFilter !== '') {
                $statusArray = is_array($statusFilter) ? $statusFilter : [$statusFilter];
                $ridesQuery->whereHas('enquiry.leadFollowups', function ($q) use ($statusArray) {
                    $q->whereIn('status', array_map('intval', $statusArray));
                });
            } else {
                // default behaviour: include a broad set of statuses
                $ridesQuery->whereHas('enquiry.leadFollowups', function ($q) {
                    $q->whereIn('status', [1, 2, 3, 4, 5, 6, 7]);
                });
            }

            if ($nameFilter) {
                $ridesQuery->whereHas('enquiry.client', function ($q) use ($nameFilter) {
                    $q->where('name', 'ilike', '%' . $nameFilter . '%');
                });
            }

            if ($phoneFilter) {
                $ridesQuery->whereHas('enquiry.client', function ($q) use ($phoneFilter) {
                    $q->where('contact_number', 'ilike', '%' . $phoneFilter . '%')
                        ->orWhere('alternate_number', 'ilike', '%' . $phoneFilter . '%');
                });
            }

            if ($productFilter) {
                $ridesQuery->whereHas('enquiry', function ($q) use ($productFilter) {
                    $q->where('product_ids', 'like', '%' . $productFilter . '%');
                });
            }

            if ($serviceFilter) {
                $ridesQuery->whereHas('enquiry.leadFollowups', function ($q) use ($serviceFilter) {
                    $q->where('service_ids', 'like', '%' . $serviceFilter . '%');
                });
            }


            $rideStatusPaginator = (clone $ridesQuery)
                ->setEagerLoads([])
                ->select('lead_id')
                ->selectRaw('MAX(created_at) as latest_created_at')
                ->groupBy('lead_id')
                ->orderByDesc('latest_created_at')
                ->paginate($perPage)
                ->appends($request->query());

            $pageLeadIds = collect($rideStatusPaginator->items())
                ->pluck('lead_id')
                ->filter()
                ->values();

            $rides = collect();
            if ($pageLeadIds->isNotEmpty()) {
                $rides = (clone $ridesQuery)
                    ->whereIn('lead_id', $pageLeadIds->all())
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->groupBy('lead_id');
            }

            // Transform the grouped data for display
            $ridesData = [];
            foreach ($pageLeadIds as $leadId) {
                $rideGroup = $rides->get($leadId);
                if (!$rideGroup || $rideGroup->isEmpty()) {
                    continue;
                }

                // Take the first ride as the main entry
                $mainRide = $rideGroup->first();
                $lead = $mainRide->enquiry;
                $client = $lead->client ?? null;
                $representative = $lead->representative ?? null;
                $latestFollowup = $lead->leadFollowups->first();

                // Get service names from latest followup
                $serviceNames = [];
                $extraServiceNames = [];
                if ($latestFollowup && $latestFollowup->service_ids) {
                    $serviceIds = is_array($latestFollowup->service_ids) ? $latestFollowup->service_ids : json_decode($latestFollowup->service_ids, true);
                    if ($serviceIds) {
                        $serviceNames = Service::whereIn('id', $serviceIds)->pluck('service')->toArray();
                    }
                }

                // Get extra service names from latest followup
                if ($latestFollowup && $latestFollowup->extra_service_ids) {
                    $extraServiceIds = is_array($latestFollowup->extra_service_ids) ? $latestFollowup->extra_service_ids : json_decode($latestFollowup->extra_service_ids, true);
                    if ($extraServiceIds) {
                        $extraServiceNames = ExtraService::whereIn('id', $extraServiceIds)->pluck('extra_service')->toArray();
                    }
                }

                // Calculate total amount
                $totalAmount = 0;
                if ($latestFollowup && $latestFollowup->total_amount && $latestFollowup->total_amount > 0) {
                    $totalAmount = (float) $latestFollowup->total_amount;
                } else {
                    $previousFollowupWithAmount = $lead->leadFollowups->filter(function ($f) {
                        return $f->total_amount && $f->total_amount > 0;
                    })->first();
                    if ($previousFollowupWithAmount) {
                        $totalAmount = (float) $previousFollowupWithAmount->total_amount;
                    } else if ($lead && isset($lead->total_amount) && $lead->total_amount > 0) {
                        $totalAmount = (float) $lead->total_amount;
                    }
                }

                // Get total received amount from approved payment audit trail (match PaymentReview)
                $totalReceivedAmount = 0;
                $followupIds = $lead->leadFollowups->pluck('id')->toArray();
                if (!empty($followupIds)) {
                    $totalReceivedAmount = PaymentAuditTrail::whereIn('lead_followup_id', $followupIds)
                        ->where('payment_status', 1) // only approved
                        ->sum('paid_amount');
                }

                $balanceAmount = $totalAmount - $totalReceivedAmount;

                // Get payment method from audit trail
                $paymentMethod = 'Cash';
                if ($latestFollowup && $latestFollowup->paymentAuditTrail->isNotEmpty()) {
                    $latestPayment = $latestFollowup->paymentAuditTrail->first();
                    $paymentMethod = $latestPayment->payment_method ?? 'Cash';
                }

                // Store all rides for this lead
                $allRides = $rideGroup->sortBy('from_date')->values();

                // Get invoice ID from first voucher
                $invoiceId = 'N/A';
                if ($lead->vouchers && $lead->vouchers->first()) {
                    $firstVoucher = $lead->vouchers->first();
                    if ($firstVoucher->invoice) {
                        $invoiceId = $firstVoucher->invoice->invoice_id ?? 'N/A';
                    }
                }

                // Get vendor name from first vendor payment
                $vendorName = 'N/A';
                if ($lead->leadVendorPayments && $lead->leadVendorPayments->first()) {
                    $firstVendorPayment = $lead->leadVendorPayments->first();
                    if ($firstVendorPayment->vendor) {
                        $vendorName = $firstVendorPayment->vendor->name ?? 'N/A';
                    }
                }

                // Get assigned rep name
                $assignedRep = 'N/A';
                if ($representative) {
                    $assignedRep = $representative->name ?? 'N/A';
                }

                // Get created date
                $createdDate = $lead->created_at ? $lead->created_at->format('d-m-Y') : 'N/A';

                $ridesData[] = [
                    'id' => $mainRide->id,
                    'lead_id' => $leadId,
                    'invoice_id' => $invoiceId,
                    'vendor_name' => $vendorName,
                    'assigned_rep' => $assignedRep,
                    'created_date' => $createdDate,
                    'created_date_sortable' => $lead->created_at ? $lead->created_at->format('Y-m-d H:i:s') : '0000-00-00 00:00:00',
                    'client_name' => $client ? $client->name : 'N/A',
                    'contact_number' => $client ? $client->contact_number : 'N/A',
                    'service_date' => $allRides->first()->from_date ? $allRides->first()->from_date->format('d-m-Y') : 'N/A',
                    'service_date_sortable' => $allRides->first()->from_date ? $allRides->first()->from_date->format('Y-m-d H:i:s') : '0000-00-00 00:00:00',
                    'service_names' => implode(', ', $serviceNames),
                    'extra_service_names' => implode(', ', $extraServiceNames),
                    'total_amount' => $totalAmount,
                    'received_amount' => $totalReceivedAmount,
                    'balance_amount' => $balanceAmount,
                    'payment_method' => $paymentMethod,
                    'status' => $latestFollowup ? $latestFollowup->status : 6,
                    'status_text' => $this->getStatusText($latestFollowup ? $latestFollowup->status : 6),
                    'all_rides' => $allRides // Store all rides for this lead
                ];
            }

            // Ensure we only include rides whose latest followup status matches the requested status
            // This prevents returning leads where an earlier followup matched the filter but the latest did not.
            if ($statusFilter !== null && $statusFilter !== '') {
                $statusArray = is_array($statusFilter) ? array_map('intval', $statusFilter) : [intval($statusFilter)];
                $ridesData = array_values(array_filter($ridesData, function ($r) use ($statusArray) {
                    $s = isset($r['status']) ? intval($r['status']) : null;
                    return $s !== null && in_array($s, $statusArray, true);
                }));
            }

            // Options shown in UI for ride-status filtering (Completed, Cancel, Reschedule)
            // $statusOptions = [
            //     5 => 'Completed',
            //     2 => 'Cancelled',
            //     7 => 'Reschedule',
            // ];

            $statusOptions = [
                1 => 'Active',
                2 => 'Cancelled',
                3 => 'Full Payment Received',
                4 => 'Partial Payment Received',
                5 => 'Completed',
                7 => 'Reschedule',
                8 => 'Approved',
            ];

            // Current filters to preserve UI selections
            $currentFilters = [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'status' => $statusFilter,
                'name'       => $nameFilter,       // ADD
                'phone'      => $phoneFilter,      // ADD
                'product_id' => $productFilter,    // ADD
                'service_id' => $serviceFilter,    // ADD
            ];
            $products = Product::where('status', 1)->orderBy('product')->get();   // ADD
            $services = Service::where('status', 1)->orderBy('service')->get();   // ADD

            return view('admin.account.rides.ride-status', compact('ridesData', 'rideStatusPaginator', 'statusOptions', 'currentFilters', 'products', 'services')); // PASS products and services to view
        } catch (\Exception $e) {
            Log::error("Error in rideStatus: " . $e->getMessage());
            return view('admin.account.rides.ride-status', ['ridesData' => collect()]);
        }
    }

    /**
     * Export ride status data to Excel or CSV
     */
    public function exportRideStatus(Request $request)
    {
        try {
            $format = $request->get('format', 'xlsx');
            $filters = $request->except('format');

            $fileName = 'ride_status_' . date('Y-m-d_His') . '.' . $format;

            return Excel::download(new RideStatusExport($filters), $fileName);
        } catch (\Exception $e) {
            Log::error('Error exporting ride status: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error exporting ride status: ' . $e->getMessage());
        }
    }

    /**
     * Get detailed ride status information for modal
     */
    public function getRideStatusDetails($rideId)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        try {
            $ride = LeadRide::with([
                'enquiry.client.country',
                'enquiry.client.city',
                'enquiry.representative',
                'enquiry.leadFollowups' => function ($query) {
                    $query->orderByDesc('created_at');
                }
            ])->find($rideId);

            if (!$ride) {
                return response()->json(['error' => 'Ride not found'], 404);
            }

            $lead = $ride->enquiry;
            $client = $lead->client ?? null;
            $representative = $lead->representative ?? null;
            $latestFollowup = $lead->leadFollowups->first();

            // Always get all rides for this lead
            $allRides = LeadRide::where('lead_id', $ride->lead_id)
                ->orderBy('from_date', 'asc')
                ->get();
            Log::info("Fetching all rides for status details", [
                'ride_id' => $rideId,
                'lead_id' => $ride->lead_id,
                'total_rides' => $allRides->count(),
                'latest_followup_date' => $latestFollowup ? $latestFollowup->created_at : 'no followup'
            ]);

            // Get service and extra service names
            $serviceNames = [];
            $extraServiceNames = [];
            $serviceExtraServices = [];

            Log::info("Getting service names", [
                'has_latest_followup' => $latestFollowup ? true : false,
                'followup_id' => $latestFollowup ? $latestFollowup->id : null,
                'service_ids' => $latestFollowup ? $latestFollowup->service_ids : null,
                'extra_service_ids' => $latestFollowup ? $latestFollowup->extra_service_ids : null,
                'lead_service_ids' => $lead ? $lead->service_ids : null
            ]);

            if ($latestFollowup) {
                if ($latestFollowup->service_ids) {
                    $serviceIds = is_array($latestFollowup->service_ids) ? $latestFollowup->service_ids : json_decode($latestFollowup->service_ids, true);
                    Log::info("Parsed service IDs from followup", ['service_ids' => $serviceIds]);
                    if ($serviceIds && is_array($serviceIds)) {
                        $serviceNames = Service::whereIn('id', $serviceIds)->pluck('service')->toArray();
                        Log::info("Found service names from followup", ['service_names' => $serviceNames]);
                        // For each service, get related extra services
                        foreach ($serviceIds as $sid) {
                            $service = Service::find($sid);
                            $relatedExtraServices = [];

                            // Fallback: get extra services from latestFollowup->extra_service_ids
                            if ($latestFollowup->extra_service_ids) {
                                $extraServiceIds = is_array($latestFollowup->extra_service_ids) ? $latestFollowup->extra_service_ids : json_decode($latestFollowup->extra_service_ids, true);
                                if ($extraServiceIds && is_array($extraServiceIds)) {
                                    $relatedExtraServices = ExtraService::whereIn('id', $extraServiceIds)->pluck('extra_service')->toArray();
                                }
                            }

                            $serviceExtraServices[$sid] = [
                                'service_name' => $service ? $service->service : '',
                                'extra_services' => $relatedExtraServices
                            ];
                        }
                    }
                }

                if ($latestFollowup->extra_service_ids) {
                    $extraServiceIds = is_array($latestFollowup->extra_service_ids) ? $latestFollowup->extra_service_ids : json_decode($latestFollowup->extra_service_ids, true);
                    Log::info("Parsed extra service IDs from followup", ['extra_service_ids' => $extraServiceIds]);
                    if ($extraServiceIds && is_array($extraServiceIds)) {
                        $extraServiceNames = ExtraService::whereIn('id', $extraServiceIds)->pluck('extra_service')->toArray();
                        Log::info("Found extra service names from followup", ['extra_service_names' => $extraServiceNames]);
                    }
                }
            }

            // If no services found from followup, try to get from the lead itself
            if (empty($serviceNames) && $lead && $lead->service_ids) {
                $leadServiceIds = is_array($lead->service_ids) ? $lead->service_ids : json_decode($lead->service_ids, true);
                Log::info("Parsed service IDs from lead", ['lead_service_ids' => $leadServiceIds]);
                if ($leadServiceIds && is_array($leadServiceIds)) {
                    $serviceNames = Service::whereIn('id', $leadServiceIds)->pluck('service')->toArray();
                    Log::info("Found service names from lead", ['service_names' => $serviceNames]);
                }
            }

            // Get payment history from followups that actually have audit trail entries
            // so we show only payment-related history. Load paymentAuditTrail ordered
            // by created_at (desc) so the first record is the latest audit.
            $paymentHistory = $lead->leadFollowups()
                ->whereNotNull('received_amount')
                ->where('received_amount', '>', 0)
                ->whereHas('paymentAuditTrail')
                ->with(['followedBy', 'paymentAuditTrail' => function ($q) {
                    $q->orderByDesc('created_at');
                }])
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($followup) {
                    // latest audit record (paymentAuditTrail is ordered desc)
                    $latestAudit = null;
                    if ($followup->paymentAuditTrail && $followup->paymentAuditTrail->isNotEmpty()) {
                        $latestAudit = $followup->paymentAuditTrail->first();
                    }

                    // Determine payment method from latest audit if present
                    $paymentMethod = $latestAudit ? ($latestAudit->payment_method ?? '') : '';

                    // Determine status strictly from the latest payment audit record.
                    // Do not fall back to the followup status as audit is the source
                    // of truth for payment state.
                    $auditStatus = $latestAudit && isset($latestAudit->payment_status) ? $latestAudit->payment_status : null;

                    // Map audit/payment status to human readable text
                    $statusText = '';
                    switch ($auditStatus) {
                        case 1:
                            $statusText = 'Payment Approved';
                            break;
                        case 2:
                            $statusText = 'Payment Rejected';
                            break;
                        case 3:
                            $statusText = 'Full Payment Received';
                            break;
                        case 4:
                            $statusText = 'Partial Payment Received';
                            break;
                        default:
                            $statusText = 'Payment Pending';
                    }

                    return [
                        'id' => $followup->id,
                        'amount' => $followup->received_amount,
                        'total_amount' => $followup->total_amount,
                        'status' => $auditStatus,
                        'status_text' => $statusText,
                        'followup_note' => $followup->followup_note,
                        'file' => $followup->file,
                        'created_at' => $followup->created_at,
                        'created_by_name' => $followup->followedBy ? $followup->followedBy->name : null,
                        'created_by_email' => $followup->followedBy ? $followup->followedBy->email : null,
                        'payment_method' => $paymentMethod,
                        'audit_trail' => $latestAudit ? [
                            'payment_method' => $latestAudit->payment_method,
                            'paid_date' => $latestAudit->paid_date,
                            'narration' => $latestAudit->narration,
                            'payment_status' => $latestAudit->payment_status,
                            'paid_amount' => $latestAudit->paid_amount
                        ] : null,
                    ];
                });

            // Calculate payment amounts using same logic as PaymentReviewController
            $totalAmount = 0;
            $totalReceived = 0;

            if ($latestFollowup) {
                $totalAmount = (float) $latestFollowup->total_amount;
            }

            // If total amount is still 0, try to get it from payment history
            if ($totalAmount == 0 && $paymentHistory->isNotEmpty()) {
                $latestPayment = $paymentHistory->first();
                $totalAmount = (float) $latestPayment['total_amount'];
                Log::info("Using total amount from payment history", ['total_amount' => $totalAmount]);
            }

            // Calculate received amount only from approved payments (audit trail status = 1)
            $followupIds = $lead->leadFollowups->pluck('id')->toArray();
            if (!empty($followupIds)) {
                $approvedPayments = PaymentAuditTrail::whereIn('lead_followup_id', $followupIds)
                    ->where('payment_status', 1) // Only approved payments
                    ->get();

                $totalReceived = $approvedPayments->sum('paid_amount');
            }

            Log::info("Payment calculations using audit trail", [
                'latest_followup_total' => $latestFollowup ? $latestFollowup->total_amount : 'no followup',
                'calculated_total' => $totalAmount,
                'approved_received' => $totalReceived,
                'followup_ids' => $followupIds,
                'payment_history_count' => $paymentHistory->count()
            ]);

            $balanceAmount = $totalAmount - $totalReceived;
            Log::info(['service_extra_services' => $serviceExtraServices]);
            // Attempt to fetch an existing refund for the lead's followups (if any)
            $refundData = null;
            try {
                // Collect all followup IDs for the lead to search broadly (refund might be tied to an older followup)
                $followupIds = $lead->leadFollowups->pluck('id')->toArray();

                $existingRefund = null;

                // Prefer refund attached to the latest followup if available
                if ($latestFollowup) {
                    $existingRefund = LeadRefund::where('lead_followup_id', $latestFollowup->id)->first();
                }

                // Fallback: find any refund for any followup of this lead (most recent)
                if (!$existingRefund && !empty($followupIds)) {
                    $existingRefund = LeadRefund::whereIn('lead_followup_id', $followupIds)
                        ->orderByDesc('created_at')
                        ->first();
                }

                if ($existingRefund) {
                    $refundData = [
                        'id' => $existingRefund->id,
                        'lead_followup_id' => $existingRefund->lead_followup_id,
                        'original_amount' => $existingRefund->original_amount,
                        'refund_amount' => $existingRefund->refund_amount,
                        'refund_type' => $existingRefund->refund_type,
                        'refund_date' => $existingRefund->refund_date,
                        'refund_proof' => $existingRefund->refund_proof,
                        'refund_reason' => $existingRefund->refund_reason,
                        'status' => $existingRefund->status,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('Error fetching existing refund for ride status', ['error' => $e->getMessage()]);
            }

            $response = [
                'ride' => [
                    'id' => $ride->id,
                    'lead_id' => $ride->lead_id,
                    'from_date' => $ride->from_date ? $ride->from_date->format('d-m-Y H:i') : null,
                    'to_date' => $ride->to_date ? $ride->to_date->format('d-m-Y H:i') : null,
                    'from_place' => $ride->from_place,
                    'to_place' => $ride->to_place,
                ],
                'client' => [
                    'name' => $client ? $client->name : 'N/A',
                    'email' => $client ? $client->email : 'N/A',
                    'contact_number' => $client ? $client->contact_number : 'N/A',
                    'alternate_number' => $client ? $client->alternate_number : 'N/A',
                    'country' => $client && $client->country ? $client->country->name : 'N/A',
                    'city' => $client && $client->city ? $client->city->name : 'N/A',
                    'address' => $client ? $client->address : 'N/A',
                ],
                'followup' => [
                    'status' => $latestFollowup ? $latestFollowup->status : 6,
                ],
                'service_names' => implode(', ', $serviceNames),
                'extra_service_names' => implode(', ', $extraServiceNames),
                'service_extra_services' => $serviceExtraServices,
                'total_amount' => $totalAmount,
                'received_amount' => $totalReceived, // Use total from approved audit trail payments
                'balance_amount' => $balanceAmount,
                'payment_history' => $paymentHistory,
                'all_rides' => $allRides->map(function ($r) {
                    // Handle both Eloquent models and already transformed arrays
                    if (is_array($r)) {
                        return $r; // Already transformed
                    } else {
                        return [
                            'id' => $r->id,
                            'from_date' => $r->from_date ? $r->from_date->format('d-m-Y H:i') : null,
                            'to_date' => $r->to_date ? $r->to_date->format('d-m-Y H:i') : null,
                            'from_place' => $r->from_place,
                            'to_place' => $r->to_place,
                        ];
                    }
                }),
                'status' => $latestFollowup ? $latestFollowup->status : 6,
                // Include latest followup id so front-end can reference the correct LeadFollowup record
                'followup_id' => $latestFollowup ? $latestFollowup->id : null,
                'refund' => $refundData,
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error("Error in getRideStatusDetails for ride {$rideId}: " . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Update ride status
     */
    public function updateRideStatus(Request $request, $rideId)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $request->validate([
                'status' => 'required|integer|in:2,5,7',
                'total_amount' => 'required|numeric|min:0'
            ]);

            $ride = LeadRide::with(['enquiry.leadFollowups' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }, 'enquiry.client'])->find($rideId);

            if (!$ride) {
                return response()->json(['success' => false, 'message' => 'Ride not found'], 404);
            }

            $latestFollowup = $ride->enquiry->leadFollowups->first();

            if (!$latestFollowup) {
                return response()->json(['success' => false, 'message' => 'No followup found'], 404);
            }

            // Helper function to parse IDs from various formats
            $parseIds = function ($ids) {
                if (empty($ids)) {
                    return [];
                }

                if (is_array($ids)) {
                    return $ids;
                }

                if (is_string($ids)) {
                    // Handle JSON strings
                    if (str_starts_with($ids, '[')) {
                        try {
                            $decoded = json_decode($ids, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                return $decoded;
                            }
                        } catch (\Exception $e) {
                            Log::warning("Error decoding JSON for IDs: {$ids}", ['error' => $e->getMessage()]);
                        }
                    }

                    // Handle string representations like "[\"uuid1\",\"uuid2\"]"
                    $cleaned = trim($ids, '"[]');
                    if (!empty($cleaned)) {
                        return array_map('trim', explode(',', str_replace('"', '', $cleaned)));
                    }
                }

                return [];
            };

            // Get service IDs - prioritize from latest followup, then from enquiry
            $serviceIds = $parseIds($latestFollowup->service_ids ?? $ride->enquiry->service_ids ?? []);

            // Get extra service IDs - prioritize from latest followup, then from enquiry
            $extraServiceIds = $parseIds($latestFollowup->extra_service_ids ?? $ride->enquiry->extra_service_ids ?? []);

            // Ensure we have proper UUID arrays
            $serviceIds = array_values(array_unique(array_filter($serviceIds, function ($id) {
                return is_string($id) && Str::isUuid($id);
            })));

            $extraServiceIds = array_values(array_unique(array_filter($extraServiceIds, function ($id) {
                return is_string($id) && Str::isUuid($id);
            })));

            // Create new followup with the same services and extra services
            $newFollowup = LeadFollowup::create([
                'id' => Str::uuid(),
                'lead_id' => $ride->lead_id,
                'status' => $request->status,
                'followup_note' => [
                    2 => 'Ride has been cancelled.',
                    5 => 'Ride has been completed successfully.',
                    7 => 'Ride has been rescheduled.'
                ][$request->status],
                'next_followup_date' => now(),
                'followed_by' => auth()->id(),
                'total_amount' => $request->total_amount,
                'received_amount' => $latestFollowup->received_amount,
                'service_ids' => !empty($serviceIds) ? json_encode($serviceIds) : null,
                'extra_service_ids' => !empty($extraServiceIds) ? json_encode($extraServiceIds) : null,
                // Preserve service breakdown and discounts so status changes don't wipe them
                'service_amount' => $latestFollowup->service_amount ?? null,
                'discount_amount' => $latestFollowup->discount_amount ?? null,
                'service_details' => $latestFollowup->service_details ?? null,
            ]);

            Log::info('Ride status updated with services', [
                'ride_id' => $rideId,
                'new_status' => $request->status,
                'service_ids' => $serviceIds,
                'extra_service_ids' => $extraServiceIds,
                'followup_id' => $newFollowup->id
            ]);

            // If ride is completed (status = 5), sync to Airpoints
            $airpointsResponse = null;
            if ($request->status == 5) {
                try {
                    Log::info('Ride completed - initiating Airpoints sync', [
                        'ride_id' => $rideId,
                        'total_amount' => $request->total_amount
                    ]);

                    $airpointsService = app(AirpointsIntegrationService::class);
                    $airpointsResponse = $airpointsService->processCompletedRide($ride, $request->total_amount);

                    if ($airpointsResponse['success']) {
                        Log::info('Ride successfully synced to Airpoints', [
                            'ride_id' => $rideId,
                            'points_awarded' => $airpointsResponse['points_awarded'] ?? 0,
                            'customer_id' => $airpointsResponse['customer_sync']['customer_id'] ?? null,
                            'product_id' => $airpointsResponse['product_sync']['product_id'] ?? null
                        ]);
                    } else {
                        Log::warning('Airpoints sync failed for completed ride', [
                            'ride_id' => $rideId,
                            'error' => $airpointsResponse['message'] ?? 'Unknown error'
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Airpoints sync exception', [
                        'ride_id' => $rideId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Don't fail the ride status update if Airpoints sync fails
                    $airpointsResponse = [
                        'success' => false,
                        'message' => 'Exception: ' . $e->getMessage()
                    ];
                }
            }

            // If status is completed (5), auto-generate invoice
            $invoiceData = null;
            if ($request->status == 5) {
                try {
                    $lead = $ride->enquiry;
                    $voucher = $lead ? $lead->vouchers()->first() : null;

                    if ($lead && $voucher) {
                        // Check if invoice already exists
                        $existingInvoice = \App\Models\Invoice::where('voucher_id', $voucher->id)->first();

                        if ($existingInvoice) {
                            // Auto-generate invoice in active state (not auto-finalizing)
                            if ($existingInvoice->status != 1) {
                                $existingInvoice->status = 1;
                                $existingInvoice->save();
                                Log::info('Auto-generated invoice on ride completion (active state)', [
                                    'ride_id' => $rideId,
                                    'invoice_id' => $existingInvoice->invoice_id,
                                ]);
                            }

                            $invoiceData = [
                                'invoice_id' => $existingInvoice->invoice_id,
                                'redirect_url' => route('admin.account.invoices') . '?status=1'
                            ];
                        } else {
                            // Generate new invoice (same logic as generateInvoice method)
                            $year = Carbon::now()->year;
                            $month = Carbon::now()->format('m');
                            $prefix = "INV-{$year}-{$month}-";

                            $lastInvoice = DB::table('invoices')
                                ->where('invoice_id', 'like', "{$prefix}%")
                                ->orderByRaw("CAST(RIGHT(invoice_id,4) AS INTEGER) DESC")
                                ->lockForUpdate()
                                ->first();

                            $newNumber = $lastInvoice ? intval(substr($lastInvoice->invoice_id, -4)) + 1 : 1;
                            $invoiceId = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

                            $companyName = optional($lead->client)->name ?: 'Accretion Aviation';

                            // Create invoice directly with status = 1 (active, not finalized)
                            $invoice = \App\Models\Invoice::create([
                                'id' => Str::uuid(),
                                'invoice_id' => $invoiceId,
                                'voucher_id' => $voucher->id,
                                'company_name' => $companyName,
                                'gst_number' => null,
                                'billing_address' => optional($lead->client)->address ?? null,
                                'status' => 1
                            ]);

                            $invoiceData = [
                                'invoice_id' => $invoice->invoice_id,
                                'redirect_url' => route('admin.account.invoices') . '?status=1'
                            ];

                            Log::info('Auto-generated invoice on ride completion (active state)', [
                                'ride_id' => $rideId,
                                'invoice_id' => $invoice->invoice_id,
                                'voucher_id' => $voucher->id
                            ]);
                        }
                    } else {
                        Log::warning('Could not auto-generate invoice: no lead or voucher found', [
                            'ride_id' => $rideId
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Auto-invoice generation failed (ride status still updated)', [
                        'ride_id' => $rideId,
                        'error' => $e->getMessage()
                    ]);
                    // Don't fail the status update if invoice generation fails
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'data' => [
                    'old_status' => $latestFollowup->status,
                    'new_status' => $request->status,
                    'followup_id' => $newFollowup->id,
                    'airpoints_sync' => $airpointsResponse,
                    'invoice' => $invoiceData
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("Error updating ride status for ride {$rideId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'Error updating status'], 500);
        }
    }

    /**
     * Update ride dates
     */
    public function updateRideDates(Request $request, $rideId)
    {
        try {
            Log::info("updateRideDates called", [
                'ride_id' => $rideId,
                'request_data' => $request->all(),
                'headers' => $request->headers->all()
            ]);

            $ride = LeadRide::find($rideId);

            if (!$ride) {
                Log::error("Ride not found for update dates: {$rideId}");
                return response()->json(['success' => false, 'message' => 'Ride not found'], 404);
            }

            $noDate = $request->boolean('no_date');
            $multipleTrips = $request->boolean('multiple_trips');

            Log::info("Processing update request", [
                'no_date' => $noDate,
                'multiple_trips' => $multipleTrips,
                'has_trip_dates' => $request->has('trip_dates')
            ]);

            if ($multipleTrips && $request->has('trip_dates')) {
                // Handle multiple trips update
                $tripDates = $request->input('trip_dates');
                Log::info("Updating multiple trips", ['trip_dates' => $tripDates]);

                foreach ($tripDates as $tripData) {
                    $tripRide = LeadRide::find($tripData['trip_id']);
                    if ($tripRide) {
                        $updateData = [];

                        if ($noDate) {
                            $updateData['from_date'] = null;
                            $updateData['to_date'] = null;
                        } else {
                            if (!empty($tripData['from_date'])) {
                                try {
                                    $updateData['from_date'] = Carbon::parse($tripData['from_date']);
                                } catch (\Exception $e) {
                                    Log::error("Error parsing from_date: {$tripData['from_date']}", ['error' => $e->getMessage()]);
                                    return response()->json(['success' => false, 'message' => 'Invalid from_date format'], 400);
                                }
                            }
                            if (!empty($tripData['to_date'])) {
                                try {
                                    $updateData['to_date'] = Carbon::parse($tripData['to_date']);
                                } catch (\Exception $e) {
                                    Log::error("Error parsing to_date: {$tripData['to_date']}", ['error' => $e->getMessage()]);
                                    return response()->json(['success' => false, 'message' => 'Invalid to_date format'], 400);
                                }
                            }
                        }

                        $result = $tripRide->update($updateData);
                    } else {
                        Log::error("Trip not found: {$tripData['trip_id']}");
                        return response()->json(['success' => false, 'message' => "Trip {$tripData['trip_id']} not found"], 404);
                    }
                }

                return response()->json(['success' => true, 'message' => 'All trip dates updated successfully']);
            } else {
                // Handle single trip update
                $updateData = [];

                if ($noDate) {
                    $updateData['from_date'] = null;
                    $updateData['to_date'] = null;
                } else {
                    if ($request->has('from_date') && !empty($request->from_date)) {
                        try {
                            $updateData['from_date'] = Carbon::parse($request->from_date);
                        } catch (\Exception $e) {
                            return response()->json(['success' => false, 'message' => 'Invalid from_date format'], 400);
                        }
                    }
                    if ($request->has('to_date') && !empty($request->to_date)) {
                        try {
                            $updateData['to_date'] = Carbon::parse($request->to_date);
                        } catch (\Exception $e) {
                            Log::error("Error parsing single to_date: {$request->to_date}", ['error' => $e->getMessage()]);
                            return response()->json(['success' => false, 'message' => 'Invalid to_date format'], 400);
                        }
                    }
                    Log::info("Setting new dates for ride {$rideId}", $updateData);
                }

                if (empty($updateData)) {
                    Log::warning("No update data provided for ride {$rideId}");
                    return response()->json(['success' => false, 'message' => 'No valid date data provided'], 400);
                }

                $result = $ride->update($updateData);
                return response()->json(['success' => true, 'message' => 'Dates updated successfully']);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating dates: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Generate invoice for completed rides
     */
    public function generateInvoice(Request $request, $rideId)
    {
        try {
            $ride = LeadRide::with(['enquiry.client', 'enquiry.vouchers'])->find($rideId);

            if (!$ride) {
                return response()->json(['success' => false, 'message' => 'Ride not found'], 404);
            }

            $lead = $ride->enquiry;
            if (!$lead) {
                return response()->json(['success' => false, 'message' => 'Associated lead not found'], 404);
            }

            // Try to find a voucher for this lead. Prefer the first one (most code paths expect a voucher)
            $voucher = $lead->vouchers()->first();
            if (!$voucher) {
                return response()->json(['success' => false, 'message' => 'No voucher found for this ride/lead'], 400);
            }

            // If an invoice already exists for this voucher, return it (no duplicate creation)
            $existingInvoice = \App\Models\Invoice::where('voucher_id', $voucher->id)->first();
            if ($existingInvoice) {
                return response()->json([
                    'success' => true,
                    'message' => 'Invoice already exists',
                    'invoice_id' => $existingInvoice->invoice_id,
                    'redirect_url' => route('admin.account.invoices') . '?open_invoice=' . $voucher->id
                ]);
            }

            // Create a fresh invoice (generate invoice id matching InvoiceController format INV-YYYY-MM-####)
            $year = Carbon::now()->year;
            $month = Carbon::now()->format('m');
            DB::beginTransaction();
            try {
                $lastInvoice = DB::table('invoices')
                    ->where('invoice_id', 'like', "INV-{$year}-%")
                    ->orderByRaw("CAST(RIGHT(invoice_id,4) AS INTEGER) DESC")
                    ->lockForUpdate()
                    ->first();

                if ($lastInvoice) {
                    $lastNumber = intval(substr($lastInvoice->invoice_id, -4));
                    $newNumber = $lastNumber + 1;
                } else {
                    $newNumber = 1;
                }

                // Use year+month prefix now (INV-YYYY-MM-####)
                $invoiceId = "INV-{$year}-{$month}-" . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

                // Use optional() helper to avoid trying to read properties on null
                $companyName = optional($lead->client)->name ?: 'Accretion Aviation';

                if (!$lead->client) {
                    Log::warning("generateInvoice: Lead {$lead->id} has no client. Using fallback company name and billing address.", ['lead_id' => $lead->id, 'voucher_id' => $voucher->id ?? null]);
                }

                $invoice = \App\Models\Invoice::create([
                    'id' => Str::uuid(),
                    'invoice_id' => $invoiceId,
                    'voucher_id' => $voucher->id,
                    'company_name' => $companyName,
                    'gst_number' => null,
                    'billing_address' => optional($lead->client)->address ?? null,
                    'status' => 1
                ]);

                DB::commit();
            } catch (\Exception $ex) {
                DB::rollBack();
                throw $ex;
            }

            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully',
                'invoice_id' => $invoice->invoice_id,
                'redirect_url' => route('admin.account.invoices') . '?open_invoice=' . $voucher->id
            ]);
        } catch (\Exception $e) {
            Log::error("Error generating invoice: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error generating invoice'], 500);
        }
    }

    /**
     * Generate refund note for canceled rides
     */
    public function generateRefundNote(Request $request, $rideId)
    {
        try {
            $ride = LeadRide::with([
                'enquiry.client',
                'enquiry.leadFollowups' => function ($query) {
                    $query->where('status', 2)->orderByDesc('created_at');
                }
            ])->find($rideId);

            if (!$ride) {
                return response()->json(['success' => false, 'message' => 'Ride not found'], 404);
            }

            $lead = $ride->enquiry;
            $latestFollowup = $lead->leadFollowups->first();

            if (!$latestFollowup || $latestFollowup->status != 2) {
                return response()->json(['success' => false, 'message' => 'Ride is not cancelled'], 400);
            }

            // Redirect to refund notes module with the ride ID
            $refundUrl = route('admin.refunds.index') . '?ride_id=' . $rideId;

            return response()->json([
                'success' => true,
                'message' => 'Redirecting to refund notes',
                'redirect_url' => $refundUrl
            ]);
        } catch (\Exception $e) {
            Log::error("Error generating refund note: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error processing refund note'], 500);
        }
    }
    // public function sendRefundEmail(Request $request, $rideId)
    //     {
    //         try {
    //             // ── 1. Load LeadRide — note: relationship is enquiry(), NOT lead() ──
    //             $ride = \App\Models\LeadRide::with([
    //                 'enquiry.client',
    //                 'enquiry.leadFollowups.refunds',
    //                 'enquiry.leadVendorPayments.vendor',
    //                 'serviceAddress',
    //             ])->findOrFail($rideId);

    //             $lead   = $ride->enquiry;          // Lead model
    //             $client = $lead->client;           // Client model

    //             // ── 2. Get the latest LeadRefund via Lead → LeadFollowup → LeadRefund ─
    //             // LeadRefund belongs to LeadFollowup via lead_followup_id
    //             // LeadFollowup belongs to Lead via lead_id
    //             $refund = \App\Models\LeadRefund::whereHas('leadFollowup', function ($q) use ($lead) {
    //                     $q->where('lead_id', $lead->id);
    //                 })
    //                 ->latest()
    //                 ->first();

    //             if (!$refund) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'No refund record found for this ride.',
    //                 ], 404);
    //             }

    //             // ── 3. Resolve the refund proof file on disk ────────────────────────
    //             $proofFilePath = null;
    //             if (!empty($refund->refund_proof)) {
    //                 $candidate = storage_path('app/public/' . ltrim($refund->refund_proof, '/'));
    //                 if (file_exists($candidate)) {
    //                     $proofFilePath = $candidate;
    //                 } else {
    //                     Log::warning('sendRefundEmail: proof file not found on disk', [
    //                         'ride_id' => $rideId,
    //                         'path'    => $candidate,
    //                     ]);
    //                 }
    //             }

    //             // ── 4. Get service name from the ride's LeadFollowup service_ids ────
    //             $serviceName = 'Service';
    //             try {
    //                 $latestFollowup = $lead->leadFollowups()->orderBy('created_at', 'desc')->first();
    //                 if ($latestFollowup && !empty($latestFollowup->service_ids)) {
    //                     $serviceIds = is_array($latestFollowup->service_ids)
    //                         ? $latestFollowup->service_ids
    //                         : json_decode($latestFollowup->service_ids, true);

    //                     if (!empty($serviceIds)) {
    //                         $firstService = \App\Models\Service::find($serviceIds[0]);
    //                         if ($firstService) {
    //                             $serviceName = $firstService->service ?? $firstService->service_name ?? 'Service';
    //                         }
    //                     }
    //                 }
    //             } catch (\Throwable $e) {
    //                 Log::warning('sendRefundEmail: could not resolve service name', ['error' => $e->getMessage()]);
    //             }

    //             // ── 5. Get service date from the ride segment ────────────────────────
    //             $serviceDate = 'N/A';
    //             try {
    //                 if (!empty($ride->from_date)) {
    //                     $serviceDate = Carbon::parse($ride->from_date)->format('jS F, Y');
    //                 }
    //             } catch (\Throwable $e) {
    //                 $serviceDate = $ride->from_date ?? 'N/A';
    //             }

    //             // ── 6. Get vendor name + email from LeadVendorPayment ───────────────
    //             $vendorName  = 'N/A';
    //             $vendorEmail = null;
    //             try {
    //                 $vendorPayment = $lead->leadVendorPayments()->with('vendor')->latest()->first();
    //                 if ($vendorPayment && $vendorPayment->vendor) {
    //                     $vendorName  = $vendorPayment->vendor->name  ?? 'N/A';
    //                     $vendorEmail = $vendorPayment->vendor->email ?? null;
    //                 }
    //             } catch (\Throwable $e) {
    //                 Log::warning('sendRefundEmail: could not load vendor', ['error' => $e->getMessage()]);
    //             }

    //             // ── 7. Format refund date for display ───────────────────────────────
    //             $refundDateFormatted = 'N/A';
    //             try {
    //                 if (!empty($refund->refund_date)) {
    //                     $refundDateFormatted = Carbon::parse($refund->refund_date)->format('jS F, Y');
    //                 }
    //             } catch (\Throwable $e) {
    //                 $refundDateFormatted = $refund->refund_date ?? 'N/A';
    //             }

    //             // ── 8. Build $data arrays for the two email templates ───────────────

    //             // Customer email: "Your refund has been processed"
    //             $customerData = [
    //                 'client_name'   => $client->name        ?? 'Customer',
    //                 'service'       => $serviceName,
    //                 'service_date'  => $serviceDate,
    //                 'refund_amount' => $refund->refund_amount,
    //                 'refund_type'   => $refund->refund_type  ?? 'N/A',
    //                 'refund_date'   => $refundDateFormatted,
    //                 'refund_reason' => $refund->refund_reason ?? '',
    //             ];

    //             // Vendor email: "Payment has been made to your account"
    //             $vendorData = [
    //                 'vendor_name'           => $vendorName,
    //                 'client_name'           => $client->name ?? 'Customer',
    //                 'service'               => $serviceName,
    //                 'service_date'          => $serviceDate,
    //                 'vendor_payment_amount' => $refund->refund_amount, // vendor sees same refund amount
    //                 'refund_type'           => $refund->refund_type    ?? 'N/A',
    //                 'refund_date'           => $refundDateFormatted,
    //                 'refund_reason'         => $refund->refund_reason  ?? '',
    //             ];

    //             $errors = [];

    //             // ── 9. Send email to customer ───────────────────────────────────────
    //             $customerEmail = $client->email ?? null;
    //             if (!empty($customerEmail) && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    //                 try {
    //                     Mail::to($customerEmail)->send(new \App\Mail\RefundMail(
    //                         'emails.refund-customer',
    //                         'Refund Processed for Your Booking – ' . $serviceName,
    //                         $customerData,
    //                         $proofFilePath
    //                     ));
    //                     Log::info('Refund customer email sent', [
    //                         'ride_id' => $rideId,
    //                         'to'      => $customerEmail,
    //                     ]);
    //                 } catch (\Exception $e) {
    //                     Log::error('sendRefundEmail: customer mail failed', ['error' => $e->getMessage()]);
    //                     $errors[] = 'Customer email failed: ' . $e->getMessage();
    //                 }
    //             } else {
    //                 Log::warning('sendRefundEmail: missing or invalid customer email', ['email' => $customerEmail]);
    //                 $errors[] = 'Customer email is missing or invalid.';
    //             }

    //             // ── 10. Send email to vendor ────────────────────────────────────────
    //             if (!empty($vendorEmail) && filter_var($vendorEmail, FILTER_VALIDATE_EMAIL)) {
    //                 try {
    //                     Mail::to($vendorEmail)->send(new \App\Mail\RefundMail(
    //                         'emails.refund-vendor',
    //                         'Payment Notification – ' . ($client->name ?? 'Customer') . ' – ' . $serviceName,
    //                         $vendorData,
    //                         $proofFilePath
    //                     ));
    //                     Log::info('Refund vendor email sent', [
    //                         'ride_id' => $rideId,
    //                         'to'      => $vendorEmail,
    //                     ]);
    //                 } catch (\Exception $e) {
    //                     Log::error('sendRefundEmail: vendor mail failed', ['error' => $e->getMessage()]);
    //                     $errors[] = 'Vendor email failed: ' . $e->getMessage();
    //                 }
    //             } else {
    //                 // Vendor email missing is a soft warning — don't block the whole request
    //                 Log::warning('sendRefundEmail: vendor email missing or invalid', [
    //                     'vendor' => $vendorName,
    //                     'email'  => $vendorEmail,
    //                 ]);
    //                 // Not added to $errors — we still consider overall success if customer email went through
    //             }

    //             // ── 11. Return response ─────────────────────────────────────────────
    //             if (!empty($errors)) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => implode(' | ', $errors),
    //                 ], 422);
    //             }

    //             return response()->json([
    //                 'success' => true,
    //                 'message' => 'Refund confirmation emails sent successfully.',
    //             ]);

    //         } catch (\Exception $e) {
    //             Log::error('sendRefundEmail: unexpected error', [
    //                 'ride_id' => $rideId,
    //                 'error'   => $e->getMessage(),
    //                 'trace'   => $e->getTraceAsString(),
    //             ]);

    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Error sending refund emails: ' . $e->getMessage(),
    //             ], 500);
    //         }
    // }
    //    public function sendRefundEmail(Request $request, $rideId)
    // {
    //     try {
    //         // ── 1. Load ride ─────────────────────────────────────────────────────
    //         $ride = \App\Models\LeadRide::with([
    //             'enquiry.client',
    //             'enquiry.leadFollowups',
    //             'enquiry.leadVendorPayments.vendor',
    //             'enquiry.leadVendorPayments.paymentDetails.service',
    //             'serviceAddress',
    //         ])->findOrFail($rideId);

    //         $lead   = $ride->enquiry;
    //         $client = $lead->client;

    //         Log::info('sendRefundEmail: started', ['ride_id' => $rideId, 'lead_id' => $lead->id]);

    //         // ── 2. Get latest refund ─────────────────────────────────────────────
    //         $refund = \App\Models\LeadRefund::whereHas('leadFollowup', function ($q) use ($lead) {
    //                 $q->where('lead_id', $lead->id);
    //             })
    //             ->latest()
    //             ->first();

    //         if (!$refund) {
    //             return response()->json(['success' => false, 'message' => 'No refund record found.'], 404);
    //         }

    //         Log::info('sendRefundEmail: refund found', [
    //             'refund_id' => $refund->id,
    //             'amount'    => $refund->refund_amount,
    //             'proof'     => $refund->refund_proof,
    //         ]);

    //         // ── 3. Build data fields ─────────────────────────────────────────────
    //         $firstVendorPayment = $lead->leadVendorPayments->first();
    //         $firstDetail        = $firstVendorPayment ? $firstVendorPayment->paymentDetails->first() : null;
    //         $serviceObj         = $firstDetail ? ($firstDetail->service ?? null) : null;
    //         $serviceName        = $serviceObj->service ?? ($serviceObj->service_name ?? 'N/A');

    //         $productName = 'N/A';
    //         if ($serviceObj && isset($serviceObj->products) && $serviceObj->products->first()) {
    //             $productName = $serviceObj->products->first()->product ?? 'N/A';
    //         }

    //         $pickup_date = 'N/A';
    //         $pickup_time = 'N/A';
    //         if ($ride && !empty($ride->from_date)) {
    //             try {
    //                 $cd          = Carbon::parse($ride->from_date);
    //                 $pickup_date = $cd->format('jS F, Y');
    //                 $pickup_time = !empty($ride->is_tba) ? 'TBA' : $cd->format('h:i A');
    //             } catch (\Throwable $ex) {
    //                 $pickup_date = is_string($ride->from_date) ? date('jS F, Y', strtotime($ride->from_date)) : 'N/A';
    //                 $pickup_time = is_string($ride->from_date) ? date('h:i A', strtotime($ride->from_date)) : 'N/A';
    //             }
    //         }

    //         $refundAmountFormatted = number_format($refund->refund_amount ?? 0, 2);
    //         $refundDateFormatted   = 'N/A';
    //         try {
    //             if (!empty($refund->refund_date)) {
    //                 $refundDateFormatted = Carbon::parse($refund->refund_date)->format('jS F, Y');
    //             }
    //         } catch (\Throwable $e) {}

    //         $vendorName = $firstVendorPayment && $firstVendorPayment->vendor
    //             ? ($firstVendorPayment->vendor->name ?? 'Vendor')
    //             : 'Vendor';

    //         // ── 4. Payment amounts ───────────────────────────────────────────────
    //         $paidAmount    = floatval($refund->refund_amount ?? 0);
    //         $totalAmount   = floatval(
    //             optional($firstVendorPayment)->total_amount
    //             ?? optional($firstVendorPayment)->vendor_payment_amount
    //             ?? $paidAmount
    //         );
    //         $pendingAmount = max(0, $totalAmount - $paidAmount);
    //         $balanceAmount = $pendingAmount;

    //         // ── 5. Sanitize helper ───────────────────────────────────────────────
    //         $sanitize = function ($arr) {
    //             return array_map(function ($v) {
    //                 if (is_null($v))                    return '';
    //                 if (is_array($v) || is_object($v))  return json_encode($v);
    //                 return (string) $v;
    //             }, $arr);
    //         };

    //         // ── 6. WhatsApp templates & data arrays ──────────────────────────────

    //         // CUSTOMER — refund_cust_notify_v2 (5 variables, Document header)
    //         $customerWaTemplate = 'refund_cust_notify_v2';
    //         $customerWaData = $sanitize([
    //             $client->name ?? 'Customer',   // {{1}} Customer Name
    //             $serviceName,                   // {{2}} Service Name
    //             $pickup_date,                   // {{3}} Service Date
    //             $refundAmountFormatted,         // {{4}} Refund Amount
    //             $refundDateFormatted,           // {{5}} Refund Date
    //         ]);

    //         // VENDOR — refund_vendor_notify_v2 (9 variables, Document header)
    //         $vendorWaTemplate = 'refund_vendor_notify_v2';
    //         $vendorWaData = $sanitize([
    //             $vendorName,                          // {{1}} Vendor Name
    //             $client->name ?? 'Customer',          // {{2}} Customer Name
    //             $serviceName,                         // {{3}} Service Name
    //             $pickup_date,                         // {{4}} Service Date
    //             number_format($paidAmount, 2),        // {{5}} Amount Paid
    //             number_format($pendingAmount, 2),     // {{6}} Pending
    //             number_format($balanceAmount, 2),     // {{7}} Balance
    //             $refundDateFormatted,                 // {{8}} Payment Date
    //             $refund->refund_type ?? 'N/A',        // {{9}} Payment Mode
    //         ]);

    //         // ── 7. Resolve WhatsApp file (refund proof — image or PDF) ───────────
    //         $waFileUrl     = null;
    //         $proofFilePath = null; // email attachment (local path)

    //         if (!empty($refund->refund_proof)) {
    //             $relative  = ltrim($refund->refund_proof, '/');
    //             $fullPath  = storage_path('app/public/' . $relative);
    //             $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

    //             if (file_exists($fullPath)) {
    //                 // Email attachment — always use local path
    //                 $proofFilePath = $fullPath;
    //                 Log::info('sendRefundEmail: proof file found for email', ['path' => $fullPath]);

    //                 // WhatsApp — use public URL (Document header accepts PDF + image)
    //                 $waFileUrl = rtrim(config('app.url'), '/') . '/storage/' . $relative;
    //                 Log::info('sendRefundEmail: proof file URL for WhatsApp', [
    //                     'url' => $waFileUrl,
    //                     'ext' => $extension,
    //                 ]);
    //             } else {
    //                 Log::warning('sendRefundEmail: proof file NOT found on disk', [
    //                     'path' => $fullPath,
    //                     'note' => 'File may only exist locally — upload on server to test WhatsApp',
    //                 ]);
    //             }
    //         } else {
    //             Log::warning('sendRefundEmail: no refund proof uploaded', ['refund_id' => $refund->id]);
    //         }

    //         $waType       = 2; // Document header
    //         $skipWhatsApp = empty($waFileUrl);

    //         if ($skipWhatsApp) {
    //             Log::warning('sendRefundEmail: WhatsApp skipped — no proof file available', [
    //                 'ride_id' => $rideId,
    //             ]);
    //         }

    //         // ── 8. WhatsApp numbers ──────────────────────────────────────────────
    //         $customerWhatsApp = !empty($client->alternate_number)
    //             ? $client->alternate_number
    //             : $client->contact_number;

    //         $vendorPhone = null;
    //         if ($firstVendorPayment && $firstVendorPayment->vendor) {
    //             $v           = $firstVendorPayment->vendor;
    //             $vendorPhone = $v->whatsapp_number ?? $v->alternate_number ?? $v->contact_number ?? $v->phone ?? null;
    //         }

    //         // ── 9. Email data arrays ─────────────────────────────────────────────
    //         $customerEmailData = [
    //             'client_name'   => $client->name          ?? 'Customer',
    //             'service'       => $serviceName,
    //             'service_date'  => $pickup_date,
    //             'refund_amount' => floatval($refund->refund_amount ?? 0),
    //             'refund_type'   => $refund->refund_type   ?? 'N/A',
    //             'refund_date'   => $refundDateFormatted,
    //             'refund_reason' => $refund->refund_reason ?? '',
    //         ];

    //         $vendorData = [
    //             'vendor_name'    => $vendorName,
    //             'client_name'    => $client->name          ?? 'Customer',
    //             'service'        => $serviceName,
    //             'service_date'   => $pickup_date,
    //             'paid_amount'    => $paidAmount,
    //             'pending_amount' => $pendingAmount,
    //             'balance_amount' => $balanceAmount,
    //             'payment_date'   => $refundDateFormatted,
    //             'payment_mode'   => $refund->refund_type   ?? 'N/A',
    //             'refund_reason'  => $refund->refund_reason ?? '',
    //         ];

    //         // ── 10. Notification masters ─────────────────────────────────────────
    //         $notificationMasters = \App\Models\NotificationMaster::where('status', 1)->get();
    //         Log::info('sendRefundEmail: notification masters', ['count' => $notificationMasters->count()]);

    //         $errors = [];

    //         // ════════════════════════════════════════════════════════════════════
    //         // CUSTOMER — Email
    //         // ════════════════════════════════════════════════════════════════════
    //         $customerEmail = $client->email ?? null;
    //         if (!empty($customerEmail) && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    //             try {
    //                 Mail::to($customerEmail)->send(new RefundMail(
    //                     'emails.refund-customer',
    //                     'Refund Processed for Your Booking – ' . $serviceName,
    //                     $customerEmailData,
    //                     $proofFilePath
    //                 ));
    //                 Log::info('sendRefundEmail: customer email sent', ['to' => $customerEmail]);
    //             } catch (\Exception $e) {
    //                 Log::error('sendRefundEmail: customer email failed', ['error' => $e->getMessage()]);
    //                 $errors[] = 'Customer email failed: ' . $e->getMessage();
    //             }
    //         } else {
    //             Log::warning('sendRefundEmail: customer email missing/invalid', ['email' => $customerEmail]);
    //             $errors[] = 'Customer email is missing or invalid.';
    //         }

    //         // ════════════════════════════════════════════════════════════════════
    //         // CUSTOMER — WhatsApp (refund_cust_notify_v2 — 5 variables)
    //         // ════════════════════════════════════════════════════════════════════
    //         if ($skipWhatsApp) {
    //             Log::warning('sendRefundEmail: skipping customer WhatsApp — no proof file');
    //         } elseif (empty($customerWhatsApp)) {
    //             Log::warning('sendRefundEmail: customer WhatsApp number missing');
    //         } else {
    //             Log::info('sendRefundEmail: sending customer WhatsApp', [
    //                 'template' => $customerWaTemplate,
    //                 'number'   => $customerWhatsApp,
    //                 'type'     => $waType,
    //                 'file'     => $waFileUrl,
    //                 'data'     => $customerWaData,
    //             ]);
    //             try {
    //                 $smc      = new SendMessageController();
    //                 $waResult = $smc->sendWhatsAppMessage($waType, $customerWaTemplate, $customerWaData, $customerWhatsApp, $waFileUrl);
    //                 Log::info('sendRefundEmail: customer WhatsApp response', [
    //                     'result'  => $waResult['result']  ?? null,
    //                     'message' => $waResult['message'] ?? null,
    //                     'id'      => $waResult['id']      ?? null,
    //                     'raw'     => $waResult,
    //                 ]);
    //                 if (isset($waResult['result']) && $waResult['result'] == false) {
    //                     Log::error('sendRefundEmail: customer WhatsApp FAILED', [
    //                         'reason'  => $waResult['message'] ?? 'unknown',
    //                         'number'  => $customerWhatsApp,
    //                         'file'    => $waFileUrl,
    //                         'payload' => $customerWaData,
    //                     ]);
    //                 }
    //             } catch (\Exception $e) {
    //                 Log::error('sendRefundEmail: customer WhatsApp exception', ['error' => $e->getMessage()]);
    //             }
    //         }

    //         // ════════════════════════════════════════════════════════════════════
    //         // VENDOR — Email
    //         // ════════════════════════════════════════════════════════════════════
    //         $vendorEmail = $firstVendorPayment && $firstVendorPayment->vendor
    //             ? ($firstVendorPayment->vendor->email ?? null)
    //             : null;

    //         if (!empty($vendorEmail) && filter_var($vendorEmail, FILTER_VALIDATE_EMAIL)) {
    //             try {
    //                 Mail::to($vendorEmail)->send(new RefundMail(
    //                     'emails.refund-vendor',
    //                     'Payment Processed – ' . ($client->name ?? 'Customer') . ' – ' . $serviceName,
    //                     $vendorData,
    //                     $proofFilePath
    //                 ));
    //                 Log::info('sendRefundEmail: vendor email sent', ['to' => $vendorEmail]);
    //             } catch (\Exception $e) {
    //                 Log::error('sendRefundEmail: vendor email failed', ['error' => $e->getMessage()]);
    //             }
    //         } else {
    //             Log::warning('sendRefundEmail: vendor email missing/invalid', [
    //                 'vendor' => $vendorName,
    //                 'email'  => $vendorEmail,
    //             ]);
    //         }

    //         // ════════════════════════════════════════════════════════════════════
    //         // VENDOR — WhatsApp (refund_vendor_notify_v2 — 9 variables)
    //         // ════════════════════════════════════════════════════════════════════
    //         if ($skipWhatsApp) {
    //             Log::warning('sendRefundEmail: skipping vendor WhatsApp — no proof file');
    //         } elseif (empty($vendorPhone)) {
    //             Log::warning('sendRefundEmail: vendor WhatsApp number missing', ['vendor' => $vendorName]);
    //         } else {
    //             Log::info('sendRefundEmail: sending vendor WhatsApp', [
    //                 'template' => $vendorWaTemplate,
    //                 'number'   => $vendorPhone,
    //                 'type'     => $waType,
    //                 'file'     => $waFileUrl,
    //                 'data'     => $vendorWaData,
    //             ]);
    //             try {
    //                 $smc            = new SendMessageController();
    //                 $vendorWaResult = $smc->sendWhatsAppMessage($waType, $vendorWaTemplate, $vendorWaData, $vendorPhone, $waFileUrl);
    //                 Log::info('sendRefundEmail: vendor WhatsApp response', [
    //                     'result'  => $vendorWaResult['result']  ?? null,
    //                     'message' => $vendorWaResult['message'] ?? null,
    //                     'id'      => $vendorWaResult['id']      ?? null,
    //                     'raw'     => $vendorWaResult,
    //                 ]);
    //                 if (isset($vendorWaResult['result']) && $vendorWaResult['result'] == false) {
    //                     Log::error('sendRefundEmail: vendor WhatsApp FAILED', [
    //                         'reason'  => $vendorWaResult['message'] ?? 'unknown',
    //                         'number'  => $vendorPhone,
    //                         'file'    => $waFileUrl,
    //                         'payload' => $vendorWaData,
    //                     ]);
    //                 }
    //             } catch (\Exception $e) {
    //                 Log::error('sendRefundEmail: vendor WhatsApp exception', ['error' => $e->getMessage()]);
    //             }
    //         }

    //         // ════════════════════════════════════════════════════════════════════
    //         // NOTIFICATION MASTER — Email + WhatsApp
    //         // ════════════════════════════════════════════════════════════════════
    //         foreach ($notificationMasters as $nm) {

    //             // NM uses customer template (5 variables)
    //             $nmWaData = $sanitize([
    //                 $client->name ?? 'Customer',   // {{1}}
    //                 $serviceName,                   // {{2}}
    //                 $pickup_date,                   // {{3}}
    //                 $refundAmountFormatted,         // {{4}}
    //                 $refundDateFormatted,           // {{5}}
    //             ]);

    //             // Email
    //             $nmEmail = $nm->email_id ?? null;
    //             if (!empty($nmEmail) && filter_var($nmEmail, FILTER_VALIDATE_EMAIL)) {
    //                 try {
    //                     Mail::to($nmEmail)->send(new RefundMail(
    //                         'emails.refund-customer',
    //                         'Refund Notification – ' . $serviceName . ' – ' . ($client->name ?? 'Customer'),
    //                         $customerEmailData,
    //                         $proofFilePath
    //                     ));
    //                     Log::info('sendRefundEmail: NM email sent', ['to' => $nmEmail, 'nm_id' => $nm->id]);
    //                 } catch (\Exception $e) {
    //                     Log::error('sendRefundEmail: NM email failed', [
    //                         'nm_id' => $nm->id,
    //                         'error' => $e->getMessage(),
    //                     ]);
    //                 }
    //             }

    //             // WhatsApp
    //             $nmPhone = !empty($nm->contact_country_code)
    //                 ? $nm->contact_country_code . '-' . $nm->mobile_number
    //                 : $nm->mobile_number;

    //             if ($skipWhatsApp) {
    //                 Log::warning('sendRefundEmail: skipping NM WhatsApp — no proof file', ['nm_id' => $nm->id]);
    //             } elseif (empty($nm->mobile_number)) {
    //                 Log::warning('sendRefundEmail: NM phone missing', ['nm_id' => $nm->id]);
    //             } else {
    //                 Log::info('sendRefundEmail: sending NM WhatsApp', [
    //                     'nm_id'    => $nm->id,
    //                     'template' => $customerWaTemplate,
    //                     'number'   => $nmPhone,
    //                     'file'     => $waFileUrl,
    //                     'data'     => $nmWaData,
    //                 ]);
    //                 try {
    //                     $smc        = new SendMessageController();
    //                     $nmWaResult = $smc->sendWhatsAppMessage($waType, $customerWaTemplate, $nmWaData, $nmPhone, $waFileUrl);
    //                     Log::info('sendRefundEmail: NM WhatsApp response', [
    //                         'nm_id'   => $nm->id,
    //                         'result'  => $nmWaResult['result']  ?? null,
    //                         'message' => $nmWaResult['message'] ?? null,
    //                         'raw'     => $nmWaResult,
    //                     ]);
    //                     if (isset($nmWaResult['result']) && $nmWaResult['result'] == false) {
    //                         Log::error('sendRefundEmail: NM WhatsApp FAILED', [
    //                             'nm_id'   => $nm->id,
    //                             'reason'  => $nmWaResult['message'] ?? 'unknown',
    //                             'number'  => $nmPhone,
    //                             'payload' => $nmWaData,
    //                         ]);
    //                     }
    //                 } catch (\Exception $e) {
    //                     Log::error('sendRefundEmail: NM WhatsApp exception', [
    //                         'nm_id' => $nm->id,
    //                         'error' => $e->getMessage(),
    //                     ]);
    //                 }
    //             }
    //         }

    //         // ── 11. Return ────────────────────────────────────────────────────────
    //         if (!empty($errors)) {
    //             return response()->json(['success' => false, 'message' => implode(' | ', $errors)], 422);
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Refund notifications sent via email and WhatsApp.',
    //         ]);

    //     } catch (\Exception $e) {
    //         Log::error('sendRefundEmail: unexpected error', [
    //             'ride_id' => $rideId,
    //             'error'   => $e->getMessage(),
    //             'trace'   => $e->getTraceAsString(),
    //         ]);
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function sendRefundEmail(Request $request, $rideId)
    {
        try {
            // ── 1. Load ride ─────────────────────────────────────────────────────
            $ride = \App\Models\LeadRide::with([
                'enquiry.client',
                'enquiry.leadFollowups',
                'enquiry.leadVendorPayments.vendor',
                'enquiry.leadVendorPayments.paymentDetails.service',
                'serviceAddress',
            ])->findOrFail($rideId);

            $lead   = $ride->enquiry;
            $client = $lead->client;

            Log::info('sendRefundEmail: started', ['ride_id' => $rideId, 'lead_id' => $lead->id]);

            // ── 2. Get latest refund ─────────────────────────────────────────────
            $refund = \App\Models\LeadRefund::whereHas('leadFollowup', function ($q) use ($lead) {
                $q->where('lead_id', $lead->id);
            })
                ->latest()
                ->first();

            if (!$refund) {
                return response()->json(['success' => false, 'message' => 'No refund record found.'], 404);
            }

            Log::info('sendRefundEmail: refund found', [
                'refund_id' => $refund->id,
                'amount'    => $refund->refund_amount,
                'proof'     => $refund->refund_proof,
            ]);

            // ── 3. Build data fields ─────────────────────────────────────────────
            $firstVendorPayment = $lead->leadVendorPayments->first();
            $firstDetail        = $firstVendorPayment ? $firstVendorPayment->paymentDetails->first() : null;
            $serviceObj         = $firstDetail ? ($firstDetail->service ?? null) : null;
            $serviceName        = $serviceObj->service ?? ($serviceObj->service_name ?? 'N/A');

            $pickup_date = 'N/A';
            if ($ride && !empty($ride->from_date)) {
                try {
                    $pickup_date = Carbon::parse($ride->from_date)->format('jS F, Y');
                } catch (\Throwable $ex) {
                    $pickup_date = is_string($ride->from_date)
                        ? date('jS F, Y', strtotime($ride->from_date))
                        : 'N/A';
                }
            }

            $refundAmountFormatted = number_format($refund->refund_amount ?? 0, 2);
            $refundDateFormatted   = 'N/A';
            try {
                if (!empty($refund->refund_date)) {
                    $refundDateFormatted = Carbon::parse($refund->refund_date)->format('jS F, Y');
                }
            } catch (\Throwable $e) {
            }

            // ── 4. Sanitize helper ───────────────────────────────────────────────
            $sanitize = function ($arr) {
                return array_map(function ($v) {
                    if (is_null($v))                   return '';
                    if (is_array($v) || is_object($v)) return json_encode($v);
                    return (string) $v;
                }, $arr);
            };

            // ── 5. Resolve refund proof file & detect type ────────────────────────
            $waFileUrl     = null;
            $proofFilePath = null;
            $fileExtension = null;
            $isImageFile   = false;
            $isPdfFile     = false;

            if (!empty($refund->refund_proof)) {
                $relative      = ltrim($refund->refund_proof, '/');
                $fullPath      = storage_path('app/public/' . $relative);
                $fileExtension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

                // Determine file type
                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                $isImageFile      = in_array($fileExtension, $imageExtensions);
                $isPdfFile        = ($fileExtension === 'pdf');

                if (file_exists($fullPath)) {
                    $proofFilePath = $fullPath;
                    $waFileUrl     = rtrim(config('app.url'), '/') . '/storage/' . $relative;
                    Log::info('sendRefundEmail: proof file ready', [
                        'url'       => $waFileUrl,
                        'type'      => $isImageFile ? 'IMAGE' : ($isPdfFile ? 'PDF' : 'OTHER'),
                        'extension' => $fileExtension,
                    ]);
                } else {
                    Log::warning('sendRefundEmail: proof file NOT found on disk', ['path' => $fullPath]);
                }
            } else {
                Log::warning('sendRefundEmail: no refund proof uploaded', ['refund_id' => $refund->id]);
            }

            // ── 6. Select template based on file type ────────────────────────────
            // refund_cust_notify_v2_img for images
            // refund_cust_notify_v2 for PDFs (default)
            $customerWaTemplate = 'refund_cust_notify_v2';

            // ── 7. WhatsApp data — CUSTOMER (5 variables) ────────────────────────
            $customerWaData = $sanitize([
                $client->name ?? 'Customer',   // {{1}} Customer Name
                $serviceName,                   // {{2}} Service Name
                $pickup_date,                   // {{3}} Service Date
                $refundAmountFormatted,         // {{4}} Refund Amount
                $refundDateFormatted,           // {{5}} Refund Date
            ]);

            $waType       = 2; // Document header
            $skipWhatsApp = empty($waFileUrl);

            if ($skipWhatsApp) {
                Log::warning('sendRefundEmail: WhatsApp skipped — no proof file', ['ride_id' => $rideId]);
            } else {
                Log::info('sendRefundEmail: WhatsApp template selected', [
                    'template'  => $customerWaTemplate,
                    'file_type' => $isImageFile ? 'IMAGE' : ($isPdfFile ? 'PDF' : 'OTHER'),
                    'extension' => $fileExtension,
                ]);
            }

            // ── 7. Phone numbers ─────────────────────────────────────────────────
            $customerWhatsApp = !empty($client->alternate_number)
                ? $client->alternate_number
                : $client->contact_number;

            // ── 8. Blade email data — CUSTOMER ───────────────────────────────────
            $customerEmailData = [
                'client_name'   => $client->name          ?? 'Customer',
                'service'       => $serviceName,
                'service_date'  => $pickup_date,
                'refund_amount' => floatval($refund->refund_amount ?? 0),
                'refund_type'   => $refund->refund_type   ?? 'N/A',
                'refund_date'   => $refundDateFormatted,
                'refund_reason' => $refund->refund_reason ?? '',
            ];

            // ── 9. Notification masters ──────────────────────────────────────────
            $notificationMasters = \App\Models\NotificationMaster::where('status', 1)->get();
            Log::info('sendRefundEmail: notification masters', ['count' => $notificationMasters->count()]);

            $smc    = new SendMessageController();
            $errors = [];

            // ════════════════════════════════════════════════════════════════════
            // ROLE CHECK — Only Super Admin, Admin, Accounts can send notifications
            // Operations can save/see popup but notifications are NOT sent
            // ════════════════════════════════════════════════════════════════════
            $currentUser     = \Illuminate\Support\Facades\Auth::user();
            $currentUserType = $currentUser?->userType?->user_type;

            $canSendNotifications = $currentUserType && in_array($currentUserType, array_merge(
                [\App\Models\UserType::SUPER_ADMIN, \App\Models\UserType::ADMIN],
                \App\Models\UserType::ACCOUNTS_ROLES
            ));

            Log::info('sendRefundEmail: role check', [
                'user_id'               => $currentUser?->id,
                'user_type'             => $currentUserType,
                'can_send_notifications' => $canSendNotifications,
            ]);

            if ($canSendNotifications) {

                // ════════════════════════════════════════════════════════════════
                // CUSTOMER — Blade Email
                // ════════════════════════════════════════════════════════════════
                $customerEmail = $client->email ?? null;
                if (!empty($customerEmail) && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                    try {
                        Mail::to($customerEmail)->send(new \App\Mail\RefundMail(
                            'emails.refund-customer',
                            'Refund Processed for Your Booking – ' . $serviceName,
                            $customerEmailData,
                            $proofFilePath
                        ));
                        Log::info('sendRefundEmail: customer blade email sent', ['to' => $customerEmail]);
                    } catch (\Exception $e) {
                        Log::error('sendRefundEmail: customer email failed', ['error' => $e->getMessage()]);
                        $errors[] = 'Customer email failed: ' . $e->getMessage();
                    }
                } else {
                    Log::warning('sendRefundEmail: customer email missing/invalid', ['email' => $customerEmail]);
                    $errors[] = 'Customer email is missing or invalid.';
                }

                // MSG91 customer email — commented, use when auth key available
                // $smc->sendMsg91Email('refund_processed_customer', $customerEmail, $client->name ?? 'Customer', $customerMsg91Vars);

                // ════════════════════════════════════════════════════════════════
                // CUSTOMER — WhatsApp (dynamic template: image or PDF)
                // ════════════════════════════════════════════════════════════════
                if ($skipWhatsApp) {
                    Log::warning('sendRefundEmail: skipping customer WhatsApp — no proof file');
                } elseif (empty($customerWhatsApp)) {
                    Log::warning('sendRefundEmail: customer WhatsApp number missing');
                } else {
                    Log::info('sendRefundEmail: sending customer WhatsApp', [
                        'template'  => $customerWaTemplate,
                        'file_type' => $isImageFile ? 'IMAGE' : ($isPdfFile ? 'PDF' : 'OTHER'),
                        'number'    => $customerWhatsApp,
                        'file'      => $waFileUrl,
                        'data'      => $customerWaData,
                    ]);
                    try {
                        $waResult = $smc->sendWhatsCrmRefundMessage($customerWhatsApp, $customerWaData, $waFileUrl, $refund->refund_proof, $customerWaTemplate);
                        Log::info('sendRefundEmail: customer WhatsApp response', [
                            'template' => $customerWaTemplate,
                            'result'   => $waResult,
                        ]);
                        if (isset($waResult['success']) && $waResult['success'] == false) {
                            Log::error('sendRefundEmail: customer WhatsApp FAILED', [
                                'reason' => $waResult['message'] ?? 'unknown',
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('sendRefundEmail: customer WhatsApp exception', ['error' => $e->getMessage()]);
                    }
                }

                // ════════════════════════════════════════════════════════════════
                // NOTIFICATION MASTER — Blade Email + WhatsApp (customer template)
                // ════════════════════════════════════════════════════════════════
                foreach ($notificationMasters as $nm) {

                    $nmWaData = $sanitize([
                        $client->name ?? 'Customer',   // {{1}}
                        $serviceName,                   // {{2}}
                        $pickup_date,                   // {{3}}
                        $refundAmountFormatted,         // {{4}}
                        $refundDateFormatted,           // {{5}}
                    ]);

                    // Blade Email
                    $nmEmail = $nm->email_id ?? null;
                    if (!empty($nmEmail) && filter_var($nmEmail, FILTER_VALIDATE_EMAIL)) {
                        try {
                            Mail::to($nmEmail)->send(new \App\Mail\RefundMail(
                                'emails.refund-customer',
                                'Refund Notification – ' . $serviceName . ' – ' . ($client->name ?? 'Customer'),
                                $customerEmailData,
                                $proofFilePath
                            ));
                            Log::info('sendRefundEmail: NM blade email sent', ['to' => $nmEmail, 'nm_id' => $nm->id]);
                        } catch (\Exception $e) {
                            Log::error('sendRefundEmail: NM email failed', [
                                'nm_id' => $nm->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    // MSG91 NM email — commented, use when auth key available
                    // $smc->sendMsg91Email('refund_processed_customer', $nmEmail, $nm->name ?? 'Team', $customerMsg91Vars);

                    // WhatsApp
                    $nmPhone = !empty($nm->contact_country_code)
                        ? $nm->contact_country_code . '-' . $nm->mobile_number
                        : $nm->mobile_number;

                    if ($skipWhatsApp) {
                        Log::warning('sendRefundEmail: skipping NM WhatsApp', ['nm_id' => $nm->id]);
                    } elseif (empty($nm->mobile_number)) {
                        Log::warning('sendRefundEmail: NM phone missing', ['nm_id' => $nm->id]);
                    } else {
                        Log::info('sendRefundEmail: sending NM WhatsApp', [
                            'nm_id'     => $nm->id,
                            'template'  => $customerWaTemplate,
                            'file_type' => $isImageFile ? 'IMAGE' : ($isPdfFile ? 'PDF' : 'OTHER'),
                            'number'    => $nmPhone,
                        ]);
                        try {
                            $nmWaResult = $smc->sendWhatsCrmRefundMessage($nmPhone, $nmWaData, $waFileUrl, $refund->refund_proof, $customerWaTemplate);
                            Log::info('sendRefundEmail: NM WhatsApp response', [
                                'nm_id'     => $nm->id,
                                'template'  => $customerWaTemplate,
                                'result'    => $nmWaResult,
                            ]);
                            if (isset($nmWaResult['success']) && $nmWaResult['success'] == false) {
                                Log::error('sendRefundEmail: NM WhatsApp FAILED', [
                                    'nm_id'  => $nm->id,
                                    'reason' => $nmWaResult['message'] ?? 'unknown',
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::error('sendRefundEmail: NM WhatsApp exception', [
                                'nm_id' => $nm->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            } else {
                // Role not permitted to send notifications — log and skip silently
                Log::info('sendRefundEmail: notifications skipped — user role not permitted', [
                    'user_id'   => $currentUser?->id,
                    'user_type' => $currentUserType,
                ]);
            }

            // ── 10. Mark refund status=1 (notifications sent, pending in Refund Notes) ──
            // status=1 = processed/notified → shows in Refund Notes PENDING tab
            // status=2 = completed → set by markAsDone button in Refund Notes page
            if (empty($errors)) {
                try {
                    $refund->update(['status' => 1]);
                    Log::info('sendRefundEmail: refund status=1 set (notified, pending)', ['refund_id' => $refund->id]);
                } catch (\Exception $e) {
                    Log::warning('sendRefundEmail: could not set refund status=1', ['error' => $e->getMessage()]);
                }
            }

            if (!empty($errors)) {
                return response()->json(['success' => false, 'message' => implode(' | ', $errors)], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Refund notifications sent via email and WhatsApp.',
            ]);
        } catch (\Exception $e) {
            Log::error('sendRefundEmail: unexpected error', [
                'ride_id' => $rideId,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Save refund information from ride status page
     * This method creates or updates a refund with status = 2 (completed)
     * Only accounts can submit refunds from ride status
     */
    public function saveRefundFromRideStatus(Request $request, $rideId)
    {
        if (!auth()->user()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            Log::info('saveRefundFromRideStatus called', [
                'rideId' => $rideId,
                'followup_id_from_request' => $request->input('followup_id'),
                'user_id' => auth()->id(),
                'request_data' => $request->except(['refund_proof', '_token'])
            ]);

            // Check user role - allow Accounts, Admin and Operations to submit from ride status
            $currentRole = optional(auth()->user()->userType)->user_type;
            $isAdmin = in_array($currentRole, UserType::ADMIN_ROLES ?? []);
            $isAccounts = in_array($currentRole, UserType::ACCOUNTS_ROLES ?? []);
            $isOperations = in_array($currentRole, UserType::OPERATIONS_ROLES ?? []);

            if (!($isAccounts || $isAdmin || $isOperations)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: Only Accounts, Operations or Admin can submit refunds'
                ], 403);
            }

            // Base validation rules applicable to all submitters
            $rules = [
                'followup_id' => 'required|string',
                'original_amount' => 'required|numeric|min:0',
                'refund_amount' => 'required|numeric|min:0|lte:original_amount',
                'refund_reason' => 'nullable|string'
            ];

            $messages = [
                'followup_id.required' => 'Required refund information is missing.',
                'original_amount.required' => 'Original amount is required.',
                'refund_amount.required' => 'Refund amount is required.',
                'refund_amount.lte' => 'Refund amount cannot be greater than the original amount.'
            ];

            // Accounts must provide refund method, date and proof
            if ($isAccounts) {
                $rules['refund_type'] = 'required|string';
                $rules['refund_date'] = 'required|date';
                $rules['refund_proof'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:2048';

                $messages['refund_type.required'] = 'Please select a refund method.';
                $messages['refund_date.required'] = 'Please provide the refund date.';
                $messages['refund_proof.required'] = 'Please upload a refund proof document.';
                $messages['refund_proof.mimes'] = 'Refund proof must be a PDF or image (JPG, JPEG, PNG).';
                $messages['refund_proof.max'] = 'Refund proof must be smaller than 2 MB.';
            } else {
                // Admin and Operations are allowed to submit without proof/type/date, but if provided validate the file
                $rules['refund_type'] = 'nullable|string';
                $rules['refund_date'] = 'nullable|date';
                $rules['refund_proof'] = 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:2048';

                $messages['refund_proof.mimes'] = 'Refund proof must be a PDF or image (JPG, JPEG, PNG).';
                $messages['refund_proof.max'] = 'Refund proof must be smaller than 2 MB.';
            }

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                Log::warning('Refund validation failed from ride status', [
                    'errors' => $validator->errors()->toArray(),
                    'followup_id' => $request->input('followup_id')
                ]);
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()
                ], 422);
            }

            $followup = \App\Models\LeadFollowup::find($request->followup_id);
            if (!$followup) {
                Log::error('Followup not found for refund from ride status', [
                    'followup_id' => $request->input('followup_id'),
                    'rideId' => $rideId,
                    'available_followups' => \App\Models\LeadFollowup::where('id', 'like', '%' . substr($request->input('followup_id'), 0, 8) . '%')->pluck('id')->toArray()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Followup not found. The followup ID provided (' . $request->input('followup_id') . ') does not exist in the database.'
                ], 404);
            }

            // Check if followup is cancelled
            if ($followup->status != 2) {
                return response()->json(['success' => false, 'message' => 'Ride must be cancelled to create refund'], 400);
            }

            // Handle file upload
            $refundProofPath = null;
            if ($request->hasFile('refund_proof')) {
                try {
                    $file = $request->file('refund_proof');
                    $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $extension = $file->getClientOriginalExtension();
                    $cleanName = preg_replace('/[^A-Za-z0-9\-]/', '_', $originalName);
                    $cleanName = substr($cleanName, 0, 100);
                    $fileName = 'refund_' . time() . '_' . $cleanName . '.' . $extension;
                    $refundProofPath = $file->storeAs('refunds', $fileName, 'public');
                } catch (\Exception $e) {
                    Log::error('Error uploading refund proof from ride status', [
                        'error' => $e->getMessage(),
                        'followup_id' => $request->followup_id
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Error uploading refund proof: ' . $e->getMessage()
                    ], 500);
                }
            }

            // Check if refund already exists
            $existingRefund = \App\Models\LeadRefund::where('lead_followup_id', $request->followup_id)->first();

            $payload = [
                'original_amount' => $request->original_amount,
                'refund_amount' => $request->refund_amount,
                'refund_type' => $request->refund_type,
                'refund_date' => $request->refund_date,
                'refund_reason' => $request->refund_reason,
                'status' => 0, // status=0: saved, email/WA not sent yet. Status set to 1 by sendRefundEmail after notifications sent
            ];

            if ($refundProofPath) {
                $payload['refund_proof'] = $refundProofPath;
            }

            if ($existingRefund) {
                $existingRefund->update($payload + ['updated_at' => now()]);

                Log::info('Refund updated from ride status', ['refund_id' => $existingRefund->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Refund updated successfully',
                    'refund_id' => $existingRefund->id
                ]);
            } else {
                $createData = [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'lead_followup_id' => $request->followup_id,
                ] + $payload;

                $refund = \App\Models\LeadRefund::create($createData);

                Log::info('Refund created from ride status', ['refund_id' => $refund->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Refund created successfully',
                    'refund_id' => $refund->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error processing refund from ride status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error processing refund: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get status text based on status code
     */
    private function getStatusText($status)
    {
        $labels = config('ride_statuses.labels', []);
        $intStatus = (int) $status;
        return $labels[$intStatus] ?? 'Unknown';
    }

    /**
     * Calculate pending amount for a lead using the same logic as Payment Review
     */
    private function calculatePendingAmount($lead)
    {
        // Match PaymentReviewController behavior exactly:
        // - total amount is taken from the latest followup for the lead
        // - received amount is sum of approved payments (payment_status = 1) from PaymentAuditTrail

        // Get all followup ids for this lead
        $followupIds = $lead->leadFollowups->pluck('id')->toArray();

        if (empty($followupIds)) {
            return 0;
        }

        // Latest followup (to get total_amount)
        $latestFollowup = $lead->leadFollowups()->orderBy('created_at', 'desc')->first();
        if (!$latestFollowup) {
            return 0;
        }

        $totalAmount = (float) ($latestFollowup->total_amount ?? 0);

        // Calculate received amount only from approved payments (audit trail status = 1)
        $approvedPayments = PaymentAuditTrail::whereIn('lead_followup_id', $followupIds)
            ->where('payment_status', 1) // Only approved payments
            ->get();

        $totalReceived = $approvedPayments->sum('paid_amount');

        return $totalAmount - $totalReceived;
    }
}
