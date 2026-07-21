<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Your Booking Confirmation</title>
</head>

<body style="font-family: Arial, sans-serif; color:#333; font-size:14px; line-height:1.6;">
    <p><strong>Dear {{ $data['client_name'] ?? 'Customer' }},</strong></p>

    <p>
        We are pleased to confirm your reservation for
        <strong>{{ $data['service'] ?? 'Service' }}</strong>
        on <strong>{{ $data['pickup_date'] ?? 'N/A' }}</strong> at
        <strong>{{ $data['pickup_time'] ?? 'N/A' }}</strong>
        for <strong>{{ $data['product'] ?? 'N/A' }}</strong>.
        The location for your ride is
        <strong>{{ $data['location'] ?? 'N/A' }}</strong>.
    </p>

    <p>
        <strong>Please Contact :</strong> {{ $data['contact_person'] ?? 'N/A' }} at
        <a href="tel:{{ $data['contact_number'] ?? '' }}">{{ $data['contact_number'] ?? 'N/A' }}</a>
        for any assistance.
    </p>

    <p>
        <strong>Google Map Link:</strong>
        @if (!empty($data['map_link']) && $data['map_link'] !== 'N/A')
            <a href="{{ $data['map_link'] }}" target="_blank">{{ $data['map_link'] }}</a>
        @else
            N/A
        @endif
    </p>
    @if (!empty($data['naration']) && $data['naration'] !== 'N/A')
        <p><strong>Special Instructions:</strong> {{ $data['naration'] ?? 'N/A' }}</p>
    @endif
    <p>
        Please find attached booking voucher which can be showed electronically.
        <em>Please note that <strong>{{ $data['service'] ?? 'Service' }}</strong> is subject to weather conditions and may be rescheduled if necessary.</em>
    </p>

    <p>
        Thank you for choosing our services. We look forward to giving you a memorable experience.
    </p>

    <br><br>
    <strong>
        <span style="font-size:10pt; color:#767171;">Warm Regards,</span><br />
        <span style="font-size:10pt; color:red;">Accretion</span>
        <span style="font-size:10pt; color:blue;">Aviation</span><br />
        <span style="font-size:10pt; color:#767171;">Phone : +91-9575340786</span><br />
        <span style="font-size:10pt; color:#767171;">
            Email : <a href="mailto:ops@accretionaviation.com">ops@accretionaviation.com</a>
        </span><br />
        <span style="font-size:10pt; color:#666666;">
            Website: <a href="http://www.accretionaviation.com" target="_blank" style="color:blue;">
                www.accretionaviation.com
            </a>
        </span>
    </strong>
</body>

</html>
