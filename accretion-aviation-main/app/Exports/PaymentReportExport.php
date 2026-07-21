<?php

namespace App\Exports;

use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;
use App\Models\Service;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
class PaymentReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
 
        $serviceDate = $this->filters['service_date'] ?? null;
        $serviceName = $this->filters['service_name'] ?? null;
        $status = $this->filters['status'] ?? null;
        $fromDate = $this->filters['from_date'] ?? null;
        $toDate = $this->filters['to_date'] ?? null;
 $exportData = collect();
         // Build query with same filtering logic as controller
         $query = LeadFollowup::with([
                'enquiry.client.country',
                'enquiry.client.city',
                'enquiry.rideSegments'
            ])->whereNotNull('received_amount')
                ->where('received_amount', '>', 0)
                ->whereIn('status', [ 3, 4]);
  
 
            // Apply service name filter
            if ($serviceName) {
                $query->where(function ($q) use ($serviceName) {
                    // Handle both array and JSON string formats
                    $q->where('service_ids', 'like', '%' . $serviceName . '%')
                        ->orWhere('service_ids', 'like', '%"' . $serviceName . '"%');
                });
            }

            // Apply status filter
            if ($this->filters['status'] && $status != '') {
                $query->where('status', intval($status));
            }

            $allPayments = $query->orderBy('created_at', 'desc')->get();

            // Group by lead_id and process
            $payments = $allPayments
                ->groupBy('lead_id')
                ->map(function ($group) use($exportData){
                    // Get the latest followup entry
                    $latest = $group->sortByDesc('created_at')->first();
                    $client = $latest->enquiry->client ?? null;
                    $ride = $latest->enquiry->rideSegments->first() ?? null;
                    $totalAmount = (float) $latest->total_amount;

                    // Calculate received amount only from approved payments (audit trail status = 1)
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

                    // Determine audit status: prefer latest audit, but if total received meets or
                    // exceeds total amount treat it as approved so the UI shows completed.
                    $auditStatus = $latestAudit ? $latestAudit->payment_status : null;
                    if ($totalAmount > 0 && $totalReceived >= $totalAmount) {
                        $auditStatus = 1; // approved
                    }

                    $exportData->push([
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
                        'audit_status' => $auditStatus
                    ]);

                    return [
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
                        'audit_status' => $auditStatus
                    ];
                })
                ->values(); // Reset keys for view

            // Filter out leads where approved payments already cover the total amount,
            // unless the caller explicitly requested Full Paid (status == 3)
            $exportData = $exportData->filter(function ($row) use ($status) {
                // If the caller asked specifically for Full Paid entries (status == 3), keep them
                if ($status !== null && (int) $status === 3) {
                    return true;
                }

                $total = isset($row['total_amount']) ? (float) $row['total_amount'] : 0;
                $received = isset($row['received_amount']) ? (float) $row['received_amount'] : 0;
                if ($total > 0 && $received >= $total) {
                    return false; // exclude fully paid/approved
                }
                return true;
            })->values();
            // Get services for filter dropdown (same as refund notes)
            $services = Service::select('id', 'service')->get(); 

        return $exportData;
  
    }


    public function headings(): array
    {
        return [ 
            'S. No',
            'Name',
            'Phone',
            'Service Date',
            'Service',
            'Received Amount',
            'Total Amount',
            'Status',
            'Payment Status' 
        ];
    }

    public function map($row): array
    {
        static $index = 0;
        $index++;
        $followupStatusLabels = [ 2 => 'Cancelled', 3 => 'Full Paid', 4 => 'Partial Paid', 5 => 'Confirmed/Complete', 6 => 'Pending', 7 => 'Rescheduled', 8 => 'Approved',  9 => 'Rejected'];
        $payment_status = 'Pending';
       
        if(isset($row['audit_status'])){
            $received = (float) $row['received_amount'];
            $total = (float) $row['total_amount'];
            if($row['audit_status'] == 1 && $received >= $total && $total > 0){
                $payment_status = 'Approved';
            }elseif($row['audit_status'] == 2){
                $payment_status = 'Rejected';
            }
        }

        $services =  'N/A';
        $serviceIds = is_string($row['service_ids']) ? json_decode($row['service_ids'], true) : $row['service_ids'];

        if ($serviceIds) {
            $services = \App\Models\Service::whereIn('id', $serviceIds)
                ->pluck('service')
                ->toArray();

            $services = Str::limit(implode(', ', $services), 50); // yaha limit laga diya
        }

        return [
            $index,
            $row['first_name'],
            $row['phone_number'],
            $row['from_date'] ? \Carbon\Carbon::parse($row['from_date'])->format('d-m-Y') : 'N/A' ,
            $services,
            '₹' . number_format($row['received_amount'], 2),
            '₹' . number_format($row['total_amount'], 2), 
            $followupStatusLabels[$row['status']] ?? 'Unknown',
            $payment_status, 
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Minimal static styles here; AfterSheet will apply full formatting
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

      public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Apply header style: bold white text on blue background
                $sheet->getStyle('A1:I1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4F81BD']
                    ],
                ]);

                // Set sensible column widths
                $sheet->getColumnDimension('A')->setWidth(8);   // S. No
                $sheet->getColumnDimension('B')->setWidth(30);  // Name
                $sheet->getColumnDimension('C')->setWidth(20);  // phone
                $sheet->getColumnDimension('D')->setWidth(20);  // service date
                $sheet->getColumnDimension('E')->setWidth(25);  // service
                $sheet->getColumnDimension('F')->setWidth(25);  // received payment
                $sheet->getColumnDimension('G')->setWidth(25);  // total payment
                $sheet->getColumnDimension('H')->setWidth(18);  // status
                $sheet->getColumnDimension('I')->setWidth(18);  // payment status
          

                // Apply number formats and alignment for data rows
                $highestRow = $sheet->getHighestRow();
                $dataRange = 'A2:I' . $highestRow;

                // Right align numeric currency columns (E, H, I, J) and center A, P
                $sheet->getStyle('A2:A' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('H2:I' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('F2:G' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT); 

                // Apply autofilter
                $sheet->setAutoFilter('A1:I' . $highestRow);

                // Freeze the header row
                $sheet->freezePane('A2');
            },
        ];
    }
}
