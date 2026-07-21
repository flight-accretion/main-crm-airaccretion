<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use function App\Helpers\getRepresentativeIds;
use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;
use App\Models\Service;
use App\Models\Lead;
use App\Models\User;
use App\Models\ExtraService;
use App\Models\Product;
use App\Models\Target;
use function App\Helpers\extractPhoneWithoutCountryCode;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SalesReportExport;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Helper method to get users in hierarchy based on user types
     */
    private function getUsersInHierarchy()
    {
        try {
            $currentUser = auth()->user();

            // If user doesn't have a user type, return all users as fallback
            if (!$currentUser || !$currentUser->userType) {
                return User::all();
            }

            $currentUserType = $currentUser->userType->user_type ?? '';

            // If current user is in accounts role, admin, or super admin, return all sales and managers
            if (
                in_array($currentUserType, \App\Models\UserType::ACCOUNTS_ROLES) ||
                in_array($currentUserType, \App\Models\UserType::ADMIN_ROLES)
            ) {
                // Get all users with sales or manager roles
                return User::where('status', 1)
                    ->whereHas('userType', function ($q) {
                        $q->whereIn('user_type', array_merge(
                            \App\Models\UserType::SALES_ROLES,
                            \App\Models\UserType::ADMIN_ROLES
                        ));
                    })
                    ->orderBy('name')
                    ->get();
            }

            // Get all descendant user type IDs
            $descendantUserTypeIds = $currentUser->userType->getDescendantIds();

            // Build the query for users in hierarchy
            $query = User::where('status', 1); // Only active users

            // If the current user has descendants, include current user + descendants
            // If no descendants, only include the current user
            if (!empty($descendantUserTypeIds)) {
                $query->where(function ($q) use ($currentUser, $descendantUserTypeIds) {
                    $q->where('id', $currentUser->id) // Include current user
                        ->orWhereIn('user_type_id', $descendantUserTypeIds); // Include descendants
                });
            } else {
                // No descendants, only return current user
                $query->where('id', $currentUser->id);
            }

            $usersInHierarchy = $query->get();

            // If no users found in hierarchy, return all users as fallback
            if ($usersInHierarchy->isEmpty()) {
                return User::all();
            }

            return $usersInHierarchy;
        } catch (\Exception $e) {
            // Log the error and return all users as fallback
            Log::error('Error getting users in hierarchy: ' . $e->getMessage());
            return User::all();
        }
    }

    public function index(Request $request)
    {

        // Ensure user is authenticated
        if (!auth()->check()) {
            return redirect()->route('login');
        }
        $currentUser = auth()->user();

        $representatives = getRepresentativeIds($currentUser);

        // try {
        // Get query parameters for filtering
        $serviceDate = $request->input('service_date');
        $serviceName = $request->input('service_name');
        $status = $request->input('status');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $representativeUserId = $request->input('representative_user_id');
        //
        // Base query for payment-related followups
        $query = LeadFollowup::with([
            'enquiry.client.country',
            'enquiry.client.city',
            'enquiry.rideSegments',
            'enquiry.representative'
        ])
            //->whereNotNull('received_amount')
            //->where('received_amount', '>', 0)
            ->whereIn('status', [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]);

        if ($request->filled('from_date')) {
            $fromDate = Carbon::parse($request->from_date)->startOfDay();
            $query->whereHas('enquiry.rideSegments', function ($q) use ($fromDate) {
                $q->where('from_date', '>=', $fromDate);
            });
        }
        if ($request->filled('to_date')) {
            $toDate = Carbon::parse($request->to_date)->endOfDay();
            $query->whereHas('enquiry.rideSegments', function ($q) use ($toDate) {
                $q->where('to_date', '<=', $toDate);
            });
        }

        if ($request->filled('from_create_date')) {
            $fromDate = Carbon::parse($request->from_create_date)->startOfDay();
            $query->whereHas('enquiry', function ($q) use ($fromDate) {
                $q->where('created_at', '>=', $fromDate);
            });
        }
        if ($request->filled('to_create_date')) {
            $toDate = Carbon::parse($request->to_create_date)->endOfDay();
            $query->whereHas('enquiry', function ($q) use ($toDate) {
                $q->where('created_at', '<=', $toDate);
            });
        }

        // Apply service name filter
        if ($serviceName) {
            $query->where(function ($q) use ($serviceName) {
                // Handle both array and JSON string formats
                $q->where('service_ids', 'like', '%' . $serviceName . '%')
                    ->orWhere('service_ids', 'like', '%"' . $serviceName . '"%');
            });
        }

        // NOTE: Status filter is intentionally NOT applied at DB level.
        // Applying it at DB level would match leads that had this status at ANY point,
        // not just leads whose LATEST followup has this status.
        // The status filter is applied after grouping below (matching KPI report logic).

        if ($request->filled('name')) {
            $query->whereHas('enquiry.client', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->name . '%');
            });
        }

        if ($request->filled('email')) {
            $query->whereHas('enquiry.client', function ($q) use ($request) {
                $q->where('email', 'like', '%' . $request->email . '%');
            });
        }

        if ($request->filled('phone')) {
            $query->whereHas('enquiry.client', function ($q) use ($request) {
                $q->where('contact_number', 'like', '%' . $request->phone . '%');
            });
        }
        if (isset($representativeUserId) && !empty($representativeUserId)) {
            // representative_user_id lives on the leads (enquiry) table, not on the users table.
            $query->whereHas('enquiry', function ($q) use ($representativeUserId) {
                $q->where('representative_user_id', $representativeUserId);
            });
        }
        if ($request->filled('product_id')) {
            // product_ids lives on the leads (enquiry) table — same as KPI/dashboard logic.
            $query->whereHas('enquiry', function ($q) use ($request) {
                $q->where('product_ids', 'like', '%' . $request->product_id . '%');
            });
        }




        $allPayments = $query->orderBy('created_at', 'desc')->get();
        $statusArray = [0 => 'Initiated', 1 => 'Active', 2 => 'Cancelled', 3 => 'Full Payment Received', 4 => 'Partial Payment Received', 5 => 'Confirmed', 6 => 'Pending', 7 => 'Rescheduled', 8 => 'Approved', 9 => 'Rejected'];
        // Group by lead_id and process
        $payments = $allPayments
            ->groupBy('lead_id')
            ->map(function ($group) {
                // Get the latest followup entry
                $latest = $group->sortByDesc('created_at')->first();
                $client = $latest->enquiry->client ?? null;
                $ride = $latest->enquiry->rideSegments->first() ?? null;
                $totalAmount = (float) $latest->total_amount;

                $approvedPayments = PaymentAuditTrail::whereIn('lead_followup_id', $group->pluck('id'))
                    ->where('payment_status', 1) // Only approved payments
                    ->get();

                $totalReceived = $approvedPayments->sum('paid_amount');

                // Determine payment status based on approved amount
                $paymentStatus = 'Unpaid';
                if ($totalReceived >= $totalAmount && $totalAmount > 0) {
                    $paymentStatus = 'Full Paid';
                } elseif ($totalReceived > 0) {
                    $paymentStatus = 'Partial Paid';
                }

                // Get latest audit trail
                $latestAudit = PaymentAuditTrail::where('lead_followup_id', $latest->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                $auditStatus = $latestAudit ? $latestAudit->payment_status : null;
                if ($totalAmount > 0 && $totalReceived >= $totalAmount) {
                    $auditStatus = 1; // approved
                }
                return (object) [
                    'followup_id' => $latest->id,
                    'lead_id' => $latest->lead_id,
                    'first_name' => $client->name ?? '',
                    'phone_number' => $client->contact_number ?? '',
                    'email' => $client->email ?? '',
                    'whatsapp_number' => $client->alternate_number ?? '',
                    'address' => $client->address ?? '',
                    'country_name' => $client->country->name ?? 'N/A',
                    'city_name' => $client->city->name ?? 'N/A',
                    'from_date' => $ride->from_date ?? null,
                    'to_date' => $ride->to_date ?? null,
                    'from_place' => $ride->from_place ?? '',
                    'to_place' => $ride->to_place ?? '',
                    'total_amount' => $totalAmount,
                    'received_amount' => $totalReceived, // Only approved payments
                    'balance' => $totalAmount - $totalReceived,
                    'status' => $latest->status ?? 'pending',
                    'created_at' => $latest->created_at,
                    'payment_status' => $paymentStatus,
                    'service_ids' => $latest->service_ids,
                    'audit_status' => $auditStatus,
                    'next_followup_date' => $latest->next_followup_date,
                ];
            })
            ->values(); // Reset keys for view

        // Apply status filter POST-grouping: only keep leads whose LATEST followup
        // has the requested status. This matches the KPI report logic exactly.
        if ($request->has('status') && $request->status != '') {
            $filterStatus = intval($request->status);
            $payments = $payments->filter(function ($payment) use ($filterStatus) {
                return $payment->status === $filterStatus;
            })->values();
        }

        // Get services for filter dropdown (same as refund notes)
        $services = Service::select('id', 'service')->get();
        $products = Product::select('id', 'product')->get();

        // Get staff based on logged-in user hierarchy
        $staff = $this->getUsersInHierarchy();
        if (!$staff) {
            $authUser = auth()->user();
            if ($authUser) {
                $staff = $authUser;
            } else {
                $staff = null;
            }
        }
        return view('admin.report.admin_report', compact('payments', 'services', 'products', 'serviceDate', 'serviceName', 'status', 'fromDate', 'toDate', 'representatives', 'staff', 'statusArray'));
    }

    /**
     * Sales Report - Display comprehensive sales data
     */
    //    public function salesReport(Request $request)
    // {
    //     // Ensure user is authenticated
    //     if (!auth()->check()) {
    //         return redirect()->route('login');
    //     }

    //     $currentUser = auth()->user();
    //     $representatives = getRepresentativeIds($currentUser);

    //     // Status array
    //     $statusArray = [ 
    //         // 1 => 'Active', 
    //         2 => 'Cancelled', 
    //         5 => 'Confirmed', 
    //         7 => 'Rescheduled', 
    //         // 8 => 'Approved', 
    //     ];

    //     // 1. Check if any filter is actually applied
    //     $hasFilters = 
    //           $request->filled('month') ||
    //           $request->filled('year') ||
    //           $request->filled('service_name') ||
    //           ($request->has('status') && $request->status != '') ||
    //           $request->filled('name') ||
    //           $request->filled('email') ||
    //           $request->filled('phone') ||
    //           $request->filled('representative_user_id') ||
    //           $request->filled('manager_user_id') ||
    //           $request->filled('product_id');

    //     $salesData = collect();

    //     if ($hasFilters) {
    //         // Base query for sales data
    //         $query = LeadFollowup::with([
    //             'enquiry.client.country',
    //             'enquiry.client.city',
    //             'enquiry.rideSegments',
    //             'enquiry.representative.userType',
    //             'followedBy.userType'
    //         ])->whereIn('status', [ 2,5,7]);

    //         // Apply filters
    //         if ($request->filled('month')) {
    //             $query->whereMonth('created_at', $request->month);
    //         }
    //         if ($request->filled('year')) {
    //             $query->whereYear('created_at', $request->year);
    //         }

    //         // Sales Manager Filter
    //         if ($request->filled('manager_user_id')) {
    //             $managerUserId = $request->manager_user_id;
    //             // Get all sales executives assigned to this manager
    //             $assignedExecutives = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($managerUserId);
    //             $assignedExecutiveIds = $assignedExecutives->pluck('id')->toArray();
    //             // Include the manager themselves in the filter
    //             $assignedExecutiveIds[] = $managerUserId;

    //             $query->whereHas('enquiry', function ($q) use ($assignedExecutiveIds) {
    //                 $q->whereIn('representative_user_id', $assignedExecutiveIds);
    //             });
    //         }

    //         // Sales Person Filter (if both manager and sales person selected, show only that sales person)
    //         if ($request->filled('representative_user_id')) {
    //             $representativeUserId = $request->representative_user_id;
    //             $query->whereHas('enquiry', function ($q) use ($representativeUserId) {
    //                 $q->where('representative_user_id', $representativeUserId);
    //             });
    //         }

    //         if ($request->filled('service_name')) {
    //             $serviceName = $request->service_name;
    //             $query->where(function ($q) use ($serviceName) {
    //                 $q->where('service_ids', 'like', '%' . $serviceName . '%')
    //                     ->orWhere('service_ids', 'like', '%"' . $serviceName . '"%');
    //             });
    //         }

    //         if ($request->has('status') && $request->status != '') {
    //             $query->where('status', intval($request->status));
    //         }

    //         if ($request->filled('name')) {
    //             $query->whereHas('enquiry.client', function ($q) use ($request) {
    //                 $q->where('name', 'like', '%' . $request->name . '%');
    //             });
    //         }

    //         if ($request->filled('email')) {
    //             $query->whereHas('enquiry.client', function ($q) use ($request) {
    //                 $q->where('email', 'like', '%' . $request->email . '%');
    //             });
    //         }

    //         if ($request->filled('phone')) {
    //             $query->whereHas('enquiry.client', function ($q) use ($request) {
    //                 $q->where('contact_number', 'like', '%' . $request->phone . '%');
    //             });  
    //         }

    //         if ($request->filled('product_id')) {
    //             $query->whereHas('enquiry', function ($q) use ($request) {
    //                 $q->where('product_ids', 'like', '%' . $request->product_id . '%');
    //             });  
    //         }

    //         $allSalesData = $query->orderBy('created_at', 'desc')->get();

    //         // Get all services and products for reference
    //         $allServices = Service::all()->keyBy('id');
    //         $allExtraServices = ExtraService::all()->keyBy('id');
    //         $allProducts = Product::all()->keyBy('id');

    //         // Process sales data (rest of the code remains the same)
    //         $salesData = $allSalesData->groupBy('lead_id')->map(function ($group) use ($allServices, $allExtraServices, $allProducts, $statusArray) {
    //             // ... existing mapping logic ...
    //             $latest = $group->sortByDesc('created_at')->first();
    //             $client = $latest->enquiry->client ?? null;
    //             $ride = $latest->enquiry->rideSegments->first() ?? null;
    //             $totalAmount = (float) $latest->total_amount;

    //             $approvedPayments = PaymentAuditTrail::whereIn('lead_followup_id', $group->pluck('id'))
    //                 ->where('payment_status', 1)
    //                 ->get();

    //             $totalReceived = $approvedPayments->sum('paid_amount');
    //             $pendingAmount = $totalAmount - $totalReceived;

    //             $lastPayment = $approvedPayments->sortByDesc('created_at')->first();
    //             $paidDate = $lastPayment ? $lastPayment->created_at->format('Y-m-d') : null;

    //             $salesPerson = $latest->enquiry->representative ?? null;
    //             $salesPersonName = $salesPerson ? $salesPerson->name : 'N/A';

    //             $managerName = 'N/A';
    //             if ($salesPerson && $salesPerson->userType && $salesPerson->userType->parent_id) {
    //                 $managerUserType = $salesPerson->userType->parent;
    //                 if ($managerUserType) {
    //                     $manager = User::where('user_type_id', $managerUserType->id)->first();
    //                     $managerName = $manager ? $manager->name : 'N/A';
    //                 }
    //             }

    //             $productNames = [];
    //             if (!empty($latest->enquiry->product_ids)) {
    //                 $productIds = is_array($latest->enquiry->product_ids) 
    //                     ? $latest->enquiry->product_ids 
    //                     : json_decode($latest->enquiry->product_ids, true);
    //                 if (is_array($productIds)) {
    //                     foreach ($productIds as $pid) {
    //                         if (isset($allProducts[$pid])) {
    //                             $productNames[] = $allProducts[$pid]->product;
    //                         }
    //                     }
    //                 }
    //             }

    //             $serviceNames = [];
    //             if (!empty($latest->service_ids)) {
    //                 $serviceIds = is_array($latest->service_ids) 
    //                     ? $latest->service_ids 
    //                     : json_decode($latest->service_ids, true);
    //                 if (is_array($serviceIds)) {
    //                     foreach ($serviceIds as $sid) {
    //                         if (isset($allServices[$sid])) {
    //                             $serviceNames[] = $allServices[$sid]->service;
    //                         }
    //                     }
    //                 }
    //             }

    //             $extraServiceNames = [];
    //             if (!empty($latest->extra_service_ids)) {
    //                 $extraServiceIds = is_array($latest->extra_service_ids) 
    //                     ? $latest->extra_service_ids 
    //                     : json_decode($latest->extra_service_ids, true);
    //                 if (is_array($extraServiceIds)) {
    //                     foreach ($extraServiceIds as $esid) {
    //                         if (isset($allExtraServices[$esid])) {
    //                             $extraServiceNames[] = $allExtraServices[$esid]->extra_service;
    //                         }
    //                     }
    //                 }
    //             }

    //             return (object) [
    //                 'followup_id' => $latest->id,
    //                 'lead_id' => $latest->lead_id,
    //                 'client_name' => $client->name ?? 'N/A',
    //                 'email' => $client->email ?? 'N/A',
    //                 'contact' => $client->contact_number ?? 'N/A',
    //                 'received_amount' => $totalReceived,
    //                 'pending_amount' => $pendingAmount,
    //                 'total_amount' => $totalAmount,
    //                 'sales_person_name' => $salesPersonName,
    //                 'product_name' => implode(', ', $productNames) ?: 'N/A',
    //                 'service' => implode(', ', $serviceNames) ?: 'N/A',
    //                 'extra_service' => implode(', ', $extraServiceNames) ?: 'N/A',
    //                 'paid_date' => $paidDate,
    //                 'ride_status' => $statusArray[$latest->status] ?? 'Unknown',
    //                 'booking_date' => $latest->created_at ? $latest->created_at->format('Y-m-d') : 'N/A',
    //                 'service_date' => $ride && $ride->from_date ? $ride->from_date : 'N/A',
    //                 'manager_name' => $managerName,
    //                 'status' => $latest->status,
    //                 'created_at' => $latest->created_at,
    //             ];
    //         })->values();
    //     }

    //     // Get services and products for filter dropdown
    //     $services = Service::select('id', 'service')->get();
    //     $products = Product::select('id', 'product')->get();

    //     // Get staff based on logged-in user hierarchy
    //     $staff = $this->getUsersInHierarchy();
    //     if (!$staff) {
    //         $authUser = auth()->user();
    //         $staff = $authUser ? collect([$authUser]) : collect();
    //     }

    //     // Build manager list: users whose user_type is a parent of other user types
    //     $parentTypeIds = \App\Models\UserType::whereNotNull('parent_id')->pluck('parent_id')->unique();
    //     $managers = User::whereIn('user_type_id', $parentTypeIds)->where('status', 1)->orderBy('name')->get();

    //     return view('admin.report.sales_report', compact(
    //         'salesData', 
    //         'services', 
    //         'products',
    //         'staff',
    //         'managers',
    //         'statusArray',
    //         'representatives',
    //         'hasFilters'
    //     ));
    // }

    public function salesReport(Request $request)
    {
        // Ensure user is authenticated
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $currentUser = auth()->user();
        $representatives = getRepresentativeIds($currentUser);

        // Status array - ONLY show these statuses in sales report
        $statusArray = [
            2 => 'Cancelled',
            5 => 'completed',
            7 => 'Rescheduled',
            8 => 'Approved'
        ];

        // Check if any filter is actually applied
        $hasFilters =
            $request->filled('month') ||
            $request->filled('year') ||
            $request->filled('service_name') ||
            ($request->has('status') && $request->status != '') ||
            $request->filled('name') ||
            $request->filled('email') ||
            $request->filled('phone') ||
            $request->filled('representative_user_id') ||
            $request->filled('manager_user_id') ||
            $request->filled('product_id');

        $salesData = collect();

        if ($hasFilters) {
            // Base query for sales data - ONLY show Cancelled, Confirmed, Rescheduled
            $query = LeadFollowup::with([
                'enquiry.client.country',
                'enquiry.client.city',
                'enquiry.rideSegments',
                'enquiry.representative.userType',
                'followedBy.userType'
            ])->whereIn('status', [2, 5, 7, 8]); // 2=Cancelled, 5=Confirmed, 7=Rescheduled, 8=Approved

            // Apply month/year filter based on PAID DATE (from PaymentAuditTrail)
            // Only show leads that received approved payment in the selected month/year
            // if ($request->filled('month') || $request->filled('year')) {
            //     $paidDateQuery = \App\Models\PaymentAuditTrail::where('payment_status', 1);
            //     if ($request->filled('month')) {
            //         $paidDateQuery->whereMonth('paid_date', $request->month);
            //     }
            //     if ($request->filled('year')) {
            //         $paidDateQuery->whereYear('paid_date', $request->year);
            //     }
            //     $paidFollowupIds = $paidDateQuery->pluck('lead_followup_id')->unique();
            //     $paidLeadIds = \App\Models\LeadFollowup::whereIn('id', $paidFollowupIds)->pluck('lead_id')->unique();
            //     $query->whereIn('lead_id', $paidLeadIds);
            // }

            if ($request->filled('month') || $request->filled('year')) {
                // Get all approved payments in the selected month/year
                $paidDateQuery = \App\Models\PaymentAuditTrail::where('payment_status', 1);
                if ($request->filled('month')) {
                    $paidDateQuery->whereMonth('paid_date', $request->month);
                }
                if ($request->filled('year')) {
                    $paidDateQuery->whereYear('paid_date', $request->year);
                }

                $paidFollowupIds = $paidDateQuery->pluck('lead_followup_id')->unique();
                $candidateLeadIds = \App\Models\LeadFollowup::whereIn('id', $paidFollowupIds)
                    ->pluck('lead_id')->unique();

                // Only keep leads whose FIRST EVER approved payment falls in the selected month/year
                // This prevents partial payment leads from appearing in multiple months
                $allFollowupIdsByLead = \App\Models\LeadFollowup::whereIn('lead_id', $candidateLeadIds)
                    ->get(['id', 'lead_id'])
                    ->groupBy('lead_id')
                    ->map(fn($group) => $group->pluck('id'));

                $allFollowupIdsFlat = $allFollowupIdsByLead->flatten()->unique();

                // Get the FIRST approved payment per lead (single query, no loop)
                $firstPaymentPerLead = \App\Models\PaymentAuditTrail::whereIn('lead_followup_id', $allFollowupIdsFlat)
                    ->where('payment_status', 1)
                    ->orderBy('paid_date')
                    ->get()
                    ->groupBy(function ($payment) use ($allFollowupIdsByLead) {
                        foreach ($allFollowupIdsByLead as $leadId => $followupIds) {
                            if ($followupIds->contains($payment->lead_followup_id)) {
                                return $leadId;
                            }
                        }
                        return null;
                    })
                    ->map(fn($payments) => $payments->sortBy('paid_date')->first());

                // Filter: only leads whose first payment is in the selected month/year
                $filterMonth = $request->filled('month') ? (int) $request->month : null;
                $filterYear  = $request->filled('year')  ? (int) $request->year  : null;

                $paidLeadIds = $firstPaymentPerLead->filter(function ($firstPayment) use ($filterMonth, $filterYear) {
                    if (!$firstPayment || empty($firstPayment->paid_date)) return false;
                    $pd = \Carbon\Carbon::parse($firstPayment->paid_date);
                    if ($filterMonth && $pd->month !== $filterMonth) return false;
                    if ($filterYear  && $pd->year  !== $filterYear)  return false;
                    return true;
                })->keys();

                $query->whereIn('lead_id', $paidLeadIds);
            }

            // Sales Manager Filter
            if ($request->filled('manager_user_id')) {
                $managerUserId = $request->manager_user_id;
                $assignedExecutives = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($managerUserId);
                $assignedExecutiveIds = $assignedExecutives->pluck('id')->toArray();
                $assignedExecutiveIds[] = $managerUserId;

                $query->whereHas('enquiry', function ($q) use ($assignedExecutiveIds) {
                    $q->whereIn('representative_user_id', $assignedExecutiveIds);
                });
            }

            // Sales Person Filter
            if ($request->filled('representative_user_id')) {
                $representativeUserId = $request->representative_user_id;
                $query->whereHas('enquiry', function ($q) use ($representativeUserId) {
                    $q->where('representative_user_id', $representativeUserId);
                });
            }

            if ($request->filled('service_name')) {
                $serviceName = $request->service_name;
                $query->where(function ($q) use ($serviceName) {
                    $q->where('service_ids', 'like', '%' . $serviceName . '%')
                        ->orWhere('service_ids', 'like', '%"' . $serviceName . '"%');
                });
            }

            if ($request->has('status') && $request->status != '') {
                $query->where('status', intval($request->status));
            }

            if ($request->filled('name')) {
                $query->whereHas('enquiry.client', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->name . '%');
                });
            }

            if ($request->filled('email')) {
                $query->whereHas('enquiry.client', function ($q) use ($request) {
                    $q->where('email', 'like', '%' . $request->email . '%');
                });
            }

            if ($request->filled('phone')) {
                $query->whereHas('enquiry.client', function ($q) use ($request) {
                    $q->where('contact_number', 'like', '%' . $request->phone . '%');
                });
            }

            if ($request->filled('product_id')) {
                $query->whereHas('enquiry', function ($q) use ($request) {
                    $q->where('product_ids', 'like', '%' . $request->product_id . '%');
                });
            }

            $allSalesData = $query->orderBy('created_at', 'desc')->get();

            // Get all services and products for reference
            $allServices = Service::all()->keyBy('id');
            $allExtraServices = ExtraService::all()->keyBy('id');
            $allProducts = Product::all()->keyBy('id');

            // Capture filter month/year for use inside closure
            $filterMonth = $request->filled('month') ? $request->month : null;
            $filterYear = $request->filled('year') ? $request->year : null;

            // Process sales data
            $salesData = $allSalesData->groupBy('lead_id')->map(function ($group) use ($allServices, $allExtraServices, $allProducts, $statusArray, $filterMonth, $filterYear) {
                $latest = $group->sortByDesc('created_at')->first();
                $client = $latest->enquiry->client ?? null;
                $ride = $latest->enquiry->rideSegments->first() ?? null;
                $totalAmount = (float) $latest->total_amount;

                // Get ALL followup IDs for this lead (not just current group)
                // This is important because payment might be in an earlier followup (status 3)
                // but current status is 5 (Confirmed) or 7 (Rescheduled)
                $allFollowupIdsForLead = \App\Models\LeadFollowup::where('lead_id', $latest->lead_id)
                    ->pluck('id');

                // Get approved payments from ALL followups of this lead
                $approvedPayments = PaymentAuditTrail::whereIn('lead_followup_id', $allFollowupIdsForLead)
                    ->where('payment_status', 1)
                    ->get();

                $totalReceived = $approvedPayments->sum('paid_amount');
                $pendingAmount = $totalAmount - $totalReceived;

                // Get processed/completed refund amounts for this lead
                $refundAmount = \App\Models\LeadRefund::whereIn('lead_followup_id', $allFollowupIdsForLead)
                    ->whereIn('status', [1, 2]) // 1=processed, 2=completed
                    ->sum('refund_amount');

                // Sales Amount = Total Amount - Refund Amount
                // This matches the dashboard's Achieved Amount calculation
                $salesAmount = max(0, $totalAmount - $refundAmount);

                // Get approved payments scoped to the filtered month/year
                // so paid_date shown matches the selected filter period
                // $filteredPayments = $approvedPayments;
                // if ($filterMonth || $filterYear) {
                //     $filteredPayments = $approvedPayments->filter(function ($p) use ($filterMonth, $filterYear) {
                //         if (empty($p->paid_date)) return false;
                //         try {
                //             $pd = \Carbon\Carbon::parse($p->paid_date);
                //             if ($filterMonth && $pd->month != $filterMonth) return false;
                //             if ($filterYear && $pd->year != $filterYear) return false;
                //             return true;
                //         } catch (\Exception $e) {
                //             return false;
                //         }
                //     });
                // }

                // $lastPayment = $filteredPayments->sortByDesc('paid_date')->first();
                // $paidDate = 'N/A';

                // if ($lastPayment) {
                //     if (!empty($lastPayment->paid_date)) {
                //         try {
                //             $paidDate = \Carbon\Carbon::parse($lastPayment->paid_date)->format('d-m-Y');
                //         } catch (\Exception $e) {
                //             $paidDate = $lastPayment->created_at ? $lastPayment->created_at->format('d-m-Y') : 'N/A';
                //         }
                //     } else {
                //         $paidDate = $lastPayment->created_at ? $lastPayment->created_at->format('d-m-Y') : 'N/A';
                //     }
                // }

                $firstPayment = $approvedPayments->sortBy('paid_date')->first();
                $paidDate = 'N/A';

                if ($firstPayment && !empty($firstPayment->paid_date)) {
                    try {
                        $paidDate = \Carbon\Carbon::parse($firstPayment->paid_date)->format('d-m-Y');
                    } catch (\Exception $e) {
                        $paidDate = $firstPayment->created_at ? $firstPayment->created_at->format('d-m-Y') : 'N/A';
                    }
                }

                $salesPerson = $latest->enquiry->representative ?? null;
                $salesPersonName = $salesPerson ? $salesPerson->name : 'N/A';

                // Get manager name from SalesExecutiveAssignment table
                $managerName = 'N/A';
                if ($salesPerson) {
                    $assignment = \App\Models\SalesExecutiveAssignment::where('sales_executive_id', $salesPerson->id)
                        ->where('status', 1)
                        ->with('manager')
                        ->first();
                    if ($assignment && $assignment->manager) {
                        $managerName = $assignment->manager->name;
                    }
                }

                // ... rest of the code for products, services, etc. remains the same ...

                $productNames = [];
                if (!empty($latest->enquiry->product_ids)) {
                    $productIds = is_array($latest->enquiry->product_ids)
                        ? $latest->enquiry->product_ids
                        : json_decode($latest->enquiry->product_ids, true);
                    if (is_array($productIds)) {
                        foreach ($productIds as $pid) {
                            if (isset($allProducts[$pid])) {
                                $productNames[] = $allProducts[$pid]->product;
                            }
                        }
                    }
                }

                $serviceNames = [];
                if (!empty($latest->service_ids)) {
                    $serviceIds = is_array($latest->service_ids)
                        ? $latest->service_ids
                        : json_decode($latest->service_ids, true);
                    if (is_array($serviceIds)) {
                        foreach ($serviceIds as $sid) {
                            if (isset($allServices[$sid])) {
                                $serviceNames[] = $allServices[$sid]->service;
                            }
                        }
                    }
                }

                $extraServiceNames = [];
                if (!empty($latest->extra_service_ids)) {
                    $extraServiceIds = is_array($latest->extra_service_ids)
                        ? $latest->extra_service_ids
                        : json_decode($latest->extra_service_ids, true);
                    if (is_array($extraServiceIds)) {
                        foreach ($extraServiceIds as $esid) {
                            if (isset($allExtraServices[$esid])) {
                                $extraServiceNames[] = $allExtraServices[$esid]->extra_service;
                            }
                        }
                    }
                }

                return (object) [
                    'followup_id' => $latest->id,
                    'lead_id' => $latest->lead_id,
                    'client_name' => $client->name ?? 'N/A',
                    'email' => $client->email ?? 'N/A',
                    'contact' => $client->contact_number ?? 'N/A',
                    'received_amount' => $totalReceived,
                    'pending_amount' => $pendingAmount,
                    'total_amount' => $totalAmount,
                    'refund_amount' => $refundAmount,
                    'sales_amount' => $salesAmount,
                    'sales_person_name' => $salesPersonName,
                    'product_name' => implode(', ', $productNames) ?: 'N/A',
                    'service' => implode(', ', $serviceNames) ?: 'N/A',
                    'extra_service' => implode(', ', $extraServiceNames) ?: 'N/A',
                    'paid_date' => $paidDate, // NOW WILL SHOW CORRECTLY!
                    'ride_status' => $statusArray[$latest->status] ?? 'Unknown',
                    'booking_date' => $latest->created_at ? $latest->created_at->format('d-m-Y') : 'N/A',
                    'service_date' => $ride && $ride->from_date ? (is_string($ride->from_date) ? $ride->from_date : $ride->from_date->format('d-m-Y')) : 'N/A',
                    'manager_name' => $managerName,
                    'status' => $latest->status,
                    'created_at' => $latest->created_at,
                ];
            })->filter(function ($row) {
                return isset($row->paid_date) && $row->paid_date !== 'N/A';
            })->values();
        }

        // Get services and products for filter dropdown
        $services = Service::select('id', 'service')->get();
        $products = Product::select('id', 'product')->get();

        // Get staff based on logged-in user hierarchy
        $staff = $this->getUsersInHierarchy();
        if (!$staff) {
            $authUser = auth()->user();
            $staff = $authUser ? collect([$authUser]) : collect();
        }

        // Build manager list
        $parentTypeIds = \App\Models\UserType::whereNotNull('parent_id')->pluck('parent_id')->unique();
        $managers = User::whereIn('user_type_id', $parentTypeIds)->where('status', 1)->orderBy('name')->get();

        return view('admin.report.sales_report', compact(
            'salesData',
            'services',
            'products',
            'staff',
            'managers',
            'statusArray',
            'representatives',
            'hasFilters'
        ));
    }

    /**
     * Vendor Report - show vendor payment rows in a flat report
     */
    public function vendorReport(Request $request)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        try {
            $query = \App\Models\LeadVendorPayment::with([
                'lead.client',
                'lead.representative',
                'lead.rideSegments',
                'vendor',
                'voucher.invoice',
                'paymentDetails.service',
                'paymentDetails.extraService',
                'vendorPayments',
                'lead.leadFollowups.paymentAuditTrail'
            ]);

            // Filters
            if ($request->filled('from_payment_date')) {
                $fromDate = Carbon::parse($request->from_payment_date)->startOfDay();
                $query->whereHas('vendorPayments', function ($q) use ($fromDate) {
                    $q->where('paid_date', '>=', $fromDate);
                });
            }
            if ($request->filled('to_payment_date')) {
                $toDate = Carbon::parse($request->to_payment_date)->endOfDay();
                $query->whereHas('vendorPayments', function ($q) use ($toDate) {
                    $q->where('paid_date', '<=', $toDate);
                });
            }

            if ($request->filled('client_id')) {
                $query->whereHas('lead.client', function ($q) use ($request) {
                    $q->where('id', $request->client_id);
                });
            }
            if ($request->filled('vendor_id')) {
                $query->where('vendor_id', $request->vendor_id);
            }
            if ($request->filled('service_id')) {
                $serviceId = $request->service_id;
                $query->whereHas('paymentDetails', function ($q) use ($serviceId) {
                    $q->where('service_id', $serviceId);
                });
            }

            if ($request->filled('status')) {
                $status = strtolower(trim($request->status));
                if (in_array($status, ['paid', 'full paid', 'full_paid', 'full'])) {
                    $query->where('payment_status', 'paid');
                } elseif (in_array($status, ['partial', 'partial_paid'])) {
                    $query->where('payment_status', 'partial');
                } elseif (in_array($status, ['unpaid'])) {
                    $query->where(function ($q) {
                        $q->whereNull('payment_status')->orWhere('payment_status', 'unpaid');
                    });
                } else {
                    $query->where('payment_status', $request->status);
                }
            }

            $vendorPayments = $query->orderByDesc('created_at')->get();

            $rows = $vendorPayments->map(function ($vp) {
                $lead = $vp->lead;
                $client = $lead->client ?? null;
                $clientName = $client->name ?? 'N/A';
                $clientContact = $client->contact_number ?? 'N/A';
                $productNames = $lead->product_names ?? [];
                $productDisplay = is_array($productNames) ? implode(', ', $productNames) : (is_string($productNames) ? $productNames : 'N/A');
                $vendorName = $vp->vendor->name ?? 'N/A';
                $vendorServiceCost = $vp->total_vendor_service_amount ?? 0;
                $paidAmount = $vp->vendorPayments->sum('paid_amount') ?? 0;
                $balance = $vendorServiceCost - $paidAmount;
                $paidDate = $vp->vendorPayments->pluck('paid_date')->filter()->sort()->last() ?? 'Not Paid';

                // service date from first ride
                $firstRide = $lead->rideSegments->first();
                $serviceDate = 'N/A';
                if ($firstRide && $firstRide->from_date) {
                    try {
                        $serviceDate = Carbon::parse($firstRide->from_date)->format('d M Y');
                    } catch (\Exception $e) {
                        $serviceDate = $firstRide->from_date;
                    }
                }

                // booking date - first payments in audit trail
                $bookingDate = 'N/A';
                $firstPaymentDate = null;
                foreach ($lead->leadFollowups as $followup) {
                    $firstPayment = $followup->paymentAuditTrail->sortBy('paid_date')->first();
                    if ($firstPayment && $firstPayment->paid_date) {
                        if (!$firstPaymentDate || Carbon::parse($firstPayment->paid_date)->lt(Carbon::parse($firstPaymentDate))) {
                            $firstPaymentDate = $firstPayment->paid_date;
                        }
                    }
                }
                if ($firstPaymentDate) {
                    try {
                        $bookingDate = Carbon::parse($firstPaymentDate)->format('d M Y');
                    } catch (\Exception $e) {
                        $bookingDate = $firstPaymentDate;
                    }
                }

                // ride status -- compute similar to export
                $rideStatus = 'Pending';
                $followupStatusRecord = $lead->leadFollowups()->orderByDesc('created_at')->first();
                if ($followupStatusRecord && in_array($followupStatusRecord->status, [1, 2, 5, 7])) {
                    switch ($followupStatusRecord->status) {
                        case 1:
                            $rideStatus = 'Active';
                            break;
                        case 2:
                            $rideStatus = 'Cancelled';
                            break;
                        case 7:
                            $rideStatus = 'Rescheduled';
                            break;
                        case 5:
                            $rideStatus = 'Complete';
                            break;
                    }
                } else {
                    if ($firstRide) {
                        if (!empty($firstRide->is_tba)) $rideStatus = 'TBA';
                        elseif ($firstRide->from_date && $firstRide->to_date) {
                            $now = Carbon::now();
                            $fromDate = Carbon::parse($firstRide->from_date);
                            $toDate = Carbon::parse($firstRide->to_date);
                            if ($now->lt($fromDate)) $rideStatus = 'Upcoming';
                            elseif ($now->between($fromDate, $toDate)) $rideStatus = 'In Progress';
                            else $rideStatus = 'Completed';
                        } else $rideStatus = 'Pending';
                    }
                }

                // booking slip number
                $bookingSlipNumber = 'N/A';
                if ($vp->voucher && $vp->voucher->invoice) {
                    $bookingSlipNumber = $vp->voucher->invoice->invoice_id ?? 'N/A';
                }

                // manager name
                $managerName = 'N/A';
                try {
                    $rep = $lead->representative ?? null;
                    if ($rep) {
                        $assignment = \App\Models\SalesExecutiveAssignment::where('sales_executive_id', $rep->id)
                            ->where('status', 1)
                            ->with('manager')
                            ->first();
                        if ($assignment && $assignment->manager) {
                            $managerName = $assignment->manager->name;
                        }
                    }
                } catch (\Exception $e) {
                    // ignore
                }

                return (object) [
                    'vendor_name' => $vendorName,
                    'booking_slip_number' => $bookingSlipNumber,
                    'product' => $productDisplay,
                    'client_name' => $clientName,
                    'client_contact' => $clientContact,
                    'vendor_service_cost' => $vendorServiceCost,
                    'balance_amount' => $balance,
                    'paid_amount' => $paidAmount,
                    'paid_date' => $paidDate,
                    'ride_status' => $rideStatus,
                    'service_date' => $serviceDate,
                    'booking_date' => $bookingDate,
                    'manager_name' => $managerName,
                ];
            });

            // Lists for filters
            $services = Service::where('status', 1)->get();
            $vendors = \App\Models\Vendor::where('status', 1)->orderBy('name')->get();
            $clients = \App\Models\Client::where('status', 1)->orderBy('name')->get();

            return view('admin.report.vendor_report', compact('rows', 'services', 'vendors', 'clients'));
        } catch (\Exception $e) {
            Log::error('Error loading vendor report: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading vendor report');
        }
    }

    /**
     * Export Vendor Report to Excel/CSV
     */
    public function exportVendorReport(Request $request)
    {
        try {
            $filters = [
                'client_id' => $request->get('client_id'),
                'vendor_id' => $request->get('vendor_id'),
                'service_id' => $request->get('service_id'),
                'status' => $request->get('status'),
            ];

            $format = $request->get('format', 'xlsx');
            $fileName = 'vendor_report_' . date('Y-m-d_H-i-s') . '.' . ($format === 'csv' ? 'csv' : 'xlsx');

            return Excel::download(new \App\Exports\VendorReportExport($filters), $fileName);
        } catch (\Exception $e) {
            Log::error('Error exporting vendor report: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error exporting vendor report: ' . $e->getMessage());
        }
    }

    /**
     * Export Sales Report to Excel/CSV
     */
    public function exportSalesReport(Request $request)
    {
        $format = $request->get('format', 'xlsx');
        $filters = $request->except('format');

        $fileName = 'sales_report_' . date('Y-m-d_His') . '.' . $format;

        return Excel::download(new SalesReportExport($filters), $fileName);
    }

    /**
     * Profit/Loss Report - Display profit/loss data with customer, vendor, and salesperson details
     */
    public function profitLossReport(Request $request)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $currentUser = auth()->user();
        $representatives = getRepresentativeIds($currentUser);

        // Status array
        $statusArray = [
            // 0 => 'Initiated',
            // 1 => 'Active',
            // 2 => 'Cancelled',
            3 => 'Full Payment Received',
            4 => 'Partial Payment Received',
            5 => 'Confirmed',
            6 => 'Pending',
            7 => 'Rescheduled',
            8 => 'Approved',
            // 9 => 'Rejected'
        ];

        // Base query
        $query = LeadFollowup::with([
            'enquiry.client.country',
            'enquiry.client.city',
            'enquiry.rideSegments',
            'enquiry.representative.userType',
            'enquiry.leadVendorPayments.vendor',
            'enquiry.leadVendorPayments.vendorPayments',
            'followedBy.userType'
        ])->whereIn('status', [3, 4, 5, 6, 7, 8]);

        // Apply filters
        if ($request->filled('from_payment_date')) {
            $fromDate = Carbon::parse($request->from_payment_date)->startOfDay();
            $query->whereHas('paymentAuditTrail', function ($q) use ($fromDate) {
                $q->where('paid_date', '>=', $fromDate);
            });
        }

        if ($request->filled('to_payment_date')) {
            $toDate = Carbon::parse($request->to_payment_date)->endOfDay();
            $query->whereHas('paymentAuditTrail', function ($q) use ($toDate) {
                $q->where('paid_date', '<=', $toDate);
            });
        }

        if ($request->filled('manager_user_id')) {
            $managerUserId = $request->manager_user_id;
            // Get all sales executives assigned to this manager
            $assignedExecutives = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($managerUserId);
            $assignedExecutiveIds = $assignedExecutives->pluck('id')->toArray();
            // Include the manager themselves in the filter
            $assignedExecutiveIds[] = $managerUserId;

            $query->whereHas('enquiry', function ($q) use ($assignedExecutiveIds) {
                $q->whereIn('representative_user_id', $assignedExecutiveIds);
            });
        }

        if ($request->filled('from_create_date')) {
            $fromDate = Carbon::parse($request->from_create_date)->startOfDay();
            $query->whereHas('enquiry', function ($q) use ($fromDate) {
                $q->where('created_at', '>=', $fromDate);
            });
        }

        if ($request->filled('to_create_date')) {
            $toDate = Carbon::parse($request->to_create_date)->endOfDay();
            $query->whereHas('enquiry', function ($q) use ($toDate) {
                $q->where('created_at', '<=', $toDate);
            });
        }

        if ($request->filled('service_name')) {
            $serviceName = $request->service_name;
            $query->where(function ($q) use ($serviceName) {
                $q->where('service_ids', 'like', '%' . $serviceName . '%')
                    ->orWhere('service_ids', 'like', '%"' . $serviceName . '"%');
            });
        }

        if ($request->has('status') && $request->status != '') {
            $query->where('status', intval($request->status));
        }

        if ($request->filled('name')) {
            $query->whereHas('enquiry.client', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->name . '%');
            });
        }

        if ($request->filled('email')) {
            $query->whereHas('enquiry.client', function ($q) use ($request) {
                $q->where('email', 'like', '%' . $request->email . '%');
            });
        }

        if ($request->filled('phone')) {
            $query->whereHas('enquiry.client', function ($q) use ($request) {
                $q->where('contact_number', 'like', '%' . $request->phone . '%');
            });
        }

        if ($request->filled('representative_user_id')) {
            $representativeUserId = $request->representative_user_id;
            $query->whereHas('enquiry', function ($q) use ($representativeUserId) {
                $q->where('representative_user_id', $representativeUserId);
            });
        }

        if ($request->filled('product_id')) {
            $query->whereHas('enquiry', function ($q) use ($request) {
                $q->where('product_ids', 'like', '%' . $request->product_id . '%');
            });
        }
        // Build manager list: users whose user_type is a parent of other user types
        $parentTypeIds = \App\Models\UserType::whereNotNull('parent_id')->pluck('parent_id')->unique();
        $managers = User::whereIn('user_type_id', $parentTypeIds)->where('status', 1)->orderBy('name')->get();


        $allData = $query->orderBy('created_at', 'desc')->get();

        $allLeadIds = $allData->pluck('lead_id')->unique();

        // Get ALL followup IDs for these leads (not just filtered ones)
        // Get ALL followup IDs for these leads with their lead_id
        // Use get() instead of pluck('id','lead_id') to avoid losing duplicate lead_ids
        $allFollowupsForLeads = LeadFollowup::whereIn('lead_id', $allLeadIds)
            ->select('id', 'lead_id')
            ->get();
        $followupToLeadMap = $allFollowupsForLeads->pluck('lead_id', 'id'); // followup_id => lead_id
        $allFollowupIds = $allFollowupsForLeads->pluck('id');
        // Get lead IDs that have at least one approved payment
        $approvedLeadIds = PaymentAuditTrail::whereIn('lead_followup_id', $allFollowupIds)
            ->where('payment_status', 1)
            ->get()
            ->map(function ($pat) use ($followupToLeadMap) {
                return $followupToLeadMap->get($pat->lead_followup_id);
            })
            ->filter()
            ->unique()
            ->values();
        // Process profit/loss data
        $profitLossData = $allData->groupBy('lead_id')->map(function ($group) use ($statusArray, $approvedLeadIds) {
            $latest = $group->sortByDesc('created_at')->first();

            // ONLY show leads that have at least one approved payment
            if (!$approvedLeadIds->contains($latest->lead_id)) {
                return null;
            }

            // Also skip if latest followup status is not in qualifying statuses
            if (!in_array($latest->status, [3, 4, 5, 6, 7, 8])) {
                return null;
            }

            $client = $latest->enquiry->client ?? null;
            $totalAmount = (float) $latest->total_amount;

            // Get ALL followup IDs for this lead
            $allFollowupIdsForLead = LeadFollowup::where('lead_id', $latest->lead_id)->pluck('id');

            // Get approved payments (client received amount)
            $approvedPayments = PaymentAuditTrail::whereIn('lead_followup_id', $allFollowupIdsForLead)
                ->where('payment_status', 1)
                ->get();
            $clientReceivedAmount = $approvedPayments->sum('paid_amount');

            // Get vendor payments for this lead
            $vendorPayments = $latest->enquiry->leadVendorPayments ?? collect();
            $totalVendorAmount = 0;
            $vendorNames = [];

            foreach ($vendorPayments as $vp) {
                $vendorAmount = $vp->total_vendor_service_amount ?? 0;
                $totalVendorAmount += $vendorAmount;
                if ($vp->vendor) {
                    $vendorNames[] = $vp->vendor->name;
                }
            }

            // Calculate profit/loss
            $profitLoss = $clientReceivedAmount - $totalVendorAmount;
            $profitLossPercent = 0;
            if ($clientReceivedAmount > 0) {
                $profitLossPercent = ($profitLoss / $clientReceivedAmount) * 100;
            }

            // Get sales person (representative)
            $salesPerson = $latest->enquiry->representative ?? null;
            $salesPersonName = $salesPerson ? $salesPerson->name : 'N/A';

            // Get manager name from SalesExecutiveAssignment table
            $managerName = 'N/A';
            if ($salesPerson) {
                $assignment = \App\Models\SalesExecutiveAssignment::where('sales_executive_id', $salesPerson->id)
                    ->where('status', 1)
                    ->with('manager')
                    ->first();
                if ($assignment && $assignment->manager) {
                    $managerName = $assignment->manager->name;
                }
            }

            // Get last paid date from approved payments
            $lastPaidDate = $approvedPayments->sortByDesc('paid_date')->first();
            $paidDateFormatted = $lastPaidDate && $lastPaidDate->paid_date
                ? Carbon::parse($lastPaidDate->paid_date)->format('d M Y')
                : 'N/A';

            return (object) [
                'followup_id' => $latest->id,
                'lead_id' => $latest->lead_id,
                'customer_name' => $client->name ?? 'N/A',
                'client_received_amount' => $clientReceivedAmount,
                'paid_date' => $paidDateFormatted,
                'vendor_name' => !empty($vendorNames) ? implode(', ', $vendorNames) : 'N/A',
                'vendor_amount' => $totalVendorAmount,
                'profit_loss' => $profitLoss,
                'profit_loss_percent' => $profitLossPercent,
                'sales_person_name' => $salesPersonName,
                'manager_name' => $managerName,
                'status' => $latest->status,
                'created_at' => $latest->created_at,
            ];
        })->filter()->values();

        // Get services and products for filter dropdown
        $services = Service::select('id', 'service')->get();
        $products = Product::select('id', 'product')->get();

        // Get staff based on logged-in user hierarchy
        $staff = $this->getUsersInHierarchy();
        if (!$staff) {
            $authUser = auth()->user();
            $staff = $authUser ? collect([$authUser]) : collect();
        }

        return view('admin.report.profit_loss_report', compact(
            'profitLossData',
            'services',
            'products',
            'staff',
            'statusArray',
            'representatives',
            'managers'
        ));
    }

    /**
     * KPI Report - Aggregated per employee (sales person)
     */
    public function kpiReport(Request $request)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $currentUser = auth()->user();
        $representatives = getRepresentativeIds($currentUser);

        $targetPeriod = $this->resolveTargetPeriod($request);
        $query = LeadFollowup::with(['enquiry.representative.userType', 'enquiry.rideSegments'])
            ->whereIn('status', [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]);

        if ($request->filled('month')) {

            // Month filter uses lead's created_at date (not followup's)
            $month = Carbon::createFromFormat('Y-m', $request->month);

            $start = $month->copy()->startOfMonth()->startOfDay();
            $end   = $month->copy()->endOfMonth()->endOfDay();

            $query->whereHas('enquiry', function ($q) use ($start, $end) {
                $q->where('created_at', '>=', $start)
                    ->where('created_at', '<=', $end);
            });
        } else {

            if ($request->filled('from_date')) {
                $fromDate = Carbon::parse($request->from_date)->startOfDay();
                $query->whereHas('enquiry', function ($q) use ($fromDate) {
                    $q->where('created_at', '>=', $fromDate);
                });
            }

            if ($request->filled('to_date')) {
                $toDate = Carbon::parse($request->to_date)->endOfDay();
                $query->whereHas('enquiry', function ($q) use ($toDate) {
                    $q->where('created_at', '<=', $toDate);
                });
            }
        }
        // if ($request->filled('from_date')) {
        //     $fromDate = Carbon::parse($request->from_date)->startOfDay();
        //     $query->whereHas('enquiry.rideSegments', function ($q) use ($fromDate) {
        //         $q->where('from_date', '>=', $fromDate);
        //     });
        // }

        // if ($request->filled('to_date')) {
        //     $toDate = Carbon::parse($request->to_date)->endOfDay();
        //     $query->whereHas('enquiry.rideSegments', function ($q) use ($toDate) {
        //         $q->where('to_date', '<=', $toDate);
        //     });
        // }



        if ($request->filled('from_create_date')) {
            $fromDate = Carbon::parse($request->from_create_date)->startOfDay();
            $query->whereHas('enquiry', function ($q) use ($fromDate) {
                $q->where('created_at', '>=', $fromDate);
            });
        }

        if ($request->filled('to_create_date')) {
            $toDate = Carbon::parse($request->to_create_date)->endOfDay();
            $query->whereHas('enquiry', function ($q) use ($toDate) {
                $q->where('created_at', '<=', $toDate);
            });
        }

        if ($request->filled('service_name')) {
            $serviceName = $request->service_name;
            $query->where(function ($q) use ($serviceName) {
                $q->where('service_ids', 'like', '%' . $serviceName . '%')
                    ->orWhere('service_ids', 'like', '%"' . $serviceName . '"%');
            });
        }

        if ($request->filled('representative_user_id')) {
            $representativeUserId = $request->representative_user_id;
            $query->whereHas('enquiry', function ($q) use ($representativeUserId) {
                $q->where('representative_user_id', $representativeUserId);
            });
        }

        if ($request->filled('product_id')) {
            $query->whereHas('enquiry', function ($q) use ($request) {
                $q->where('product_ids', 'like', '%' . $request->product_id . '%');
            });
        }

        if ($request->filled('manager_user_id')) {
            $managerUserId = $request->manager_user_id;
            // Get all sales executives assigned to this manager
            $assignedExecutives = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($managerUserId);
            $assignedExecutiveIds = $assignedExecutives->pluck('id')->toArray();
            // Include the manager themselves in the filter
            $assignedExecutiveIds[] = $managerUserId;

            $query->whereHas('enquiry', function ($q) use ($assignedExecutiveIds) {
                $q->whereIn('representative_user_id', $assignedExecutiveIds);
            });
        }

        $allData = $query->orderBy('created_at', 'desc')->get();

        // Group by representative
        $groupedByRep = $allData->groupBy(function ($f) {
            return $f->enquiry && $f->enquiry->representative ? $f->enquiry->representative->id : 'no_rep';
        });

        $kpiData = $groupedByRep->map(function ($group, $repId) use ($targetPeriod) {
            $rep = null;
            if ($repId !== 'no_rep') {
                $rep = $group->first()->enquiry->representative ?? null;
            }
            $repName = $rep ? $rep->name : 'N/A';

            $totalLeads = $group->groupBy('lead_id')->count();
            $activeCount = $group->groupBy('lead_id')->map(function ($leadGroup) {
                $latest = $leadGroup->sortByDesc('created_at')->first();
                return in_array($latest->status, [1]) ? 1 : 0;
            })->sum();
            $cancelledCount = $group->groupBy('lead_id')->map(function ($leadGroup) {
                $latest = $leadGroup->sortByDesc('created_at')->first();
                return $latest->status == 2 ? 1 : 0;
            })->sum();

            // Completed count - latest status in [3, 4, 5, 7, 8]
            $completedCount = $group->groupBy('lead_id')->map(function ($leadGroup) {
                $latest = $leadGroup->sortByDesc('created_at')->first();
                return in_array($latest->status, [3, 4, 5, 7, 8]) ? 1 : 0;
            })->sum();

            $conversionRate = 0;
            if ($totalLeads > 0) {
                $conversionRate = ($completedCount / $totalLeads) * 100;
            }

            $targetStats = [
                'target_amount' => 0,
                'achieved_amount' => 0,
                'remaining_amount' => 0,
            ];

            if ($rep && $repId !== 'no_rep') {
                $targetStats = $this->getTargetStatsForRepresentative($rep->id, $targetPeriod['year'], $targetPeriod['month']);
            }

            return (object) [
                'representative_id' => $repId !== 'no_rep' ? $repId : null,
                'employee' => $repName,
                'total_leads' => $totalLeads,
                'active' => $activeCount,
                'cancelled' => $cancelledCount,
                'completed' => $completedCount,
                'conversion_rate' => $conversionRate,
                'target_amount' => $targetStats['target_amount'],
                'achieved_amount' => $targetStats['achieved_amount'],
                'remaining_amount' => $targetStats['remaining_amount'],
            ];
        })->values();

        $kpiTotals = [
            'target_amount' => $kpiData->sum('target_amount'),
            'achieved_amount' => $kpiData->sum('achieved_amount'),
            'remaining_amount' => $kpiData->sum('remaining_amount'),
        ];

        $services = Service::select('id', 'service')->get();
        $managers = User::whereIn('user_type_id', \App\Models\UserType::whereNotNull('parent_id')->pluck('parent_id')->unique())->where('status', 1)->orderBy('name')->get();
        $staff = $this->getUsersInHierarchy();

        return view('admin.report.kpi_report', compact('kpiData', 'services', 'staff', 'managers', 'representatives', 'kpiTotals'));
    }

    /**
     * Export KPI report
     */
    public function exportKpiReport(Request $request)
    {
        try {
            $format = $request->get('format', 'xlsx');
            $filters = $request->except('format');
            $fileName = 'kpi_report_' . date('Y-m-d_His') . '.' . $format;

            return Excel::download(new \App\Exports\KPIReportExport($filters), $fileName);
        } catch (\Exception $e) {
            Log::error('Error exporting KPI report: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error exporting KPI report: ' . $e->getMessage());
        }
        // try {
        //     $format = $request->get('format', 'xlsx');
        //     $filters = $request->except('format');

        //     $fileName = 'kpi_report_' . date('Y-m-d_His') . '.' . ($format === 'csv' ? 'csv' : 'xlsx');

        //     return Excel::download(new \App\Exports\KPIReportExport($filters), $fileName);

        // } catch (\Exception $e) {
        //     \Log::error('KPI export error: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
        //     return response()->json(['error' => 'Export failed: ' . $e->getMessage()], 500);
        // }
    }

    /**
     * Export individual KPI report for a specific sales executive/manager
     */
    public function exportKpiReportIndividual(Request $request, $representative_id)
    {
        try {
            $format = $request->get('format', 'xlsx');
            $filters = $request->except('format');

            // Get the representative name for the filename
            $representative = User::find($representative_id);
            $repName = $representative ? preg_replace('/[^A-Za-z0-9_]/', '_', $representative->name) : 'unknown';

            $fileName = 'kpi_report_' . $repName . '_' . date('Y-m-d_His') . '.' . $format;

            return Excel::download(new \App\Exports\KPIReportIndividualExport($filters, $representative_id), $fileName);
        } catch (\Exception $e) {
            Log::error('Error exporting individual KPI report: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error exporting individual KPI report: ' . $e->getMessage());
        }
    }

    /**
     * Export Profit/Loss Report to Excel/CSV
     */
    public function exportProfitLossReport(Request $request)
    {
        try {
            $format = $request->get('format', 'xlsx');
            $filters = $request->except('format');

            $fileName = 'profit_loss_report_' . date('Y-m-d_His') . '.' . $format;

            return Excel::download(new \App\Exports\ProfitLossReportExport($filters), $fileName);
        } catch (\Exception $e) {
            Log::error('Error exporting profit/loss report: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error exporting profit/loss report: ' . $e->getMessage());
        }
    }

    private function resolveTargetPeriod(Request $request): array
    {
        // If month filter provided (format: Y-m), use it directly
        if ($request->filled('month')) {
            try {
                $date = Carbon::createFromFormat('Y-m', $request->month);
                return [
                    'year'  => (int) $date->format('Y'),
                    'month' => (int) $date->format('n'),
                ];
            } catch (\Throwable $e) {
            }
        }

        $date = null;
        foreach (['from_date', 'to_date', 'from_create_date', 'to_create_date'] as $param) {
            if ($request->filled($param)) {
                try {
                    $date = Carbon::parse($request->input($param));
                    break;
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        $date = $date ?: Carbon::now();

        return [
            'year' => (int) $date->format('Y'),
            'month' => (int) $date->format('n'),
        ];
    }

    /**
     * AJAX: Get sales persons assigned to a specific manager
     */
    public function getSalesPersonsByManager(Request $request)
    {
        $managerId = $request->get('manager_id');

        if (!$managerId) {
            // Return all staff in hierarchy when no manager selected
            $staff = $this->getUsersInHierarchy();
            if (!$staff) {
                $authUser = auth()->user();
                $staff = $authUser ? collect([$authUser]) : collect();
            }
            return response()->json([
                'data' => $staff->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'user_type' => optional($u->userType)->user_type ?? 'N/A',
                    ];
                })->values()
            ]);
        }

        $executives = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($managerId);

        // Include the manager themselves in the list (manager is also a sales person)
        $manager = User::with('userType')->find($managerId);
        if ($manager) {
            // Prepend manager to the collection so they appear first
            $executives = collect([$manager])->merge($executives);
        }

        return response()->json([
            'data' => $executives->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'user_type' => optional($u->userType)->user_type ?? 'N/A',
                ];
            })->values()
        ]);
    }

    // private function getTargetStatsForRepresentative($repId, int $year, int $month): array
    // {
    //     // use raw rep id (may be UUID string) when querying
    //     $targetAmount = Target::where('sales_executive_id', $repId)
    //         ->where('year', $year)
    //         ->where('month', $month)
    //         ->where('status', 'active')
    //         ->sum('target_amount');

    //     $achievedAmount = LeadFollowup::whereHas('enquiry', function ($q) use ($repId) {
    //         $q->where('representative_user_id', $repId);
    //     })
    //         ->whereYear('created_at', $year)
    //         ->whereMonth('created_at', $month)
    //         ->whereHas('paymentAuditTrail', function ($q) {
    //             $q->where('payment_status', 1);
    //         })
    //         ->sum('total_amount');

    //     $remainingAmount = max(0, (float) $targetAmount - (float) $achievedAmount);

    //     return [
    //         'target_amount' => (float) $targetAmount,
    //         'achieved_amount' => (float) $achievedAmount,
    //         'remaining_amount' => (float) $remainingAmount,
    //     ];
    // }

    private function getTargetStatsForRepresentative($repId, int $year, int $month): array
    {
        $targetAmount = Target::where('sales_executive_id', $repId)
            ->where('year', $year)
            ->where('month', $month)
            ->where('status', 'active')
            ->sum('target_amount');

        // Mirror dashboard logic exactly:
        // Step 1: paid followup IDs in this period
        $paidFollowupIds = \App\Models\PaymentAuditTrail::where('payment_status', 1)
            ->whereYear('paid_date', $year)
            ->whereMonth('paid_date', $month)
            ->pluck('lead_followup_id')->unique();

        if ($paidFollowupIds->isEmpty()) {
            return [
                'target_amount'    => (float) $targetAmount,
                'achieved_amount'  => 0.0,
                'remaining_amount' => (float) $targetAmount,
            ];
        }

        // Step 2: lead IDs scoped to this rep
        $paidLeadIds = LeadFollowup::whereIn('id', $paidFollowupIds)
            ->whereHas('enquiry', function ($q) use ($repId) {
                $q->where('representative_user_id', $repId);
            })
            ->pluck('lead_id')->unique();

        if ($paidLeadIds->isEmpty()) {
            return [
                'target_amount'    => (float) $targetAmount,
                'achieved_amount'  => 0.0,
                'remaining_amount' => (float) $targetAmount,
            ];
        }

        // Step 3: ALL followups for those leads
        $allFollowups = LeadFollowup::whereIn('lead_id', $paidLeadIds)
            ->whereHas('enquiry', function ($q) use ($repId) {
                $q->where('representative_user_id', $repId);
            })
            ->get();

        // Step 4: batch-load followup IDs per lead
        $allFollowupIdsByLead = $allFollowups->groupBy('lead_id')
            ->map(fn($group) => $group->pluck('id'));

        $allFollowupIdsFlat = $allFollowupIdsByLead->flatten();

        // Step 5: batch-load all refunds in one query
        $refundsByFollowupId = \App\Models\LeadRefund::whereIn('lead_followup_id', $allFollowupIdsFlat)
            ->whereIn('status', [1, 2])
            ->get()
            ->groupBy('lead_followup_id');

        // Step 6: per-lead — latest qualifying followup [2,5,7,8], subtract refunds
        $achievedAmount = $allFollowups->groupBy('lead_id')->map(function ($group) use ($allFollowupIdsByLead, $refundsByFollowupId) {
            $qualifying = $group->filter(fn($f) => in_array($f->status, [2, 5, 7, 8]));
            $latest = $qualifying->sortByDesc('created_at')->first();

            if (!$latest) return 0;

            $leadFollowupIds = $allFollowupIdsByLead->get($latest->lead_id, collect());
            $refund = $leadFollowupIds->sum(function ($fid) use ($refundsByFollowupId) {
                return $refundsByFollowupId->get($fid, collect())->sum('refund_amount');
            });

            return max(0, (float) $latest->total_amount - $refund);
        })->sum();

        $remainingAmount = max(0, (float) $targetAmount - (float) $achievedAmount);

        return [
            'target_amount' => (float) $targetAmount,
            'achieved_amount' => (float) $achievedAmount,
            'remaining_amount' => (float) $remainingAmount,
        ];
    }
}
