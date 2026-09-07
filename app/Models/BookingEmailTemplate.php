<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BookingEmailTemplate extends Model
{
    protected $fillable = [
        'id',
        'subject',
        'body',
        'updated_by',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public static function active(): self
    {
        $defaults = [
            'subject' => self::defaultSubject(),
            'body' => self::defaultBody(),
        ];

        if (!Schema::hasTable('booking_email_templates')) {
            return new self($defaults);
        }

        return self::query()->firstOrCreate([], $defaults);
    }

    public static function defaultSubject(): string
    {
        return 'Booking Confirmation - Accretion Aviation | {{service_name}} on {{service_date}}';
    }

    public static function defaultBody(): string
    {
        return implode(PHP_EOL, [
            'Dear {{customer_name}},',
            '',
            'Thank you for choosing Accretion Aviation. We are pleased to share your service details and the next steps to confirm your booking.',
            '',
            'SERVICE DETAILS',
            'Service Name: {{service_name}}',
            'Date of Service: {{service_date}}',
            'Duration: {{duration}}',
            'Timing: {{timing}}',
            'Passengers: {{passengers}}',
            '',
            'PAYMENT BREAKDOWN',
            'Total Service Cost: {{total_amount}}',
            'Advance Payment Due Now: {{advance_amount}}',
            'Balance Amount: {{balance_amount}}',
            'Balance Due By: {{balance_due_by}}',
            '',
            'NEXT STEPS TO CONFIRM YOUR BOOKING',
            'Step 1 - Make the Advance Payment',
            'Complete your advance payment securely via the link below:',
            '{{payment_link}}',
            '',
            'Step 2 - Send Payment Screenshot',
            'Once the payment is done, share us the payment screenshot for verification.',
            '',
            'Step 3 - Fill Passenger Registration Form',
            'After we confirm your payment, please fill out the passenger registration link below with the full names and government-issued ID card details of all passengers. This is required for voucher generation.',
            '{{registration_link}}',
            '',
            'IMPORTANT NOTES',
            '- Your slot is not held till the advance payment.',
            '- Timing is subject to weather and air traffic clearance; final timing will be shared 24-48 hours before your ride.',
            '- Please carry the original government-issued photo ID on the day of service for verification.',
            '- Review our Terms & Conditions before proceeding:',
            '{{terms_link}}',
            '',
            '{{product_service_notes}}',
            '',
            'If you have any questions, please reach out to us.',
            'We look forward to giving you an unforgettable experience in the skies!',
            '',
            'Warm regards,',
            '{{agent_name}}',
            'Sales & Reservations',
            'Accretion Aviation',
            '{{agent_email}}',
            'www.accretionaviation.com',
            '+91 {{agent_phone}}',
        ]);
    }

    public static function variables(): array
    {
        return [
            'customer_name',
            'customer_email',
            'customer_phone',
            'service_name',
            'product_name',
            'service_date',
            'duration',
            'timing',
            'passengers',
            'total_amount',
            'advance_amount',
            'balance_amount',
            'balance_due_by',
            'registration_link',
            'product_service_notes',
            'payment_link',
            'terms_link',
            'agent_name',
            'agent_email',
            'agent_phone',
            'lead_code',
        ];
    }
}
