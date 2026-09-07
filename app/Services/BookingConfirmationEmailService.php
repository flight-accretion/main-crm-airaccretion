<?php

namespace App\Services;

use App\Mail\BookingConfirmationMail;
use App\Models\BookingEmailTemplate;
use App\Models\ExtraService;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BookingConfirmationEmailService
{
    public function sendForLead(
        Lead $lead,
        ?User $actor = null
    ): array {
        $lead->loadMissing([
            'client',
            'representative',
            'rideSegments',
        ]);

        $customerEmail = trim((string) optional($lead->client)->email);

        if (
            $customerEmail === ''
            || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)
        ) {
            return [
                'success' => false,
                'message' => 'Customer email is not available or invalid.',
            ];
        }

        try {
            $registration = $this->registrationLink($lead);
            $template = BookingEmailTemplate::active();
            $agent = $lead->representative ?: $actor;
            $variables = $this->variables(
                $lead->fresh(['client', 'representative', 'rideSegments']),
                $agent,
                $registration['display_link']
            );

            $subject = $this->renderTemplate(
                $template->subject ?: BookingEmailTemplate::defaultSubject(),
                $variables
            );
            $body = $this->renderTemplate(
                $template->body ?: BookingEmailTemplate::defaultBody(),
                $variables
            );

            Mail::to($customerEmail)->send(
                new BookingConfirmationMail(
                    $subject,
                    $body,
                    optional($agent)->email,
                    optional($agent)->name
                )
            );

            $this->createFollowupNote(
                $lead,
                $actor ?: $agent,
                $customerEmail
            );

            return [
                'success' => true,
                'message' => 'Booking confirmation email sent successfully.',
                'registration_link' => $registration['long_link'],
                'short_link' => $registration['short_link'],
            ];
        } catch (\Throwable $exception) {
            Log::error('Booking confirmation email failed', [
                'lead_id' => $lead->id,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Booking confirmation email could not be sent.',
            ];
        }
    }

    private function registrationLink(Lead $lead): array
    {
        $token = $lead->generatePassengerRegistrationToken();
        $passenger = $lead->passengers()
            ->whereNull('voucher_id')
            ->first();

        if ($passenger && empty($passenger->registration_slug)) {
            $passenger->generateRegistrationSlug();
        }

        $longLink = route('lead.register.form', [
            'lead' => $lead->id,
            'token' => $token,
        ]);
        $shortLink = $passenger
            ? $passenger->getShortRegistrationLink()
            : null;

        return [
            'long_link' => $longLink,
            'short_link' => $shortLink,
            'display_link' => $shortLink ?: $longLink,
        ];
    }

    private function variables(
        Lead $lead,
        ?User $agent,
        string $registrationLink
    ): array {
        $amountFollowup = $this->amountFollowup($lead);
        $serviceIds = $this->selectedServiceIds($lead, $amountFollowup);
        $extraServiceIds = $this->selectedExtraServiceIds($amountFollowup);
        $services = $this->services($serviceIds);
        $extraServices = $this->extraServices($extraServiceIds);
        $products = $this->products($lead, $services);
        $firstRide = $this->firstRideSegment($lead);
        $totalAmount = $this->totalAmount(
            $amountFollowup,
            $services,
            $extraServices
        );
        $advanceAmount = (float) ($amountFollowup->received_amount ?? 0);

        if ($advanceAmount <= 0 && $totalAmount !== null) {
            $advanceAmount = (float) $totalAmount;
        }

        $balanceAmount = $totalAmount !== null
            ? max(0, (float) $totalAmount - $advanceAmount)
            : null;

        return [
            'customer_name' => $this->value(optional($lead->client)->name),
            'customer_email' => $this->value(optional($lead->client)->email),
            'customer_phone' => $this->value(
                optional($lead->client)->contact_number
            ),
            'service_name' => $this->names($services, 'service'),
            'product_name' => $this->names($products, 'product'),
            'service_date' => $this->serviceDate($firstRide),
            'duration' => $this->duration($services, $firstRide),
            'timing' => $this->timing($firstRide),
            'passengers' => $this->value($lead->number_of_passengers),
            'total_amount' => $this->money($totalAmount),
            'advance_amount' => $this->money($advanceAmount),
            'balance_amount' => $this->money($balanceAmount),
            'balance_due_by' => $this->balanceDueBy($firstRide),
            'registration_link' => $registrationLink,
            'product_service_notes' => $this->productServiceNotes(
                $products,
                $services
            ),
            'payment_link' => 'https://www.accretionaviation.com/pay',
            'terms_link' =>
                'https://www.accretionaviation.com/terms&condition.php',
            'agent_name' => $this->value(optional($agent)->name),
            'agent_email' => $this->value(optional($agent)->email),
            'agent_phone' => $this->value(optional($agent)->contact_number),
            'lead_code' => $this->value($lead->crm_lead_code ?: $lead->id),
        ];
    }

    private function renderTemplate(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace(
                [
                    '{{' . $key . '}}',
                    '{{ ' . $key . ' }}',
                ],
                (string) $value,
                $template
            );
        }

        return $template;
    }

    private function amountFollowup(Lead $lead): ?LeadFollowup
    {
        return $lead->leadFollowups()
            ->whereNotNull('total_amount')
            ->latest('created_at')
            ->first()
            ?: $lead->leadFollowups()
                ->latest('created_at')
                ->first();
    }

    private function selectedServiceIds(
        Lead $lead,
        ?LeadFollowup $followup
    ): array {
        $followupIds = $this->ids(optional($followup)->service_ids);

        return $followupIds ?: $this->ids($lead->service_ids);
    }

    private function selectedExtraServiceIds(?LeadFollowup $followup): array
    {
        return $this->ids(optional($followup)->extra_service_ids);
    }

    private function ids($value): array
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_string($value)) {
            $decoded = json_decode(stripslashes($value), true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn($id) => is_string($id) || is_numeric($id))
            ->map(fn($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function services(array $serviceIds): Collection
    {
        if (empty($serviceIds)) {
            return collect();
        }

        return Service::query()
            ->whereIn('id', $serviceIds)
            ->get();
    }

    private function extraServices(array $extraServiceIds): Collection
    {
        if (empty($extraServiceIds)) {
            return collect();
        }

        return ExtraService::query()
            ->whereIn('id', $extraServiceIds)
            ->get();
    }

    private function products(Lead $lead, Collection $services): Collection
    {
        $productIds = collect($this->ids($lead->product_ids));

        $services->each(function (Service $service) use ($productIds) {
            foreach ($this->ids($service->product_ids) as $productId) {
                $productIds->push($productId);
            }
        });

        $productIds = $productIds->unique()->values()->all();

        if (empty($productIds)) {
            return collect();
        }

        return Product::query()
            ->whereIn('id', $productIds)
            ->get();
    }

    private function names(Collection $records, string $field): string
    {
        $names = $records
            ->pluck($field)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return empty($names) ? 'N/A' : implode(', ', $names);
    }

    private function firstRideSegment(Lead $lead)
    {
        return $lead->rideSegments
            ->filter(fn($ride) => !empty($ride->from_date))
            ->sortBy('from_date')
            ->first();
    }

    private function serviceDate($ride): string
    {
        if (!$ride || empty($ride->from_date)) {
            return 'TBA';
        }

        return Carbon::parse($ride->from_date)->format('l, jS F Y');
    }

    private function timing($ride): string
    {
        if (!$ride || empty($ride->from_date)) {
            return 'TBA';
        }

        $date = Carbon::parse($ride->from_date);

        if ($date->format('H:i:s') === '00:00:00') {
            return 'TBA';
        }

        return $date->format('g:i A') . ' IST';
    }

    private function duration(Collection $services, $ride): string
    {
        foreach ($services as $service) {
            if (
                preg_match(
                    '/\b(\d+\s*(?:minutes?|mins?|min|hours?|hrs?|hr))\b/i',
                    (string) $service->service,
                    $match
                )
            ) {
                return (string) Str::of($match[1])->lower()->title();
            }
        }

        if ($ride && !empty($ride->from_date) && !empty($ride->to_date)) {
            $minutes = Carbon::parse($ride->from_date)
                ->diffInMinutes(Carbon::parse($ride->to_date), false);

            if ($minutes > 0) {
                return $minutes . ' Minutes';
            }
        }

        return 'TBA';
    }

    private function balanceDueBy($ride): string
    {
        if (!$ride || empty($ride->from_date)) {
            return 'TBA';
        }

        return Carbon::parse($ride->from_date)
            ->subDays(3)
            ->format('l, jS F Y');
    }

    private function totalAmount(
        ?LeadFollowup $followup,
        Collection $services,
        Collection $extraServices
    ): ?float {
        if ($followup && $followup->total_amount !== null) {
            return (float) $followup->total_amount;
        }

        $serviceAmount = $services->sum('service_amount');
        $extraServiceAmount = $extraServices->sum('extra_service_amount');
        $total = (float) $serviceAmount + (float) $extraServiceAmount;

        return $total > 0 ? $total : null;
    }

    private function money($amount): string
    {
        if ($amount === null || $amount === '') {
            return 'TBA';
        }

        return $this->currencySymbol() . number_format((float) $amount, 2);
    }

    private function currencySymbol(): string
    {
        $symbol = config('settings.currency_symbol');

        return is_string($symbol) && trim($symbol) !== ''
            ? trim($symbol)
            : '₹';
    }

    private function productServiceNotes(
        Collection $products,
        Collection $services
    ): string {
        $lines = [];

        if (Schema::hasColumn('products', 'booking_email_note')) {
            foreach ($products as $product) {
                $note = trim((string) $product->booking_email_note);

                if ($note !== '') {
                    $lines[] = '- ' . $product->product . ': ' . $note;
                }
            }
        }

        if (Schema::hasColumn('services', 'booking_email_note')) {
            foreach ($services as $service) {
                $note = trim((string) $service->booking_email_note);

                if ($note !== '') {
                    $lines[] = '- ' . $service->service . ': ' . $note;
                }
            }
        }

        $lines = array_values(array_unique($lines));

        return empty($lines)
            ? ''
            : 'Product/Service Notes:' . PHP_EOL . implode(PHP_EOL, $lines);
    }

    private function createFollowupNote(
        Lead $lead,
        ?User $user,
        string $customerEmail
    ): void {
        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => now(),
            'followup_note' =>
                'Booking confirmation email sent to '
                . $customerEmail
                . ' with passenger registration link.',
            'status' => 1,
            'followed_by' => optional($user)->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function value($value): string
    {
        $value = trim((string) $value);

        return $value === '' ? 'N/A' : $value;
    }
}
