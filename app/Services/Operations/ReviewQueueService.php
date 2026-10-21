<?php

namespace App\Services\Operations;

use App\Models\OperationCase;
use Illuminate\Database\Eloquent\Builder;

class ReviewQueueService
{
    public function filter(Builder $query, string $segment): void
    {
        if ($segment === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($segment === 'waiting') {
            $query->whereHas('reviewConversation', fn ($q) => $q
                ->whereNotNull('initial_message_sent_at')->where('customer_replied', false));
        } elseif ($segment === 'not_sent') {
            $query->where(fn ($q) => $q->whereDoesntHave('reviewConversation')
                ->orWhereHas('reviewConversation', fn ($c) => $c
                    ->whereNull('initial_message_sent_at')->where('customer_replied', false)));
        } elseif (in_array($segment, ['negative', 'positive', 'neutral'], true)) {
            $query->whereHas('reviewConversation', fn ($q) => $q
                ->where('customer_replied', true)->where('sentiment', $segment));
        } elseif ($segment === 'unknown') {
            $query->whereHas('reviewConversation', fn ($q) => $q->where('customer_replied', true)
                ->where(fn ($s) => $s->whereNull('sentiment')->orWhereNotIn('sentiment', ['negative', 'positive', 'neutral'])));
        }
    }

    public function summary(Builder $query): array
    {
        $counts = ['all' => (clone $query)->count()];
        foreach (['waiting', 'negative', 'positive', 'neutral', 'unknown', 'not_sent', 'unassigned'] as $segment) {
            $subset = clone $query;
            $this->filter($subset, $segment);
            $counts[$segment] = $subset->count();
        }
        $waiting = clone $query;
        $this->filter($waiting, 'waiting');
        $counts['oldest_waiting'] = $waiting->min('opened_at');
        $driver = $query->getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            $ageExpression = 'EXTRACT(EPOCH FROM (CAST(? AS TIMESTAMP) - opened_at)) / 86400.0';
        } elseif ($driver === 'sqlite') {
            $ageExpression = "(CAST(strftime('%s', ?) AS INTEGER) - CAST(strftime('%s', opened_at) AS INTEGER)) / 86400.0";
        } else {
            $ageExpression = '(UNIX_TIMESTAMP(?) - UNIX_TIMESTAMP(opened_at)) / 86400.0';
        }
        $average = (clone $query)->reorder()
            ->selectRaw("AVG($ageExpression) as average_days", [now()->toDateTimeString()])
            ->value('average_days');
        $counts['average_days'] = $average === null ? null : round(max(0, $average), 1);

        return $counts;
    }

    public function state(OperationCase $case): array
    {
        $conversation = $case->reviewConversation;
        $sentiment = !$conversation?->customer_replied
            ? ($conversation?->initial_message_sent_at ? 'waiting' : 'not_sent')
            : (in_array($conversation->sentiment, ['negative', 'positive', 'neutral'], true) ? $conversation->sentiment : 'unknown');
        $labels = ['waiting' => 'Waiting on customer', 'not_sent' => 'Not sent yet',
            'negative' => 'Negative - replied', 'positive' => 'Positive - replied',
            'neutral' => 'Neutral - replied', 'unknown' => 'Unknown sentiment'];
        $review = $this->stage($case, 'review');
        $images = $this->stage($case, 'image_collection');
        $touch = collect([
            ['at' => $case->opened_at, 'label' => 'Case opened'],
            ['at' => $case->activities_max_created_at, 'label' => 'Operations activity'],
            ['at' => $conversation?->initial_message_sent_at, 'label' => 'Review request sent'],
            ['at' => $conversation?->last_reminder_at, 'label' => 'Reminder sent'],
            ['at' => $conversation?->whatsappConversation?->last_customer_message_at, 'label' => 'Customer replied'],
        ])->filter(fn ($touch) => $touch['at'])->sortByDesc(fn ($touch) => \Carbon\Carbon::parse($touch['at'])->timestamp)->first();

        return ['sentiment' => $sentiment, 'label' => $labels[$sentiment], 'review' => $review,
            'images' => $images, 'review_resolved' => in_array($review, ['completed', 'cancelled'], true),
            'images_resolved' => in_array($images, ['completed', 'cancelled'], true), 'touch' => $touch];
    }

    private function stage(OperationCase $case, string $stage): string
    {
        return data_get($case->metadata, $stage . '_status')
            ?: (data_get($case->metadata, $stage . '_completed') ? 'completed'
                : (data_get($case->metadata, $stage . '_cancelled') ? 'cancelled' : 'pending'));
    }
}
