@component('mail::message')
# Registration Link

Hello {{ $data['client_name'] ?? 'Client' }},

Please complete the passenger registration for your voucher by clicking the button below:

@component('mail::button', ['url' => $data['registration_link'] ?? '#'])
Open Registration Form
@endcomponent

If the button doesn't work, copy and paste the following link into your browser:

{{ $data['registration_link'] ?? '' }}

Thanks,
{{ config('app.name') }}
@endcomponent
