<?php
$now = \Carbon\Carbon::parse('2026-10-22 10:30');
return [[
    'id'=>'11111111-1111-4111-8111-111111111111', 'lead_id'=>'22222222-2222-4222-8222-222222222222',
    'client_name'=>'Aviation Customer With A Longer Name', 'contact_number'=>'9876543210', 'whatsapp_number'=>'9876543210',
    'client_email'=>'customer@example.test', 'company_name'=>'Customer company', 'gst_number'=>'Not recorded', 'invoice_id'=>'INV-101', 'created_date'=>'20 Oct 2026',
    'vendor_name'=>'Flight vendor', 'assigned_rep'=>'Operations owner', 'received_amount'=>45000, 'total_amount'=>75000, 'balance_amount'=>30000, 'status_label'=>'Partial Payment Received', 'status_text'=>'Partial Payment Received',
    'all_rides'=>collect([(object)['from_place'=>'Mumbai Juhu','to_place'=>'Pune Airport','is_tba'=>false,'from_date'=>$now,'to_date'=>$now->copy()->addHour()]]),
    'timeline'=>[['label'=>'Booked','date'=>$now->toDateTimeString(),'note'=>'By sales'],['label'=>'Confirmed','date'=>null,'note'=>'Not yet recorded'],['label'=>'Vendor assigned','date'=>$now->toDateTimeString(),'note'=>'Flight vendor'],['label'=>'In transit','date'=>null,'note'=>''],['label'=>'Completed','date'=>null,'note'=>'']],
]];
