<?php

namespace App\Services\Kpi;

use App\Models\KpiMetric;
use App\Models\KpiTemplate;
use App\Models\KpiUserAssignment;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KpiTemplateAutomationService
{
    public function definitions(): array
    {
        return (array) config('kpi.default_templates', []);
    }

    public function definition(string $department): ?array
    {
        $definition = $this->definitions()[$department] ?? null;

        return is_array($definition) ? $definition : null;
    }

    public function eligibleUsers(string $department): Collection
    {
        $roles = match ($department) {
            'sales' => UserType::SALES_ROLES,
            'accounts' => UserType::ACCOUNTS_ROLES,
            'operations' => UserType::OPERATIONS_ROLES,
            default => [],
        };

        if ($roles === []) {
            return collect();
        }

        return User::with('userType')
            ->where('status', 1)
            ->whereHas('userType', function ($query) use ($roles) {
                $query->whereIn('user_type', $roles);
            })
            ->orderBy('name')
            ->get();
    }

    public function syncDepartment(
        string $department,
        ?string $actorId = null
    ): array {
        $definition = $this->definition($department);

        if (!$definition) {
            throw new \InvalidArgumentException(
                "No default KPI definition configured for {$department}."
            );
        }

        return DB::transaction(function () use (
            $department,
            $definition,
            $actorId
        ) {
            $template = KpiTemplate::query()->firstOrNew([
                'department' => $department,
                'name' => $definition['name'],
            ]);

            $template->fill([
                'working_days_per_month' => (int) ($definition['working_days_per_month'] ?? 22),
                'active' => true,
                'effective_from' => now()->startOfMonth()->toDateString(),
                'effective_to' => null,
                'updated_by' => $actorId,
            ]);

            if (!$template->exists) {
                $template->created_by = $actorId;
            }

            $template->save();

            $metrics = collect($definition['metrics'] ?? []);
            $activeCodes = [];
            $createdOrUpdated = 0;

            foreach ($metrics as $metric) {
                $activeCodes[] = (string) $metric['code'];
                KpiMetric::query()->updateOrCreate(
                    [
                        'template_id' => $template->id,
                        'code' => $metric['code'],
                    ],
                    [
                        'name' => $metric['name'],
                        'description' => $metric['description'] ?? null,
                        'weightage' => (float) ($metric['weightage'] ?? 0),
                        'measurement_type' => $metric['measurement_type'] ?? 'automatic',
                        'source_key' => $metric['source_key'] ?? null,
                        'target_value' => $metric['target_value'] ?? null,
                        'direction' => $metric['direction'] ?? 'higher_better',
                        'score_rules' => $metric['score_rules'] ?? [],
                        'sort_order' => (int) ($metric['sort_order'] ?? 0),
                        'active' => true,
                    ]
                );
                $createdOrUpdated++;
            }

            KpiMetric::query()
                ->where('template_id', $template->id)
                ->whereNotIn('code', $activeCodes)
                ->update(['active' => false]);

            $assigned = 0;

            foreach ($this->eligibleUsers($department) as $user) {
                $hasActiveAssignment = KpiUserAssignment::query()
                    ->where('user_id', $user->id)
                    ->where('active', true)
                    ->whereDate('effective_from', '<=', now()->toDateString())
                    ->where(function ($query) {
                        $query->whereNull('effective_to')
                            ->orWhereDate('effective_to', '>=', now()->toDateString());
                    })
                    ->exists();

                if ($hasActiveAssignment) {
                    continue;
                }

                KpiUserAssignment::create([
                    'user_id' => $user->id,
                    'template_id' => $template->id,
                    'effective_from' => now()->startOfMonth()->toDateString(),
                    'effective_to' => null,
                    'active' => true,
                    'assigned_by' => $actorId,
                ]);

                $assigned++;
            }

            return [
                'department' => $department,
                'template_id' => $template->id,
                'template_name' => $template->name,
                'metrics_synced' => $createdOrUpdated,
                'users_assigned' => $assigned,
            ];
        });
    }

    public function coverage(): array
    {
        $coverage = [];

        foreach ($this->definitions() as $department => $definition) {
            $template = KpiTemplate::query()
                ->where('department', $department)
                ->where('name', $definition['name'] ?? '')
                ->where('active', true)
                ->first();

            $eligible = $this->eligibleUsers($department);
            $assigned = $template
                ? KpiUserAssignment::query()
                    ->where('template_id', $template->id)
                    ->where('active', true)
                    ->count()
                : 0;

            $coverage[$department] = [
                'template' => $template,
                'eligible_users' => $eligible->count(),
                'assigned_users' => $assigned,
                'definition' => $definition,
                'total_weightage' => collect($definition['metrics'] ?? [])
                    ->sum(fn ($metric) => (float) ($metric['weightage'] ?? 0)),
            ];
        }

        return $coverage;
    }
}
