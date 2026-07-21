@component('mail::message')

Dear {{ $data['name'] ?? 'Customer' }},

Your {{ $data['service'] ?? 'ride' }} is scheduled for today at {{ $data['time'] ?? '' }}. Please arrive at the {{ $data['location'] ?? '' }} with the original id proof of all passengers. {{ $data['extra'] ?? '' }}

Late arrival will be considered a no-show with a full penalty. Enjoy your ride! Parking is your responsibility. Thank you!

This is an automated message for any assistance please call +91-9575340786.

Thanks,
{{ config('app.name') }}
@endcomponent
