<?php

namespace App\Services;

use App\Models\Client;
use App\Models\IvrCallLog;
use App\Models\Lead;
use App\Models\LeadAllocationLog;
use App\Models\LeadAllocationSetting;
// use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IvrLeadService
{
    public function __construct(
        private DtmfAllocationService $allocationService,
        private LeadAllocationService $leadAllocationService,
        private IvrFollowupService $followupService,
        private ActiveLeadService $activeLeadService,
        private LeadSourceDataHydrationService $sourceDataHydrator
    ) {
    }

    public function processCallLog(IvrCallLog $callLog): array
    {
        return DB::transaction(function () use ($callLog) {

        $callLog = IvrCallLog::where('id', $callLog->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (!empty($callLog->lead_id)) {
            return [
                'status' => 'already_processed',
                'lead_id' => $callLog->lead_id
            ];
        }

        $existingLead = $this->activeLeadService->findByPhone(
            $callLog->normalized_phone
        );

        if ($existingLead) {

            $callLog->lead_id = $existingLead->id;
            $callLog->processing_status = 'repeat_lead';
            $callLog->processing_message =
                'Existing active lead found for same phone. Lead retained with existing representative.';

            $callLog->save();

            if (!empty($existingLead->representative_user_id)) {

                $this->followupService->createIfNeeded(
                    $existingLead,
                    $callLog,
                    true
                );

            } else {

                $this->leadAllocationService->queueLead(
                    $existingLead,
                    'ivr_repeat_call'
                );
            }

            return [
                'status' => 'repeat_lead',
                'lead_id' => $existingLead->id
            ];
        }

            $client = $this->findClientByPhone($callLog->normalized_phone);
            if (!$client) {
                $client = Client::create([
                    'id' => (string) Str::uuid(),
                    'name' => 'IVR Lead ' . ($callLog->normalized_phone ?: $callLog->cli ?: 'Unknown'),
                    'email' => null,
                    'contact_number' => $callLog->normalized_phone ?: $callLog->cli,
                    'alternate_number' => null,
                    'status' => 1,
                    'created_by' => null,
                ]);
            }

            $representative = null;
            if ($this->followupService->isSuccessfulStatus($callLog->dial_status)) {
                $representative = $this->allocationService->mappedUserForSuccessfulAgent(
                    $callLog->agent_number,
                    $callLog->agent_name
                );
            }

            if (!$representative && $this->isOfficeOpen()) {
                $representative = $this->allocationService->pickAvailableUser($callLog->ivr_call_type_id, $callLog->raw_dtmf);
            }

            $lead = Lead::create([
                'id' => (string) Str::uuid(),
                'client_id' => $client->id,
                'representative_user_id' => $representative ? $representative->id : null,
                'service_ids' => null,
                'product_ids' => null,
                'number_of_passengers' => 1,
                'description' => 'Lead received automatically from VI CPaaS IVR.',
                'occasion' => null,
            ]);

            $this->sourceDataHydrator->hydrate(
                $lead,
                []
            );

            $callLog->lead_id = $lead->id;
            $callLog->processing_status = $representative ? 'lead_created_assigned' : 'lead_created_queued';
            $callLog->processing_message = $representative
                ? 'New IVR lead created and assigned.'
                : 'New IVR lead created and queued for assignment.';
            $callLog->save();

            if ($representative) {
                LeadAllocationLog::create([
                    'lead_id' => $lead->id,
                    'salesperson_id' => $representative->id,
                    'action' => 'ivr_assigned',
                    'result' => 'success',
                    'details' => 'Assigned by dynamic IVR routing configuration.',
                ]);
                $this->followupService->createIfNeeded($lead, $callLog, false);
            } else {
                $this->leadAllocationService->queueLead($lead, 'ivr_new_lead');

                $this->followupService->createIfNeeded(
                    $lead,
                    $callLog,
                    false,
                    true
                );
            }

            return ['status' => $representative ? 'created_assigned' : 'created_queued', 'lead_id' => $lead->id];
        });
    }

    // private function findRecentLeadByPhone(?string $phone, $callStart): ?Lead
    // {
    //     if (empty($phone)) {
    //         return null;
    //     }

    //     $anchor = $callStart ? Carbon::parse($callStart) : now();
    //     $from = $anchor->copy()->subDay()->startOfDay();
    //     $to = $anchor->copy()->endOfDay();
    //     $expr = $this->digitsSqlExpression('clients.contact_number');

    //     return Lead::query()
    //         ->join('clients', 'clients.id', '=', 'leads.client_id')
    //         ->whereRaw("{$expr} LIKE ?", ['%' . $phone])
    //         ->whereBetween('leads.created_at', [$from, $to])
    //         ->orderByDesc('leads.created_at')
    //         ->select('leads.*')
    //         ->first();
    // }

    private function findClientByPhone(?string $phone): ?Client
    {
        if (empty($phone)) {
            return null;
        }

        $expr = $this->digitsSqlExpression('contact_number');
        return Client::whereRaw("{$expr} LIKE ?", ['%' . $phone])->first();
    }

    private function digitsSqlExpression(string $column): string
    {
        if (config('database.default') === 'pgsql') {
            return "regexp_replace({$column}, '[^0-9]', '', 'g')";
        }

        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, '+', ''), '-', ''), ' ', ''), '(', ''), ')', '')";
    }

    private function isOfficeOpen(): bool
    {
        try {
            $settings = LeadAllocationSetting::getActiveSettings();
            return $this->leadAllocationService->isOfficeOpenForDebug($settings, now());
        } catch (\Throwable $e) {
            Log::error('IVR office-hours check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
