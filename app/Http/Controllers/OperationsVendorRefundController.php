<?php

namespace App\Http\Controllers;

use App\Models\LeadVendorPayment;
use App\Models\OperationCase;
use App\Models\VendorRefund;
use App\Services\Operations\OperationsActivityService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OperationsVendorRefundController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Operations Vendor Refund
    |--------------------------------------------------------------------------
    |
    | One row per LeadVendorPayment that still has a pending VendorRefund.
    | Money always comes from the existing LeadVendorPayment accessors
    | (total_paid, total_refunded, vendor_refund_due, cancellation_amount),
    | so the Ride Status vendor refund flow stays the single source of truth.
    |
    | Services always come from LeadVendorPayment->paymentDetails
    | (is_extra_service = 0 => service, is_extra_service = 1 => extraService).
    | Lead::leadServices is never used.
    |
    */

    private const RELATIONS = [
        'lead.client',
        'lead.rideSegments',
        'paymentDetails.service',
        'paymentDetails.extraService',
        'vendor.city',
        'vendorPayments',
        'vendorRefunds',
    ];

    /*
    |--------------------------------------------------------------------------
    | Vendor Refund List
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $rows = LeadVendorPayment::query()
            ->with(self::RELATIONS)
            ->whereHas('vendorRefunds', function ($query) {
                $this->wherePending($query);
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (LeadVendorPayment $payment) => $this->buildRow($payment))
            ->values();

        $rows = $this->applyFilters($rows, $request);

        $perPage = (int) $request->get('per_page', 25);

        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $page = max(1, (int) $request->get('page', 1));

        $vendorRefunds = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view(
            'admin.pages.operations.vendor-refunds.index',
            compact('vendorRefunds')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Right Side Drawer (AJAX JSON, same pattern as Customer Refund)
    |--------------------------------------------------------------------------
    */

    public function show(LeadVendorPayment $vendorPayment)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->buildDetails($vendorPayment),
            ]);
        } catch (\Throwable $e) {
            Log::error('Vendor refund details error', [
                'vendor_payment_id' => $vendorPayment->getKey(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load vendor refund details.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save Refund Information
    |--------------------------------------------------------------------------
    |
    | Amounts are never edited here; they are derived from the vendor
    | payment / refund records created by Ride Status.
    |
    */

    public function update(Request $request, LeadVendorPayment $vendorPayment)
    {
        $validated = $request->validate([
            'refund_type' => 'nullable|string|max:100',
            'refund_date' => 'nullable|date',
            'refund_reason' => 'nullable|string|max:5000',
            'refund_proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $vendorPayment->loadMissing(self::RELATIONS);

        $refund = $this->latestRefund($vendorPayment);

        if (!$refund) {
            return $this->fail(
                $request,
                'Vendor refund record not found. Please create the Vendor Refund from Ride Status first.',
                404
            );
        }

        DB::transaction(function () use ($request, $validated, $vendorPayment, $refund) {
            foreach (['refund_type', 'refund_date', 'refund_reason'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $refund->{$field} = $validated[$field];
                }
            }

            if ($request->hasFile('refund_proof')) {
                $oldProof = $refund->refund_proof;

                $refund->refund_proof = $request
                    ->file('refund_proof')
                    ->store('vendor-refunds', 'public');

                if ($oldProof && Storage::disk('public')->exists($oldProof)) {
                    Storage::disk('public')->delete($oldProof);
                }
            }

            $refund->save();

            $this->writeOperationsNote(
                $vendorPayment,
                sprintf(
                    "Vendor refund updated.\nVendor: %s\nRefund Received: Rs. %s\nRefund Balance: Rs. %s",
                    $this->vendorName($vendorPayment),
                    number_format((float) $vendorPayment->total_refunded, 2),
                    number_format((float) $vendorPayment->vendor_refund_due, 2)
                ),
                OperationCase::STATUS_IN_PROGRESS
            );
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Vendor refund updated successfully.',
            ]);
        }

        return back()->with('success', 'Vendor refund updated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Download / Preview  (same Blade, same as Customer Refund invoice)
    |--------------------------------------------------------------------------
    */

    public function download(LeadVendorPayment $vendorPayment)
    {
        $details = $this->buildDetails($vendorPayment);
        $is_pdf = true;

        return Pdf::loadView(
            'admin.pages.operations.vendor-refunds.vendor-refund-invoice',
            compact('details', 'is_pdf')
        )
            ->setPaper('a4', 'portrait')
            ->download('vendor-refund-' . $vendorPayment->getKey() . '.pdf');
    }

    public function preview(LeadVendorPayment $vendorPayment)
    {
        $details = $this->buildDetails($vendorPayment);
        $is_pdf = false;

        return view(
            'admin.pages.operations.vendor-refunds.vendor-refund-invoice',
            compact('details', 'is_pdf')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Download uploaded refund proof
    |--------------------------------------------------------------------------
    */

    public function downloadProof(VendorRefund $vendorRefund)
    {
        $path = $vendorRefund->refund_proof;

        abort_if(!$path, 404, 'Vendor refund proof not found.');
        abort_unless(
            Storage::disk('public')->exists($path),
            404,
            'Vendor refund proof file not found.'
        );

        return Storage::disk('public')->download($path);
    }

    /*
    |--------------------------------------------------------------------------
    | Mark Done
    |--------------------------------------------------------------------------
    |
    | Popup / UX matches Customer Refund, but completion stays financially
    | protected: only allowed when the vendor no longer owes any refund.
    |
    */

    public function markDone(Request $request, LeadVendorPayment $vendorPayment)
    {
        try {
            $vendorPayment->load(['vendorPayments', 'vendorRefunds']);

            $pending = $vendorPayment->vendorRefunds
                ->filter(fn (VendorRefund $refund) => !$this->isCompleted($refund));

            if ($pending->isEmpty()) {
                return $this->fail(
                    $request,
                    'Vendor refund record not found or already completed.',
                    404
                );
            }

            $refundDue = (float) $vendorPayment->vendor_refund_due;

            if (round($refundDue, 2) > 0) {
                return $this->fail(
                    $request,
                    'Vendor refund cannot be marked done. Rs. '
                        . number_format($refundDue, 2)
                        . ' is still pending.',
                    422
                );
            }

            DB::transaction(function () use ($vendorPayment, $pending) {
                foreach ($pending as $refund) {
                    $refund->status = VendorRefund::STATUS_COMPLETED;
                    $refund->completed_at = now();
                    $refund->completed_by = auth()->id();
                    $refund->save();
                }

                $this->writeOperationsNote(
                    $vendorPayment,
                    sprintf(
                        "Vendor Refund Completed.\nVendor: %s\nTotal Refund Received: Rs. %s\nRefund Balance: Rs. 0.00",
                        $this->vendorName($vendorPayment),
                        number_format((float) $vendorPayment->total_refunded, 2)
                    ),
                    OperationCase::STATUS_COMPLETED
                );
            });
        } catch (\Throwable $e) {
            Log::error('Vendor refund mark done failed', [
                'vendor_payment_id' => $vendorPayment->getKey(),
                'error' => $e->getMessage(),
            ]);

            return $this->fail($request, 'Unable to mark vendor refund as done.', 500);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Vendor refund marked as done successfully.',
                'vendor_payment_id' => $vendorPayment->getKey(),
            ]);
        }

        return redirect()
            ->route('admin.operations.vendor-refunds.index')
            ->with('success', 'Vendor refund marked as completed.');
    }

    /*
    |--------------------------------------------------------------------------
    | Table Row
    |--------------------------------------------------------------------------
    */

    private function buildRow(LeadVendorPayment $payment): object
    {
        $lead = $payment->lead;
        $refund = $this->latestRefund($payment);
        $lines = $this->serviceLines($payment);

        return (object) [
            'id' => $payment->getKey(),

            'vendor_name' => $this->vendorName($payment),
            'vendor_phone' => $payment->vendor?->contact_number ?: '-',
            'customer_name' => $lead?->client?->name ?: '-',
            'customer_phone' => $lead?->client?->contact_number ?: '-',

            'service_name' => $this->serviceNames($lines) ?: '-',
            'service_date' => $this->serviceDate($lead),

            'original_amount' => (float) ($payment->total_vendor_service_amount ?? 0),
            'cancellation_amount' => $payment->cancellation_amount,
            'refund_received' => (float) $payment->total_refunded,
            'refund_due' => (float) $payment->vendor_refund_due,

            'refund_date' => $refund?->refund_date,
        ];
    }

    private function applyFilters($rows, Request $request)
    {
        if ($request->filled('from_service_date')) {
            $from = Carbon::parse($request->from_service_date)->startOfDay();

            $rows = $rows->filter(
                fn ($row) => $row->service_date && $row->service_date->gte($from)
            );
        }

        if ($request->filled('to_service_date')) {
            $to = Carbon::parse($request->to_service_date)->endOfDay();

            $rows = $rows->filter(
                fn ($row) => $row->service_date && $row->service_date->lte($to)
            );
        }

        foreach (
            [
                'customer' => 'customer_name',
                'vendor' => 'vendor_name',
                'service' => 'service_name',
            ] as $input => $property
        ) {
            if ($request->filled($input)) {
                $needle = mb_strtolower(trim((string) $request->get($input)));

                $rows = $rows->filter(
                    fn ($row) => str_contains(mb_strtolower((string) $row->{$property}), $needle)
                );
            }
        }

        if ($request->filled('phone')) {
            $needle = preg_replace('/\D+/', '', (string) $request->phone);

            $rows = $rows->filter(
                fn ($row) => $needle !== ''
                    && str_contains(preg_replace('/\D+/', '', (string) $row->vendor_phone), $needle)
            );
        }

        return $rows->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Drawer / Preview / PDF data
    |--------------------------------------------------------------------------
    */

    private function buildDetails(LeadVendorPayment $payment): array
    {
        $payment->loadMissing(self::RELATIONS);

        $lead = $payment->lead;
        $client = $lead?->client;
        $vendor = $payment->vendor;
        $refund = $this->latestRefund($payment);
        $lines = $this->serviceLines($payment);

        $rides = collect($lead?->rideSegments ?? [])
            ->sortBy('from_date')
            ->map(fn ($ride) => [
                'from_date' => $ride->from_date ? Carbon::parse($ride->from_date)->format('d-m-Y H:i') : '-',
                'to_date' => $ride->to_date ? Carbon::parse($ride->to_date)->format('d-m-Y H:i') : '-',
                'from_place' => $ride->from_place ?: '-',
                'to_place' => $ride->to_place ?: '-',
            ])
            ->values()
            ->all();

        $paymentHistory = collect($payment->vendorPayments)
            ->sortByDesc(fn ($item) => $item->paid_date ?? $item->created_at)
            ->map(fn ($item) => [
                'id' => $item->getKey(),
                'amount' => (float) ($item->paid_amount ?? 0),
                'payment_method' => $item->payment_method ?: '-',
                'paid_date' => $item->paid_date ? Carbon::parse($item->paid_date)->format('d M Y') : '-',
                'narration' => $item->narration ?: '',
                'receipt' => $item->receipt ?: null,
            ])
            ->values()
            ->all();

        $refundHistory = collect($payment->vendorRefunds)
            ->sortByDesc(fn ($item) => $item->refund_date ?? $item->created_at)
            ->map(fn ($item) => [
                'id' => $item->getKey(),
                'amount' => (float) ($item->refund_amount ?? 0),
                'refund_type' => $item->refund_type ?: '-',
                'refund_date' => $item->refund_date ? Carbon::parse($item->refund_date)->format('d M Y') : '-',
                'refund_reason' => $item->refund_reason ?: '',
                'has_proof' => !empty($item->refund_proof),
                'completed' => $this->isCompleted($item),
            ])
            ->values()
            ->all();

        $received = (float) $payment->total_refunded;
        $due = (float) $payment->vendor_refund_due;
        $expected = $received + $due;
        $completed = $payment->vendorRefunds->isNotEmpty()
            && $payment->vendorRefunds->every(fn ($item) => $this->isCompleted($item));

        if ($completed || $due <= 0) {
            $progress = 100;
        } else {
            $progress = (int) min(100, round(($received / $expected) * 100));
        }

        $cancellation = $payment->cancellation_amount;

        return [
            'id' => $payment->getKey(),
            'vendor_refund_id' => $refund?->getKey(),
            'is_completed' => $completed,

            'vendor' => [
                'name' => $vendor?->name ?: '-',
                'email' => $vendor?->email ?: '-',
                'contact_number' => $vendor?->contact_number ?: '-',
                'city' => $vendor?->city?->name ?: '-',
                'address' => $vendor?->address ?: '-',
                'bank_details' => $vendor?->bank_details ?: '-',
            ],

            'customer' => [
                'name' => $client?->name ?: '-',
                'phone' => $client?->contact_number ?: ($client?->alternate_number ?: '-'),
            ],

            'rides' => $rides,
            'services' => $lines['services'],
            'extra_services' => $lines['extra_services'],
            'service_names' => $this->serviceNames($lines),
            'service_date' => ($date = $this->serviceDate($lead)) ? $date->format('d M Y') : '-',

            'original_vendor_amount' => (float) ($payment->total_vendor_service_amount ?? 0),
            'cancellation_amount' => $cancellation !== null ? (float) $cancellation : null,
            'gross_paid' => (float) $payment->total_paid,
            'refund_received' => $received,
            'net_paid' => (float) $payment->net_paid_to_vendor,
            'refund_due' => $due,
            'refund_progress' => $progress,

            'refund' => $refund
                ? [
                    'id' => $refund->getKey(),
                    'refund_amount' => (float) ($refund->refund_amount ?? 0),
                    'refund_type' => $refund->refund_type ?: '',
                    'refund_date' => $refund->refund_date ? Carbon::parse($refund->refund_date)->format('Y-m-d') : '',
                    'refund_reason' => $refund->refund_reason ?: '',
                    'refund_proof' => $refund->refund_proof ?: null,
                    'status' => (int) ($refund->status ?? VendorRefund::STATUS_PENDING),
                ]
                : null,

            'payment_history' => $paymentHistory,
            'refund_history' => $refundHistory,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Every paymentDetails row is processed: it can be a normal Service or
     * an ExtraService, distinguished by is_extra_service.
     */
    private function serviceLines(LeadVendorPayment $payment): array
    {
        $services = [];
        $extraServices = [];

        foreach ($payment->paymentDetails as $detail) {
            $amount = (float) ($detail->vendor_service_amount ?? 0);

            if ((bool) $detail->is_extra_service) {
                if ($detail->extraService) {
                    $extraServices[] = [
                        'name' => $detail->extraService->extra_service ?: '-',
                        'vendor_amount' => $amount,
                    ];
                }

                continue;
            }

            if ($detail->service) {
                $services[] = [
                    'name' => $detail->service->service ?: '-',
                    'vendor_amount' => $amount,
                ];
            }
        }

        return [
            'services' => $services,
            'extra_services' => $extraServices,
        ];
    }

    private function serviceNames(array $lines): string
    {
        return collect($lines['services'])
            ->merge($lines['extra_services'])
            ->pluck('name')
            ->filter(fn ($name) => $name && $name !== '-')
            ->unique()
            ->implode(', ');
    }

    /**
     * leads has no departure_date column; the service date is the first
     * ride segment's start date.
     */
    private function serviceDate($lead): ?Carbon
    {
        $date = collect($lead?->rideSegments ?? [])->min('from_date');

        return $date ? Carbon::parse($date) : null;
    }

    private function vendorName(LeadVendorPayment $payment): string
    {
        return $payment->vendor?->name ?: '-';
    }

    private function latestRefund(LeadVendorPayment $payment): ?VendorRefund
    {
        return $payment->vendorRefunds
            ->sortByDesc(fn ($refund) => $refund->refund_date ?? $refund->created_at)
            ->first();
    }

    private function isCompleted(VendorRefund $refund): bool
    {
        return (int) $refund->status === VendorRefund::STATUS_COMPLETED;
    }

    private function wherePending($query): void
    {
        $query->where(function ($statusQuery) {
            $statusQuery
                ->whereNull('status')
                ->orWhere('status', '!=', VendorRefund::STATUS_COMPLETED);
        });
    }

    private function fail(Request $request, string $message, int $status)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        }

        return back()->with('error', $message);
    }

    /*
    |--------------------------------------------------------------------------
    | Operations Lead History Note
    |--------------------------------------------------------------------------
    |
    | Uses the Operations follow-up flow, so Sales KPI is untouched.
    |
    */

    private function writeOperationsNote(
        LeadVendorPayment $payment,
        string $note,
        string $operationsStatus
    ): void {
        $lead = $payment->lead;

        if (!$lead) {
            return;
        }

        $case = OperationCase::query()
            ->where('lead_id', $lead->getKey())
            ->where('type', OperationCase::TYPE_REFUND)
            ->whereNull('completed_at')
            ->latest('created_at')
            ->first();

        /*
         * The financial update must not fail merely because the
         * Operations case does not exist.
         */
        if (!$case) {
            return;
        }

        app(OperationsActivityService::class)->addFollowup(
            $case,
            auth()->user(),
            $note,
            $operationsStatus,
            null
        );
    }
}
