<?php

namespace App\Services;

use App\Models\IvrCallLog;
use App\Models\IvrCallType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class IvrImportService
{
    public function __construct(private IvrLeadService $leadService)
    {
    }

    public function import(IvrCallType $callType, array $records): array
    {
        $result = ['fetched' => count($records), 'created' => 0, 'duplicate_calls' => 0, 'repeat_leads' => 0, 'errors' => 0];
        $groups = [];

        foreach ($records as $record) {
            if (!is_array($record)) {
                $result['errors']++;
                continue;
            }
            $normalized = $this->normalizeKeys($record);
            $callId = trim((string) ($normalized['CALLID'] ?? ''));
            if ($callId === '') {
                $result['errors']++;
                Log::warning('VI record skipped because CALL ID is missing.', ['record' => $record]);
                continue;
            }
            $groups[$callId][] = $record;
        }

        foreach ($groups as $callId => $attempts) {
            try {
                if (IvrCallLog::where('provider_call_id', $callId)->exists()) {
                    $result['duplicate_calls']++;
                    continue;
                }

                $canonical = $this->selectCanonicalAttempt($attempts);
                $data = $this->normalizeKeys($canonical);
                $phone = $this->normalizePhone($data['CLI'] ?? null);
                $agentNumber = $this->agentNumberFrom($data);

                $callLog = IvrCallLog::create([
                    'provider_call_id' => $callId,
                    'ivr_call_type_id' => $callType->id,
                    'call_type_code' => $callType->code,
                    'dni' => $data['DNI'] ?? null,
                    'cli' => $data['CLI'] ?? null,
                    'normalized_phone' => $phone,
                    'raw_dtmf' => $data['DTMF'] ?? null,
                    'agent_name' => $data['AGENTNAME'] ?? null,
                    'agent_number' => $agentNumber,
                   'dial_status' =>
                    $data['DIALSTATUS']
                    ?? $data['BPARTYSTATUS']
                    ?? null,
                    'call_start_at' => $this->parseDateTime($data['CALLSTARTTIME'] ?? null),
                    'call_end_at' => $this->parseDateTime($data['CALLENDTIME'] ?? null),
                    'duration_sec' => $this->toNullableInt($data['DUARATIONSEC'] ?? $data['DURATIONSEC'] ?? null),
                    'og_duration_sec' => $this->toNullableInt($data['OGDUARATIONSEC'] ?? $data['OGDURATIONSEC'] ?? null),
                    'voice_url' => $data['VOICEURL'] ?? null,
                    'processing_status' => 'received',
                    'raw_payload' => ['canonical' => $canonical, 'attempts' => $attempts],
                ]);

                $leadResult = $this->leadService->processCallLog($callLog);
                if (($leadResult['status'] ?? '') === 'repeat_lead') {
                    $result['repeat_leads']++;
                } else {
                    $result['created']++;
                }
            } catch (\Throwable $e) {
                $result['errors']++;
                Log::error('VI call import failed', ['call_id' => $callId, 'error' => $e->getMessage()]);
                $existing = IvrCallLog::where('provider_call_id', $callId)->first();
                if ($existing) {
                    $existing->processing_status = 'error';
                    $existing->processing_message = $e->getMessage();
                    $existing->save();
                }
            }
        }

        return $result;
    }

 private function selectCanonicalAttempt(array $attempts): array
{
    foreach ($attempts as $attempt) {
        $data = $this->normalizeKeys($attempt);

        $status = strtolower(
            trim(
                (string) (
                    $data['DIALSTATUS']
                    ?? $data['BPARTYSTATUS']
                    ?? ''
                )
            )
        );

        if (in_array(
            $status,
            [
                'success',
                'sucess',
                'connected',
            ],
            true
        )) {
            return $attempt;
        }
    }

    return $attempts[0];
}

    private function normalizeKeys(array $record): array
    {
        $normalized = [];
        foreach ($record as $key => $value) {
            $normalizedKey = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $key));
            $normalized[$normalizedKey] = $value;
        }
        return $normalized;
    }

    private function agentNumberFrom(array $data): ?string
    {
        foreach ([
            'BPARTYNO',
            'BPARTYNUMBER',
            'BPARTYDIALNO',
            'BPARTYDIALNUMBER',
            'AGENTNUMBER',
            'AGENTMOBILE',
        ] as $key) {
            if (!empty($data[$key])) {
                return $this->normalizePhone($data[$key]);
            }
        }

        return null;
    }

    private function normalizePhone($value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') {
            return null;
        }
        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    private function parseDateTime($value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y H:i:s', 'd-m-Y H:i:s', 'Y-m-d H:i:s'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date;
                }
            } catch (\Throwable $e) {
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function toNullableInt($value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
