<?php

namespace App\Services;

use App\Models\LeadAiScoringSetting;
use App\Models\LeadFollowup;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class LeadListFilter
{
    public function setting(): LeadAiScoringSetting
    {
        // Reading a list must not create settings or start AI processing.
        return LeadAiScoringSetting::query()->orderBy('created_at')->first()
            ?? new LeadAiScoringSetting(['cold_max' => 39, 'neutral_max' => 69]);
    }

    public function apply(Builder $query, Request $request, bool $isSuperAdmin, LeadAiScoringSetting $setting, bool $applyQueue = true): void
    {
        $request->validate([
            'lead_queue' => 'nullable|in:all,hot,queued,unassigned,no_followup',
            'from_followup_date' => 'nullable|date_format:Y-m-d',
            'to_followup_date' => 'nullable|date_format:Y-m-d' . ($request->filled('from_followup_date') ? '|after_or_equal:from_followup_date' : ''),
            'followup_period' => 'nullable|in:today,overdue,upcoming',
            'from_created_date' => 'nullable|date_format:Y-m-d',
            'to_created_date' => 'nullable|date_format:Y-m-d' . ($request->filled('from_created_date') ? '|after_or_equal:from_created_date' : ''),
        ]);
        $queue = (string) $request->input('lead_queue', 'all');
        abort_if($queue === 'queued' && !$isSuperAdmin, 403);
        if ($applyQueue) {
            $this->queue($query, $queue, $setting);
        }

        if ($request->filled('from_created_date')) {
            $query->where('leads.created_at', '>=', Carbon::parse($request->from_created_date)->startOfDay());
        }
        if ($request->filled('to_created_date')) {
            $query->where('leads.created_at', '<=', Carbon::parse($request->to_created_date)->endOfDay());
        }
        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($search) use ($term) {
                $search->whereHas('client', function ($client) use ($term) {
                    $client->where('name', 'like', $term)
                        ->orWhere('company_name', 'like', $term)
                        ->orWhere('gst_number', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('contact_number', 'like', $term);
                })->orWhereHas('representative', fn ($rep) => $rep->where('name', 'like', $term))
                    ->orWhereHas('rideSegments', function ($ride) use ($term) {
                        $ride->where('from_place', 'like', $term)->orWhere('to_place', 'like', $term);
                    })->orWhereHas('leadFollowups', fn ($followup) => $followup->where('followup_note', 'like', $term));
            });
        }

        $from = $request->input('from_followup_date');
        $to = $request->input('to_followup_date');
        $period = $request->input('followup_period');
        if (!$from && !$to && !$period) {
            return;
        }
        $query->whereExists(function ($followup) use ($from, $to, $period) {
            $this->latestFollowup($followup);
            if ($from) $followup->where('lf.next_followup_date', '>=', Carbon::parse($from)->startOfDay());
            if ($to) $followup->where('lf.next_followup_date', '<=', Carbon::parse($to)->endOfDay());
            if ($period === 'today') $followup->whereBetween('lf.next_followup_date', [now()->startOfDay(), now()->endOfDay()]);
            if ($period === 'upcoming') $followup->where('lf.next_followup_date', '>=', now()->addDay()->startOfDay());
            if ($period === 'overdue') {
                $followup->where('lf.next_followup_date', '<', now()->startOfDay())
                    ->whereIn('lf.status', LeadFollowup::TODAY_FOLLOWUP_MISSED_OPEN_STATUSES);
            }
        });
    }

    public function queue(Builder $query, string $queue, LeadAiScoringSetting $setting): void
    {
        if ($queue === 'hot') {
            $hotAbove = max((int) $setting->cold_max, (int) $setting->neutral_max);
            $query->whereExists(function ($score) use ($hotAbove) {
                $score->selectRaw('1')->from('lead_ai_scores as las')->whereColumn('las.lead_id', 'leads.id')
                    ->where('las.id', function ($latest) {
                        $latest->select('last_score.id')->from('lead_ai_scores as last_score')
                            ->whereColumn('last_score.lead_id', 'leads.id')
                            ->orderByDesc('last_score.created_at')->orderByDesc('last_score.id')->limit(1);
                    })->where('las.status', 'completed')->where(function ($hot) use ($hotAbove) {
                        $hot->where('las.score', '>', $hotAbove)->orWhere(function ($legacy) {
                            $legacy->whereNull('las.score')->whereRaw('LOWER(TRIM(las.temperature)) = ?', ['hot']);
                        });
                    });
            });
        } elseif ($queue === 'queued') {
            $query->whereNull('leads.representative_user_id')->whereExists(function ($queued) {
                $queued->selectRaw('1')->from('lead_allocation_queue as laq')
                    ->whereColumn('laq.lead_id', 'leads.id')->where('laq.status', 'queued');
            });
        } elseif ($queue === 'unassigned') {
            $query->whereNull('leads.representative_user_id');
        } elseif ($queue === 'not_queued') {
            $query->whereNotExists(function ($queued) {
                $queued->selectRaw('1')->from('lead_allocation_queue as laq')
                    ->whereColumn('laq.lead_id', 'leads.id')->where('laq.status', 'queued');
            });
        } elseif ($queue === 'no_followup') {
            $query->whereNotExists(function ($followup) {
                $this->latestFollowup($followup);
                $followup->whereNotNull('lf.next_followup_date');
            });
        }
    }

    private function latestFollowup($query): void
    {
        $query->selectRaw('1')->from('lead_followups as lf')->whereColumn('lf.lead_id', 'leads.id')
            ->where('lf.id', function ($latest) {
                $latest->select('last_followup.id')->from('lead_followups as last_followup')
                    ->whereColumn('last_followup.lead_id', 'leads.id')
                    ->orderByDesc('last_followup.created_at')->orderByDesc('last_followup.id')->limit(1);
            });
    }
}
