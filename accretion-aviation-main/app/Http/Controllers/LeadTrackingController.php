<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use App\Models\Client;
use App\Models\Voucher;
use App\Models\UserType;
use App\Models\LeadFollowup;
use App\Models\LeadPayment;
use App\Models\LeadVendorPayment;
use App\Models\PaymentAuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadTrackingController extends Controller
{
    /**
     * Display a listing of all leads for tracking
     */
    public function index(Request $request)
    {
        try {
            $currentUser = auth()->user();
            $userType = $currentUser->userType->user_type ?? null;

            // Check if any filter is applied
            $hasFilters = $request->filled('representative_user_id') ||
                          $request->filled('from_date') ||
                          $request->filled('to_date') ||
                          $request->filled('invoice_number') ||
                          $request->filled('name') ||
                          $request->filled('email') ||
                          $request->filled('phone');

            // Initialize empty collection - only load data when filters are applied
            $leads = collect();
            $leadsPaginator = null;
            $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

            if ($hasFilters) {
                // Build the query for leads -- eager load the latest followup via a hasOne relation and vouchers->invoice
                $query = Lead::with([
                    'client',
                    'representative',
                    // Use the hasOne relation that returns the latest followup per lead
                    'latestFollowup.followedBy',
                    'latestFollowup.paymentAuditTrail',
                    // also eager-load vouchers and their invoice to display invoice number in index
                    'vouchers.invoice'
                ]);

                // Apply user-based filters
                if (!in_array($userType, [UserType::SUPER_ADMIN, UserType::ADMIN])) {
                    if (in_array($userType, [UserType::SENIOR_SALES_MANAGER, UserType::SALES_MANAGER])) {
                        // Managers can see leads assigned to them and their team
                        $assignedExecutiveIds = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($currentUser->id)->pluck('id')->toArray();
                        $userIds = array_merge($assignedExecutiveIds, [$currentUser->id]);
                        $query->whereIn('representative_user_id', $userIds);
                    } elseif (!in_array($userType, UserType::OPERATIONS_ROLES) && !in_array($userType, UserType::ACCOUNTS_ROLES)) {
                        // Sales executives only see their own leads
                        $query->where('representative_user_id', $currentUser->id);
                    }
                    // Operations roles and Accounts roles can see all leads
                }

                if ($request->filled('representative_user_id')) {
                    $query->where('representative_user_id', $request->representative_user_id);
                }

                if ($request->filled('from_date')) {
                    $query->where('created_at', '>=', $request->from_date);
                }

                if ($request->filled('to_date')) {
                    $query->where('created_at', '<=', $request->to_date . ' 23:59:59');
                }

                // Invoice number filter: search by invoice 'invoice_id' on invoice model via vouchers
                if ($request->filled('invoice_number')) {
                    $query->whereHas('vouchers.invoice', function ($q) use ($request) {
                        $q->where('invoice_id', 'like', '%' . $request->invoice_number . '%');
                    });
                }

                // Name filter: search by client name
                if ($request->filled('name')) {
                    $query->whereHas('client', function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->name . '%');
                    });
                }

                // Email filter: search by client email
                if ($request->filled('email')) {
                    $query->whereHas('client', function ($q) use ($request) {
                        $q->where('email', 'like', '%' . $request->email . '%');
                    });
                }

                // Phone filter: search by client contact number
                if ($request->filled('phone')) {
                    $query->whereHas('client', function ($q) use ($request) {
                        $q->where('contact_number', 'like', '%' . $request->phone . '%');
                    });
                }

                $leadsPaginator = $query->orderBy('created_at', 'desc')
                    ->paginate($perPage)
                    ->appends($request->query());
                $leads = $leadsPaginator;
            }

            // Get staff for filter dropdown
            $staff = $this->getUsersForFilter($currentUser, $userType);

            return view('admin.pages.lead-tracking.index', compact('leads', 'leadsPaginator', 'staff', 'hasFilters'));
        } catch (\Exception $e) {
            Log::error('Error in LeadTrackingController@index: ' . $e->getMessage());
            return back()->with('error', 'An error occurred while loading leads.');
        }
    }

    /**
     * Show detailed information for a specific lead
     */
    public function show($id)
    {
        try {
            // Load lead with all necessary relationships
            $lead = Lead::with([
                'client',
                'representative',
                'vouchers.operationTeam',
                'vouchers.passengers',
                'vouchers.payment.paymentDetails.service',
                'vouchers.vendorPayments.vendor',
                'vouchers.vendorPayments.paymentDetails',
                'leadFollowups.followedBy',
                'leadFollowups.paymentAuditTrail',
                'rideSegments',
                'passengers'
            ])->findOrFail($id);

            // Check authorization
            $currentUser = auth()->user();
            $userType = $currentUser->userType->user_type ?? null;

            if (!in_array($userType, [UserType::SUPER_ADMIN, UserType::ADMIN])) {
                if (in_array($userType, [UserType::SENIOR_SALES_MANAGER, UserType::SALES_MANAGER])) {
                    $assignedExecutiveIds = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($currentUser->id)->pluck('id')->toArray();
                    $userIds = array_merge($assignedExecutiveIds, [$currentUser->id]);
                    if (!in_array($lead->representative_user_id, $userIds)) {
                        return redirect()->route('admin.lead-tracking.index')->with('error', 'Unauthorized access.');
                    }
                } elseif (!in_array($userType, UserType::OPERATIONS_ROLES) && !in_array($userType, UserType::ACCOUNTS_ROLES)) {
                    if ($lead->representative_user_id != $currentUser->id) {
                        return redirect()->route('admin.lead-tracking.index')->with('error', 'Unauthorized access.');
                    }
                }
                // Operations roles and Accounts roles can see all leads
            }

            // Get all followups with payment audit trails
            $followups = LeadFollowup::where('lead_id', $id)
                ->with(['followedBy', 'paymentAuditTrail'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Get payment audit trails related to this lead via lead followups
            $followupIds = LeadFollowup::where('lead_id', $id)->pluck('id')->toArray();

            // PaymentAuditTrail uses 'lead_followup_id' FK and 'payment_status' (1=Approved,2=Rejected)
            $approvedPayments = collect();
            $rejectedPayments = collect();
            if (!empty($followupIds)) {
                $approvedPayments = PaymentAuditTrail::whereIn('lead_followup_id', $followupIds)
                    ->where('payment_status', 1) // 1 == approved
                    ->with('leadFollowup')
                    ->orderBy('created_at', 'desc')
                    ->get();

                $rejectedPayments = PaymentAuditTrail::whereIn('lead_followup_id', $followupIds)
                    ->where('payment_status', 2) // 2 == rejected
                    ->with('leadFollowup')
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            // Calculate payment summary: 'total_amount' tracked on followups is the expected charge,
            // received amount is sum of approved audit trail 'paid_amount'. Balance = expected - received
            $totalAmount = LeadFollowup::where('lead_id', $id)->sum('total_amount');
            $receivedAmount = $approvedPayments->sum('paid_amount');
            $balanceAmount = max(0, ($totalAmount - $receivedAmount));

            // Get voucher details (use first voucher if exists, otherwise get from lead)
            $voucher = $lead->vouchers->first();

            // Get travel information
            $travelInfo = $this->getTravelInformation($lead, $voucher);

            // Get vendor payment summary
            $vendorPaymentSummary = $this->getVendorPaymentSummary($lead);

            return view('admin.pages.lead-tracking.show', compact(
                'lead',
                'followups',
                'approvedPayments',
                'rejectedPayments',
                'totalAmount',
                'receivedAmount',
                'balanceAmount',
                'voucher',
                'travelInfo',
                'vendorPaymentSummary'
            ));
        } catch (\Exception $e) {
            Log::error('Error in LeadTrackingController@show: ' . $e->getMessage());
            return redirect()->route('admin.lead-tracking.index')->with('error', 'Lead not found or error occurred.');
        }
    }

    /**
     * Get travel information from voucher or lead
     */
    private function getTravelInformation($lead, $voucher)
    {
        $info = [
            'passengers' => collect(),
            'rides' => collect(),
            'service_address' => null,
            'operation_team' => null
        ];

        if ($voucher) {
            // Get from voucher
            $info['passengers'] = $voucher->passengers;
            $info['operation_team'] = $voucher->operationTeam;

            // Get service address from voucher if available
            // Assuming service_address relationship exists or get from payment details
        } else {
            // Get from lead
            $info['passengers'] = $lead->passengers;
        }

        // Get ride segments
        $info['rides'] = $lead->rideSegments;

        return $info;
    }

    /**
     * Get vendor payment summary
     */
    private function getVendorPaymentSummary($lead)
    {
        $vendorPayments = LeadVendorPayment::where('lead_id', $lead->id)
            ->with(['vendor', 'paymentDetails', 'vendorPayments'])
            ->get();

        $totalVendorAmount = 0;
        $paidVendorAmount = 0;

        foreach ($vendorPayments as $vp) {
            $totalVendorAmount += $vp->total_vendor_service_amount ?? 0;

            // Calculate paid amount from vendorPayments (actual payments made to vendor)
            if ($vp->vendorPayments && $vp->vendorPayments->count() > 0) {
                $paidVendorAmount += $vp->vendorPayments->sum('paid_amount');
            }
        }

        return [
            'vendor_payments' => $vendorPayments,
            'total' => $totalVendorAmount,
            'paid' => $paidVendorAmount,
            'balance' => $totalVendorAmount - $paidVendorAmount
        ];
    }

    /**
     * Get users for filter dropdown based on current user role
     */
    private function getUsersForFilter($currentUser, $userType)
    {
        if (in_array($userType, [UserType::SUPER_ADMIN, UserType::ADMIN])) {
            return User::where('status', 1)->get();
        }

        if (in_array($userType, [UserType::SENIOR_SALES_MANAGER, UserType::SALES_MANAGER])) {
            $assignedExecutiveIds = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($currentUser->id)->pluck('id');
            $userIds = $assignedExecutiveIds->push($currentUser->id);
            return User::whereIn('id', $userIds)->where('status', 1)->get();
        }

        if (in_array($userType, UserType::OPERATIONS_ROLES) || in_array($userType, UserType::ACCOUNTS_ROLES)) {
            $excludeTypeIds = UserType::whereIn('user_type', [UserType::SUPER_ADMIN, UserType::ADMIN])->pluck('id')->toArray();
            return User::where('status', 1)->whereNotIn('user_type_id', $excludeTypeIds)->get();
        }

        return collect([$currentUser]);
    }
}
