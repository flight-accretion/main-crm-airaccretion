<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AiAgentTelemetry
{
    public function run(?string $agentId, string $kind, callable $operation): array
    {
        $started = microtime(true);
        try {
            $result = $operation();
        } catch (\Throwable $exception) {
            $this->record($agentId, $kind, 'failed', $started);
            throw $exception;
        }
        $this->record($agentId, $kind, !empty($result['needs_human']) ? 'handoff' : 'handled', $started);
        return $result;
    }

    private function record(?string $agentId, string $kind, string $outcome, float $started): void
    {
        try {
            if (!Schema::hasTable('ai_agent_runs')) return;
            // No prompts, customer messages, provider credentials or raw errors are retained.
            DB::table('ai_agent_runs')->insert([
                'ai_agent_id' => $agentId, 'kind' => $kind, 'outcome' => $outcome,
                'duration_ms' => max(0, (int) round((microtime(true) - $started) * 1000)), 'occurred_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('AI telemetry could not be recorded.', ['exception_type' => get_class($exception)]);
        }
    }

    public function summary(): array
    {
        if (!Schema::hasTable('ai_agent_runs')) return ['available' => false, 'handled' => null, 'handoffs' => null, 'failures' => null, 'agents' => collect()];
        $week = now()->startOfWeek();
        $daily = now()->subDay();
        $rows = DB::table('ai_agent_runs')->select('ai_agent_id')
            ->selectRaw('MAX(occurred_at) as last_run')
            ->selectRaw("SUM(CASE WHEN occurred_at >= ? AND outcome IN ('handled', 'handoff') THEN 1 ELSE 0 END) as handled", [$week])
            ->selectRaw("SUM(CASE WHEN occurred_at >= ? AND outcome = 'handoff' THEN 1 ELSE 0 END) as handoffs", [$week])
            ->selectRaw("SUM(CASE WHEN occurred_at >= ? AND outcome = 'failed' THEN 1 ELSE 0 END) as failures", [$daily])
            ->groupBy('ai_agent_id')->get();
        return ['available' => true, 'handled' => (int) $rows->sum('handled'), 'handoffs' => (int) $rows->sum('handoffs'),
            'failures' => (int) $rows->sum('failures'), 'agents' => $rows->keyBy('ai_agent_id')];
    }
}
