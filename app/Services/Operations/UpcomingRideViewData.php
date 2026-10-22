<?php

namespace App\Services\Operations;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class UpcomingRideViewData
{
    public function build(Collection $rides): array
    {
        $rows = $rides->map(function (array $ride) {
            $start = Carbon::parse($ride['ride_start']);
            $due = (float) $ride['pending_amount_value'];
            $total = (float) $ride['total_amount_value'];
            $urgent = $ride['is_tba'] && $start->copy()->endOfDay()->gte(now()) && $start->lte(now()->addHours(48));
            $paid = $total > 0 && $due <= 0.005;
            $ride['health'] = $urgent ? 'urgent' : ($due > 0.005 ? 'payment' : (!$ride['has_vendor'] ? 'vendor' : ($paid ? 'ready' : 'neutral')));
            $ride['urgent'] = $urgent;
            $ride['fully_paid'] = $paid;
            $ride['payment_label'] = $total <= 0 ? 'Amount not recorded'
                : ($paid ? 'Paid in full' : ($due < $total ? 'Partially paid' : 'Pending'));
            $ride['lead_url'] = route('admin.leads.view', $ride['lead_id']);
            return $ride;
        })->sortBy('ride_start')->values();
        $count = $rows->count();
        $paid = $rows->where('fully_paid', true)->count();
        return [
            'rows' => $rows->all(),
            'groups' => $rows->groupBy('ride_day'),
            'summary' => [
                'total' => $count, 'today' => $rows->where('ride_day', now()->toDateString())->count(),
                'tomorrow' => $rows->where('ride_day', now()->addDay()->toDateString())->count(),
                'unconfirmed' => $rows->where('urgent', true)->count(),
                'payment_pending' => $rows->filter(fn ($ride) => $ride['pending_amount_value'] > 0.005)->count(),
                'fully_paid' => $paid, 'paid_percent' => $count ? (int) round($paid / $count * 100) : 0,
            ],
            'events' => $rows->map(fn ($ride) => [
                'id' => $ride['id'], 'title' => $ride['client_name'],
                'start' => $ride['is_tba'] ? $ride['ride_day'] : $ride['ride_start'],
                'allDay' => (bool) $ride['is_tba'], 'classNames' => ['ur-event', 'ur-'.$ride['health']],
                'extendedProps' => ['ride' => $ride],
            ])->all(),
        ];
    }
}
