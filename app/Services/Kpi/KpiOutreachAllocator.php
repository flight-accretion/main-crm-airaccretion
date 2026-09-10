<?php

namespace App\Services\Kpi;

use App\Models\KpiOutreachAssignment;
use App\Models\KpiOutreachBatch;
use App\Models\KpiOutreachCursor;
use App\Models\KpiOutreachPool;
use App\Models\User;
use App\Services\ActiveLeadService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class KpiOutreachAllocator
{
    public function __construct(
        private ActiveLeadService $activeLeadService
    ) {}

    public function allocate(
        User $user,
        int $count,
        string $type = 'standard',
        ?KpiOutreachBatch $batch = null
    ): int {
        if ($count <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($user, $count, $type, $batch) {
            $cursor = KpiOutreachCursor::query()
                ->lockForUpdate()
                ->findOrFail(1);

            $start = (int) $cursor->last_pool_id;
            $allocated = 0;
            $lastScanned = $start;

            $process = function ($query) use (
                $user,
                $count,
                $type,
                $batch,
                &$allocated,
                &$lastScanned
            ) {
                foreach ($query->cursor() as $pool) {
                    if ($allocated >= $count) {
                        break;
                    }

                    $lastScanned = (int) $pool->id;

                    if ($pool->cooling_until && $pool->cooling_until->isFuture()) {
                        continue;
                    }

                    if ($this->activeLeadService->findByPhone($pool->normalized_phone)) {
                        continue;
                    }

                    try {
                        KpiOutreachAssignment::create([
                            'pool_id' => $pool->id,
                            'user_id' => $user->id,
                            'batch_id' => $batch?->id,
                            'allocation_type' => $type,
                            'normalized_phone' => $pool->normalized_phone,
                            'active_phone_key' => $pool->normalized_phone,
                            'status' => 'pending',
                            'assigned_at' => now(),
                        ]);

                        $allocated++;
                    } catch (QueryException $e) {
                        continue;
                    }
                }
            };

            $process(
                KpiOutreachPool::query()
                    ->where('id', '>', $start)
                    ->orderBy('id')
            );

            if ($allocated < $count) {
                $process(
                    KpiOutreachPool::query()
                        ->where('id', '<=', $start)
                        ->orderBy('id')
                );
            }

            $cursor->last_pool_id = $lastScanned;
            $cursor->save();

            if ($batch) {
                $batch->allocated_count = $allocated;
                $batch->save();
            }

            return $allocated;
        }, 3);
    }
}
