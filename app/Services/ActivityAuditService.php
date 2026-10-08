<?php

namespace App\Services;

use App\Models\ActivityAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class ActivityAuditService
{
    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'api_key',
        'secret',
        'access_token',
        'refresh_token',
        'authorization',
        'card_number',
        'cvv',
        'otp',
        'file',
        'front_document',
        'back_document',
        'refund_proof',
        'receipt',
    ];

    public function record(
        string $module,
        string $action,
        ?Model $entity = null,
        array $oldValues = [],
        array $newValues = [],
        array $meta = []
    ): ?ActivityAudit {
        try {
            $user = Auth::user();
            $request = request();

            return ActivityAudit::create([
                'id' => (string) Str::uuid(),
                'actor_id' => $user?->id,
                'actor_name' => $user?->name,
                'actor_role' => optional($user?->userType)->user_type,
                'module' => $module,
                'action' => $action,
                'entity_id' => $entity?->getKey(),
                'entity_type' => $entity ? get_class($entity) : null,
                'lead_id' => $this->resolveLeadId($entity, $meta),
                'client_id' => $this->resolveClientId($entity, $meta),
                'old_values' => $this->sanitize($oldValues),
                'new_values' => $this->sanitize($newValues),
                'meta' => $this->sanitize($meta),
                'ip_address' => $request?->ip(),
                'user_agent' => substr((string) $request?->userAgent(), 0, 500) ?: null,
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function resolveLeadId(?Model $entity, array $meta): ?string
    {
        return $meta['lead_id']
            ?? $entity?->lead_id
            ?? ($entity && $entity->getTable() === 'leads' ? $entity->getKey() : null);
    }

    private function resolveClientId(?Model $entity, array $meta): ?string
    {
        return $meta['client_id']
            ?? $entity?->client_id
            ?? ($entity && $entity->getTable() === 'clients' ? $entity->getKey() : null);
    }

    private function sanitize(array $values): array
    {
        $safe = [];

        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_FIELDS, true)) {
                $safe[$key] = '[redacted]';
                continue;
            }

            $safe[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $safe;
    }
}
