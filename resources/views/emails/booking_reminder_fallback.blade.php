@component('mail::message')

Dear {{ $data['name'] ?? 'Customer' }},

Your booking reminder for your upcoming ride. Your ride is scheduled for {{ $data['ride_time'] ?? 'TBA' }}.

**Route:** {{ $data['from_place'] ?? 'TBA' }} to {{ $data['to_place'] ?? 'TBA' }}

**Days until ride:** {{ $data['days_before'] ?? 'N/A' }}

@if (!empty($data['extra_services']))
**Extra Services Available:**
@foreach ($data['extra_services'] as $service)
- {{ $service }}
@endforeach

@endif

**Your Sales Representative:**
{{ $data['sales_name'] ?? 'Sales Representative' }}
Phone: {{ $data['sales_phone'] ?? 'N/A' }}

**Your Manager:**
{{ $data['manager_name'] ?? 'Manager' }}
Phone: {{ $data['manager_phone'] ?? 'N/A' }}

Please arrive on time with all required documents. Enjoy your ride!

This is an automated message. For any assistance, please call +91-9575340786.

Thanks,
{{ config('app.name') }}
@endcomponent
