<?php

namespace App\Services\WhatsApp\Msg91;

use App\Models\{Msg91WhatsAppCampaign, Msg91WhatsAppSenderTemplateBinding, Msg91WhatsAppTemplate, User, UserType};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Msg91TemplateDeletionService
{
    public function __construct(private Msg91Client $client, private Msg91TemplateDefinition $definitions) {}

    private function reject(string $message): void
    {
        throw ValidationException::withMessages(['msg91_template' => $message]);
    }

    private function assertNoCampaigns(Msg91WhatsAppTemplate $template): void
    {
        $active = Msg91WhatsAppCampaign::where('template_id', $template->id)
            ->where(function ($query) {
                $query->whereIn('status', ['queued', 'processing', 'pending', 'scheduled', 'retry_scheduled'])
                    ->orWhereHas('recipients', fn ($q) => $q->whereIn('status', ['processing', 'accepted', 'unknown']))
                    ->orWhere(function ($retry) {
                        $retry->whereNull('cancelled_at')->whereHas('recipients', fn ($q) => $q->where(fn ($states) => $states
                            ->whereIn('status', ['queued', 'retry_scheduled'])
                            ->orWhere(fn ($f) => $f->where('status', 'failed')->where('attempts', 0)
                                ->whereIn('failure_code', ['sender_unavailable', 'dispatch_authorization_failed']))));
                    });
            })->exists();
        if ($active) $this->reject('This template has an active, unresolved or retryable campaign. Complete or reconcile it before deleting.');
    }

    public function delete(Msg91WhatsAppTemplate $template, User $actor): array
    {
        abort_unless($actor->userType?->user_type === UserType::SUPER_ADMIN, 403);
        if ($template->fresh()->status === 'deleted') return ['complete' => true, 'succeeded' => 0, 'remaining' => 0];
        $this->assertNoCampaigns($template);
        // Name-only deletion can affect another language variant, even on one number.
        if (Msg91WhatsAppTemplate::where('name', $template->name)->whereKeyNot($template->id)
            ->whereNotIn('status', ['deleted'])->exists()) {
            $this->reject('Another CRM template shares this name. MSG91 name-only deletion has ambiguous scope; reconcile its language/identity first.');
        }
        $bindings = $template->bindings()->with('sender')->orderBy('sender_id')->get();
        if ($bindings->isEmpty()) $this->reject('No MSG91 sender is linked. Sync and verify template identity before deletion.');
        $confirmed = [];
        foreach ($bindings as $binding) {
            $sender = $binding->sender;
            $number = $sender?->integrated_number;
            if (!$number || !preg_match('/^[0-9]{8,15}$/', $number)) $this->reject('A linked sender is missing or invalid.');
            $boundNumber = data_get($binding->provider_meta, 'integrated_number', $number);
            if ($boundNumber !== $number || data_get($binding->provider_meta, 'previous_integrated_number')) {
                $this->reject('A sender number changed. Reconcile its sender-specific template identity before deleting.');
            }
            $operation = DB::table('msg91_whatsapp_template_deletions')->where('template_id', $template->id)->where('integrated_number', $number)->first();
            if ($operation && $operation->state === 'succeeded') { $confirmed[$binding->id] = ['number' => $number, 'done' => true]; continue; }
            if ($operation && $operation->state === 'in_progress' && $operation->started_at > now()->subMinutes(5)->toDateTimeString()) {
                abort(409, 'Deletion is already in progress for this sender.');
            }
            // Read-only provider preflight. Invalid, partial and error responses never prove absence.
            $variants = $this->definitions->variants($this->client->fetchTemplates($number));
            foreach ($variants as $row) if (!$row['name']) $this->reject('Provider template identity is incomplete.');
            $matching = array_values(array_filter($variants, fn ($row) => $row['name'] === $template->name));
            if (count($matching) > 1 || ($matching && $matching[0]['language'] !== $template->language)) {
                $this->reject('MSG91 reports ambiguous same-name language variants. No template deletion was submitted.');
            }
            if ($matching && (!$binding->provider_template_id || (string) $matching[0]['provider_id'] !== (string) $binding->provider_template_id)) {
                $this->reject('Provider template identity changed or is incomplete. Sync and review before deletion.');
            }
            $confirmed[$binding->id] = ['number' => $number, 'absent' => !$matching];
        }

        DB::transaction(function () use ($template, $actor, $bindings, $confirmed) {
            $locked = Msg91WhatsAppTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail();
            $this->assertNoCampaigns($locked);
            if (Msg91WhatsAppTemplate::where('name', $locked->name)->whereKeyNot($locked->id)->where('status', '!=', 'deleted')->exists()) $this->reject('Same-name template scope changed. Review before deleting.');
            $current = $locked->bindings()->with('sender')->orderBy('sender_id')->get();
            if ($current->pluck('id')->sort()->values()->all() !== $bindings->pluck('id')->sort()->values()->all()) abort(409, 'Template bindings changed. Retry review.');
            foreach ($current as $binding) {
                $number = $confirmed[$binding->id]['number'];
                if ($binding->sender?->integrated_number !== $number) abort(409, 'Sender changed during deletion review.');
                DB::table('msg91_whatsapp_template_deletions')->insertOrIgnore([
                    'id' => (string) Str::uuid(), 'template_id' => $locked->id, 'sender_id' => $binding->sender_id,
                    'binding_id' => $binding->id, 'integrated_number' => $number, 'template_name' => $locked->name,
                    'requested_by' => $actor->id, 'state' => 'pending', 'attempts' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $locked->forceFill(['status' => 'deleting', 'crm_enabled' => false, 'crm_visibility_overridden' => true])->save();
        });

        $errors = [];
        foreach ($bindings as $binding) {
            $target = $confirmed[$binding->id];
            $claim = DB::transaction(function () use ($binding, $target, $template, $actor) {
                $op = DB::table('msg91_whatsapp_template_deletions')->where('template_id', $template->id)
                    ->where('integrated_number', $target['number'])->lockForUpdate()->first();
                if ($op->state === 'succeeded') return null;
                if ($op->state === 'in_progress' && $op->started_at > now()->subMinutes(5)->toDateTimeString()) abort(409, 'Deletion is already in progress.');
                $token = (string) Str::uuid();
                $audit = json_decode($op->audit_meta ?? '{}', true);
                $audit['attempts'][] = ['actor_id' => $actor->id, 'token' => $token, 'at' => now()->toIso8601String(), 'operation' => 'delete'];
                DB::table('msg91_whatsapp_template_deletions')->where('id', $op->id)->update([
                    'state' => 'in_progress', 'attempts' => $op->attempts + 1, 'attempt_token' => $token,
                    'started_at' => now(), 'finished_at' => null, 'updated_at' => now(), 'audit_meta' => json_encode($audit),
                ]);
                return ['id' => $op->id, 'token' => $token, 'audit' => $audit];
            });
            if (!$claim) continue;
            $state = 'succeeded'; $httpStatus = null; $message = null;
            try {
                if (empty($target['absent'])) $this->client->deleteTemplate($target['number'], $template->name);
            } catch (Msg91ProviderException $exception) {
                $ambiguousAck = $exception->httpStatus >= 200 && $exception->httpStatus < 300
                    && data_get($exception->payload, 'hasError') !== true
                    && !in_array(strtolower((string) data_get($exception->payload, 'status')), ['error', 'fail', 'failed'], true);
                $state = $ambiguousAck || $exception->httpStatus >= 500 || $exception->httpStatus === 404 ? 'unknown' : 'failed';
                $httpStatus = $exception->httpStatus;
                $message = Msg91SafeError::message($exception->payload, 'MSG91 did not confirm deletion. Review and retry.');
            } catch (\Throwable $exception) {
                $state = 'unknown';
                $message = 'MSG91 deletion was not confirmed. Refresh provider state and retry reconciliation.';
            }
            $audit = $claim['audit'];
            $audit['attempts'][count($audit['attempts']) - 1] += ['state' => $state, 'http_status' => $httpStatus,
                'provider_confirmed_absent' => !empty($target['absent']), 'message' => $message];
            DB::transaction(function () use ($claim, $audit, $state, $binding) {
                $changed = DB::table('msg91_whatsapp_template_deletions')->where('id', $claim['id'])->where('attempt_token', $claim['token'])
                    ->update(['state' => $state, 'audit_meta' => json_encode($audit), 'finished_at' => now(), 'updated_at' => now()]);
                if (!$changed) abort(409, 'Deletion lease changed. Reconcile provider status.');
                if ($state === 'succeeded') $binding->forceFill(['provider_status' => 'deleted', 'last_synced_at' => now()])->save();
            });
            if ($message) $errors[] = $message;
        }
        $states = DB::table('msg91_whatsapp_template_deletions')->where('template_id', $template->id)->pluck('state');
        $remaining = $states->filter(fn ($state) => $state !== 'succeeded')->count();
        if (!$remaining) DB::transaction(function () use ($template) {
            $locked = Msg91WhatsAppTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['status' => 'deleted', 'crm_enabled' => false, 'crm_visibility_overridden' => true])->save();
        });
        return ['complete' => $remaining === 0, 'succeeded' => $states->filter(fn ($s) => $s === 'succeeded')->count(),
            'remaining' => $remaining, 'errors' => array_values(array_unique($errors))];
    }
}
