<?php

namespace App\Services;

class BookingEmailTemplatePreview
{
    public static function samples(): array
    {
        return [
            'customer_name'=>'Ankit Goel', 'customer_email'=>'customer@example.test', 'customer_phone'=>'+91 90000 00000',
            'service_name'=>'Private Plane Ride - Jaipur', 'product_name'=>'Private Plane Ride', 'service_date'=>'16 Dec 2026',
            'duration'=>'1h 15m', 'time'=>'10:00 AM', 'timing'=>'10:00 AM', 'passengers'=>'2',
            'total_amount'=>'INR 48,000', 'advance_amount'=>'INR 24,000', 'balance_amount'=>'INR 24,000', 'balance_due_by'=>'15 Dec 2026',
            'registration_link'=>'https://example.test/passenger-registration', 'product_service_notes'=>'Please carry an original government-issued photo ID.',
            'payment_link'=>'https://example.test/payment', 'bank_account_name'=>'Sample account', 'bank_name'=>'Sample bank',
            'bank_account_number'=>'XXXX XXXX 0000', 'bank_ifsc'=>'SAMPLE00000', 'bank_branch'=>'Sample branch',
            'terms_link'=>'https://example.test/terms', 'agent_name'=>'Sample booking executive', 'agent_email'=>'agent@example.test',
            'agent_phone'=>'90000 00001', 'lead_code'=>'SAMPLE-1042', 'manager_name'=>'Sample manager',
            'manager_email'=>'manager@example.test', 'manager_phone'=>'90000 00002',
        ];
    }

    public static function render(string $text): string
    {
        foreach (self::samples() as $key => $value) {
            $text = str_replace(['{{'.$key.'}}', '{{ '.$key.' }}'], $value, $text);
        }
        return $text;
    }
}
