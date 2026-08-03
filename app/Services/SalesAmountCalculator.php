<?php

namespace App\Services;

use App\Models\ExtraService;
use App\Models\LeadFollowup;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesAmountCalculator
{
    private static array $serviceCache = [];
    private static array $extraServiceCache = [];

    public static function feeAmount(float $amount, float $feePercent): float
    {
        $amount = max(0, $amount);
        $feePercent = self::normalizePercent($feePercent);

        return round(($amount * $feePercent) / 100, 2);
    }

    public static function amountAfterFees(float $amount, float $feePercent): float
    {
        return max(0, round(max(0, $amount) - self::feeAmount($amount, $feePercent), 2));
    }

    public static function salesAmountForFollowup(LeadFollowup $followup, float $refundAmount = 0): float
    {
        $grossTotal = max(0, (float) ($followup->total_amount ?? 0));
        if ($grossTotal <= 0) {
            return 0.0;
        }

        $netBeforeRefund = self::netAmountForFollowup($followup, $grossTotal);

        return max(0, round($netBeforeRefund - max(0, $refundAmount), 2));
    }

    public static function netAmountForFollowup(LeadFollowup $followup, ?float $grossTotal = null): float
    {
        $grossTotal = max(0, (float) ($grossTotal ?? $followup->total_amount ?? 0));
        if ($grossTotal <= 0) {
            return 0.0;
        }

        $details = self::normalizeServiceDetails($followup->service_details ?? null);
        if (!empty($details)) {
            [$detailsGross, $detailsNet] = self::detailTotalsAfterFees($followup, $details);
            if ($detailsGross > 0) {
                return round($grossTotal * ($detailsNet / $detailsGross), 2);
            }
        }

        [$componentGross, $componentNet] = self::currentMasterTotalsAfterFees($followup);
        if ($componentGross > 0) {
            return round($grossTotal * ($componentNet / $componentGross), 2);
        }

        return round($grossTotal, 2);
    }

    public static function normalizePercent($value): float
    {
        return min(100, max(0, (float) ($value ?? 0)));
    }

    private static function detailTotalsAfterFees(LeadFollowup $followup, array $details): array
    {
        $serviceIds = self::idsFromFollowupValue($followup->service_ids);
        foreach ($details as $detail) {
            if (($detail['type'] ?? null) === 'service' && !empty($detail['id'])) {
                $serviceIds[] = (string) $detail['id'];
            }
            if (!empty($detail['parent_service_id'])) {
                $serviceIds[] = (string) $detail['parent_service_id'];
            }
        }

        $serviceFeeMap = self::serviceFeeMap($serviceIds);
        $extraServiceFeeMap = self::extraServiceFeeMap($serviceIds);
        $fallbackFeePercent = count($serviceFeeMap) === 1 ? reset($serviceFeeMap) : 0.0;

        $gross = 0.0;
        $net = 0.0;

        foreach ($details as $detail) {
            $finalAmount = self::detailFinalAmount($detail);
            if ($finalAmount <= 0) {
                continue;
            }

            $type = $detail['type'] ?? null;
            $hasStoredFeePercent = array_key_exists('fees_percent', $detail) || array_key_exists('gst_percent', $detail);
            $feePercent = $hasStoredFeePercent
                ? self::normalizePercent($detail['fees_percent'] ?? $detail['gst_percent'] ?? 0)
                : 0.0;

            if (!$hasStoredFeePercent) {
                if ($type === 'service' && !empty($detail['id'])) {
                    $feePercent = $serviceFeeMap[(string) $detail['id']] ?? 0.0;
                } elseif ($type === 'extra_service') {
                    if (!empty($detail['parent_service_id'])) {
                        $feePercent = $serviceFeeMap[(string) $detail['parent_service_id']] ?? 0.0;
                    } elseif (!empty($detail['id'])) {
                        $feePercent = $extraServiceFeeMap[(string) $detail['id']] ?? $fallbackFeePercent;
                    }
                }
            }

            $gross += $finalAmount;
            $net += self::amountAfterFees($finalAmount, $feePercent);
        }

        return [$gross, $net];
    }

    private static function currentMasterTotalsAfterFees(LeadFollowup $followup): array
    {
        $serviceIds = self::idsFromFollowupValue($followup->service_ids);
        $extraServiceIds = self::idsFromFollowupValue($followup->extra_service_ids);

        $gross = 0.0;
        $net = 0.0;
        $serviceFeeMap = self::serviceFeeMap($serviceIds);

        foreach (self::serviceRecords($serviceIds) as $service) {
            $amount = max(0, (float) ($service->service_amount ?? 0));
            $gross += $amount;
            $net += self::amountAfterFees($amount, (float) ($service->fees_percent ?? 0));
        }

        $extraServiceFeeMap = self::extraServiceFeeMap($serviceIds);
        $fallbackFeePercent = count($serviceFeeMap) === 1 ? reset($serviceFeeMap) : 0.0;

        foreach (self::extraServiceRecords($extraServiceIds) as $extraService) {
            $amount = max(0, (float) ($extraService->extra_service_amount ?? 0));
            $feePercent = $extraServiceFeeMap[(string) $extraService->id] ?? $fallbackFeePercent;
            $gross += $amount;
            $net += self::amountAfterFees($amount, $feePercent);
        }

        return [$gross, $net];
    }

    private static function detailFinalAmount(array $detail): float
    {
        if (array_key_exists('final_amount', $detail)) {
            return max(0, (float) $detail['final_amount']);
        }

        $original = max(0, (float) ($detail['original_amount'] ?? 0));
        $discount = max(0, (float) ($detail['discount_amount'] ?? 0));

        return max(0, $original - $discount);
    }

    private static function normalizeServiceDetails($details): array
    {
        if (is_string($details)) {
            $decoded = json_decode($details, true);
            return is_array($decoded) ? array_values($decoded) : [];
        }

        return is_array($details) ? array_values($details) : [];
    }

    private static function idsFromFollowupValue($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            return [];
        }

        return self::validIds($value);
    }

    private static function serviceFeeMap(array $serviceIds): array
    {
        $map = [];
        foreach (self::serviceRecords($serviceIds) as $service) {
            $map[(string) $service->id] = self::normalizePercent($service->fees_percent ?? 0);
        }

        return $map;
    }

    private static function extraServiceFeeMap(array $serviceIds): array
    {
        $serviceIds = self::validIds($serviceIds);
        if (empty($serviceIds)) {
            return [];
        }

        $serviceFeeMap = self::serviceFeeMap($serviceIds);
        $rows = DB::table('service_extra_service')
            ->whereIn('service_id', $serviceIds)
            ->get(['service_id', 'extra_service_id']);

        $map = [];
        foreach ($rows as $row) {
            $extraId = (string) $row->extra_service_id;
            if (!array_key_exists($extraId, $map)) {
                $map[$extraId] = $serviceFeeMap[(string) $row->service_id] ?? 0.0;
            }
        }

        return $map;
    }

    private static function serviceRecords(array $ids)
    {
        $ids = self::validIds($ids);
        $missing = array_values(array_filter($ids, fn($id) => !array_key_exists($id, self::$serviceCache)));

        if (!empty($missing)) {
            Service::whereIn('id', $missing)
                ->get(['id', 'service_amount', 'fees_percent'])
                ->each(function ($service) {
                    self::$serviceCache[(string) $service->id] = $service;
                });

            foreach ($missing as $id) {
                self::$serviceCache[$id] = self::$serviceCache[$id] ?? null;
            }
        }

        return collect($ids)
            ->map(fn($id) => self::$serviceCache[$id] ?? null)
            ->filter()
            ->values();
    }

    private static function extraServiceRecords(array $ids)
    {
        $ids = self::validIds($ids);
        $missing = array_values(array_filter($ids, fn($id) => !array_key_exists($id, self::$extraServiceCache)));

        if (!empty($missing)) {
            ExtraService::whereIn('id', $missing)
                ->get(['id', 'extra_service_amount'])
                ->each(function ($extraService) {
                    self::$extraServiceCache[(string) $extraService->id] = $extraService;
                });

            foreach ($missing as $id) {
                self::$extraServiceCache[$id] = self::$extraServiceCache[$id] ?? null;
            }
        }

        return collect($ids)
            ->map(fn($id) => self::$extraServiceCache[$id] ?? null)
            ->filter()
            ->values();
    }

    private static function validIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('strval', $ids), function ($id) {
            return Str::isUuid($id);
        })));
    }
}
