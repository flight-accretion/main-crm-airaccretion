@component('mail::message')

Dear {{ $data['name'] ?? 'Customer' }},

Your ride is scheduled for today at {{ $data['time'] ?? '' }}. Please arrive at {{ $data['location'] ?? '' }} with the original ID proof of all passengers.

Late arrival will be considered a no-show with a full penalty. Enjoy your ride! Parking is your responsibility. Thank you!

This is an automated message. For any assistance, please call +91-9575340786.

Thanks,
Accretion Aviation
@endcomponent
