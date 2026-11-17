<?php

namespace Tests\Feature;

use App\Jobs\SyncMsg91SenderTemplates;
use App\Models\{Msg91WhatsAppSender as Sender, Msg91WhatsAppTemplate as Template, Msg91WhatsAppSenderTemplateBinding as Binding, Msg91WhatsAppCampaign as Campaign, Msg91WhatsAppCampaignRecipient as Recipient, User, UserType};
use App\Services\WhatsApp\Msg91\{Msg91Client, Msg91TemplateSyncService, Msg91CampaignTemplateResolver};
use Illuminate\Support\Facades\{Bus, DB, Http};
use Illuminate\Support\Str;
use Tests\Support\IsolatedWave2PostgresTestCase;

class Wave2Msg91TemplatesPostgresAcceptanceTest extends IsolatedWave2PostgresTestCase
{
    private array $provider = [];
    private array $deleteFailures = [];
    private array $fetchFailures = [];
    private array $calls = [];
    private ?array $createFailure = null;
    private int $number = 100;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.msg91_campaigns.template_create_enabled' => true,
            'services.msg91_campaigns.template_sync_enabled' => true, 'services.msg91_campaigns.dry_run' => false,
            'services.msg91_campaigns.enabled' => true, 'services.msg91_campaigns.auth_key' => 'synthetic-qa-not-a-provider-key',
            'services.msg91_campaigns.control_url' => 'https://control.msg91.com',
            'services.msg91_campaigns.template_create_url' => 'https://api.msg91.com',
            'services.msg91_campaigns.numbers_url' => 'https://control.msg91.com/qa-number-status',
            'services.msg91_campaigns.numbers_list_path' => 'data', 'services.msg91_campaigns.numbers_number_field' => 'number',
            'services.msg91_campaigns.numbers_status_field' => 'status', 'services.msg91_campaigns.connected_states' => ['connected']]);
        Bus::fake([SyncMsg91SenderTemplates::class]);
        Http::swap(new \Illuminate\Http\Client\Factory());
        // Explicit catch-all guard: unmatched traffic throws; no real HTTP is possible.
        Http::fake(function ($request) {
            $this->assertTrue($request->hasHeader('authkey', 'synthetic-qa-not-a-provider-key'));
            $this->assertSame(1, DB::transactionLevel(), 'Provider IO must not hold a service-owned transaction.');
            $this->calls[] = [$request->method(), $request->url()];
            if ($request->method() === 'GET' && $request->url() === 'https://control.msg91.com/qa-number-status') {
                return Http::response(['data' => Sender::where('integrated_number', 'like', '1202555%')->get()->map(fn ($s) => ['number' => $s->integrated_number, 'status' => 'connected'])->all()]);
            }
            if ($request->method() === 'GET' && str_starts_with($request->url(), 'https://control.msg91.com/api/v5/whatsapp/get-template-client/')) {
                $number = basename(parse_url($request->url(), PHP_URL_PATH));
                if (isset($this->fetchFailures[$number])) throw new \Illuminate\Http\Client\ConnectionException('Synthetic timeout');
                if (!array_key_exists($number, $this->provider)) throw new \RuntimeException('Unconfigured synthetic sender: outbound blocked');
                return Http::response(['data' => ['templates' => $this->provider[$number]]]);
            }
            if ($request->method() === 'DELETE' && str_starts_with($request->url(), 'https://control.msg91.com/api/v5/whatsapp/client-panel-template/')) {
                parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
                $number = $query['integrated_number'];
                if (($this->deleteFailures[$number] ?? null) === 'timeout') throw new \Illuminate\Http\Client\ConnectionException('Synthetic timeout');
                if (isset($this->deleteFailures[$number])) return Http::response($this->deleteFailures[$number][1], $this->deleteFailures[$number][0]);
                $this->provider[$number] = array_values(array_filter($this->provider[$number], fn ($row) => ($row['template_name'] ?? '') !== $query['template_name']));
                return Http::response(['status' => 'success']);
            }
            if ($request->method() === 'POST' && $request->url() === 'https://api.msg91.com/api/v5/whatsapp/client-panel-template/') {
                if ($this->createFailure) return Http::response($this->createFailure, 400);
                return Http::response(['status' => 'success', 'data' => ['template_id' => 'qa-provider-created']]);
            }
            throw new \RuntimeException('Unexpected outbound request blocked: '.$request->method());
        });
    }

    private function actor(string $role = UserType::SALES_EXECUTIVE): User
    {
        $type = UserType::firstOrCreate(['user_type' => $role], ['id' => (string) Str::uuid(), 'status' => 1]);
        return User::forceCreate(['id' => (string) Str::uuid(), 'name' => 'QA MSG91 '.$role,
            'email' => Str::uuid().'@wave1.invalid', 'password' => bcrypt('synthetic-only'), 'status' => 1, 'user_type_id' => $type->id]);
    }
    private function sender(?User $user = null): Sender
    {
        return Sender::create(['user_id' => ($user ?? $this->actor())->id, 'integrated_number' => '1202555'.sprintf('%04d', ++$this->number),
            'status' => 'active', 'verified_at' => now(), 'verification_meta' => ['source' => 'synthetic-provider']])->fresh();
    }
    private function template(string $name = 'qa_company_template', ?User $owner = null): Template
    {
        return Template::create(['name' => $name, 'language' => 'en', 'category' => 'UTILITY', 'scope' => $owner ? 'personal' : 'central',
            'owner_user_id' => $owner?->id, 'status' => 'approved', 'crm_enabled' => true, 'crm_visibility_overridden' => true,
            'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}']],
            'variable_schema' => ['body_1' => ['component' => 'body_1', 'field' => 'salesperson_name', 'type' => 'text']]])->fresh();
    }
    private function row(Template $template, string $status = 'approved', string $id = 'qa-provider-template'): array
    {
        return ['template_name' => $template->name, 'language' => $template->language, 'category' => $template->category,
            'id' => $id, 'status' => $status, 'components' => $template->components];
    }
    private function bind(Sender $sender, Template $template): Binding
    {
        $this->provider[$sender->integrated_number] = [$this->row($template)];
        return Binding::create(['sender_id' => $sender->id, 'template_id' => $template->id, 'provider_template_id' => 'qa-provider-template',
            'provider_status' => 'approved', 'last_synced_at' => now(), 'provider_meta' => ['integrated_number' => $sender->integrated_number]])->fresh();
    }
    private function campaign(Sender $sender, Template $template, string $status = 'completed'): Campaign
    {
        return Campaign::create(['created_by' => $sender->user_id, 'sender_id' => $sender->id, 'template_id' => $template->id,
            'name' => 'QA campaign history', 'status' => $status, 'selection_snapshot' => ['synthetic' => true]])->fresh();
    }
    private function deleteTemplate(Template $template)
    {
        return $this->actingAs($this->actor(UserType::SUPER_ADMIN))->deleteJson(route('admin.msg91.templates.destroy', $template));
    }

    public function test_verified_onboarding_dispatches_automatic_compatible_central_linking_without_overwriting_central_or_other_binding(): void
    {
        $template = $this->template(); $original = $template->getAttributes();
        $oldSender = $this->sender(); $old = $this->bind($oldSender, $template); $oldSnapshot = $old->getAttributes();
        $sender = $this->sender(); $sender->update(['verified_at' => null]);
        $this->provider[$sender->integrated_number] = [$this->row($template)];
        $this->actingAs($this->actor(UserType::SUPER_ADMIN))->post(route('admin.msg91.admin.refresh-senders'))->assertRedirect();
        Bus::assertDispatched(SyncMsg91SenderTemplates::class, fn ($job) => $job->senderId === $sender->id);
        (new SyncMsg91SenderTemplates($sender->id))->handle(app(Msg91TemplateSyncService::class));
        $this->assertSame('approved', $sender->bindings()->sole()->provider_status);
        $this->assertSame($template->id, $sender->bindings()->sole()->template_id);
        $this->assertSame($original, $template->fresh()->getAttributes());
        $this->assertSame($oldSnapshot, $old->fresh()->getAttributes());
        app(Msg91TemplateSyncService::class)->syncSender($sender->fresh());
        $this->assertSame(1, $sender->bindings()->count());
        $this->assertSame('synced', $sender->fresh()->template_sync_status);
        $this->assertSame([$template->id], app(Msg91CampaignTemplateResolver::class)->approvedFor($sender->user, $sender->fresh())->pluck('id')->all());
        $this->assertCount(0, array_filter($this->calls, fn ($call) => $call[0] !== 'GET'));
    }

    public function test_missing_pending_rejected_incompatible_and_incomplete_definitions_do_not_become_send_ready(): void
    {
        $template = $this->template(); $original = $template->getAttributes(); $sync = app(Msg91TemplateSyncService::class);
        foreach (['missing', 'pending', 'rejected', 'body', 'category', 'incomplete', 'duplicate'] as $kind) {
            $sender = $this->sender(); $row = $this->row($template);
            if (in_array($kind, ['pending', 'rejected'])) $row['status'] = $kind;
            if ($kind === 'body') $row['components'][0]['text'] = 'Different body';
            if ($kind === 'category') $row['category'] = 'MARKETING';
            if ($kind === 'incomplete') unset($row['components']);
            $this->provider[$sender->integrated_number] = $kind === 'missing' ? [] : ($kind === 'duplicate' ? [$row, $row] : [$row]);
            $sync->syncSender($sender);
            $this->assertSame(0, app(Msg91CampaignTemplateResolver::class)->approvedFor($sender->user, $sender)->count(), $kind);
        }
        $this->assertSame($original, $template->fresh()->getAttributes());
    }

    public function test_sender_change_inactive_unverified_and_timeout_fail_closed_and_remain_retryable(): void
    {
        $template = $this->template(); $sender = $this->sender(); $binding = $this->bind($sender, $template);
        $sender->update(['integrated_number' => '12025550199']);
        $this->assertNull($sender->fresh()->verified_at);
        $this->assertSame('stale', $binding->fresh()->provider_status);
        $this->assertSame(0, app(Msg91CampaignTemplateResolver::class)->approvedFor($sender->user, $sender)->count());
        foreach ([['status' => 'inactive', 'verified_at' => now()], ['status' => 'active', 'verified_at' => null]] as $state) {
            $sender->update($state);
            try { app(Msg91TemplateSyncService::class)->syncSender($sender); $this->fail('Unverified/inactive sync must fail'); }
            catch (\RuntimeException $e) { $this->assertStringContainsString('verified and active', $e->getMessage()); }
        }
        Http::assertNothingSent();
        $sender->update(['status' => 'active', 'verified_at' => now()]);
        $this->fetchFailures[$sender->integrated_number] = true;
        try { app(Msg91TemplateSyncService::class)->syncSender($sender); $this->fail('Timeout must fail'); } catch (\RuntimeException $e) {}
        $this->assertSame('failed', $sender->fresh()->template_sync_status);
        unset($this->fetchFailures[$sender->integrated_number]);
        $this->provider[$sender->integrated_number] = [$this->row($template)];
        app(Msg91TemplateSyncService::class)->syncSender($sender->fresh());
        $this->assertSame('approved', $binding->fresh()->provider_status);
    }

    public function test_personal_ownership_foreign_sender_and_forged_campaign_template_remain_restricted(): void
    {
        $owner = $this->actor(); $sender = $this->sender($owner); $foreign = $this->sender();
        $personal = $this->template('qa_personal', $owner); $this->bind($sender, $personal);
        $this->provider[$foreign->integrated_number] = [$this->row($personal)];
        app(Msg91TemplateSyncService::class)->syncSender($foreign);
        $this->assertSame($owner->id, $personal->fresh()->owner_user_id);
        $this->assertFalse($foreign->bindings()->where('template_id', $personal->id)->exists());
        $this->actingAs($foreign->user)->post(route('admin.msg91.templates.sync', $sender))->assertForbidden();
        $this->postJson(route('admin.msg91.campaigns.store'), ['name' => 'Forged', 'template_id' => $personal->id,
            'lead_ids' => [\App\Models\Lead::firstOrFail()->id]])->assertNotFound();
        $this->assertSame(0, app(Msg91CampaignTemplateResolver::class)->approvedFor($foreign->user, $sender)->count());
    }

    public function test_multi_sender_partial_deletion_is_durable_retryable_and_preserves_campaign_history(): void
    {
        $template = $this->template(); $first = $this->sender(); $second = $this->sender();
        $a = $this->bind($first, $template); $b = $this->bind($second, $template);
        // Service orders by sender ID; assign failure to the second attempted sender.
        $ordered = collect([$first, $second])->sortBy('id')->values();
        $this->deleteFailures[$ordered[1]->integrated_number] = [422, ['message' => 'Provider refused this synthetic template']];
        $campaign = $this->campaign($first, $template); $history = $campaign->getAttributes();
        $failed = $this->deleteTemplate($template)->assertUnprocessable()->assertJsonPath('complete', false);
        $this->assertSame(1, $failed->json('succeeded')); $this->assertSame(1, $failed->json('remaining'));
        $this->assertSame('deleting', $template->fresh()->status);
        $this->assertFalse($template->fresh()->crm_enabled);
        $this->assertSame(['failed', 'succeeded'], DB::table('msg91_whatsapp_template_deletions')->where('template_id', $template->id)->orderBy('state')->pluck('state')->all());
        $beforeCalls = count(array_filter($this->calls, fn ($call) => $call[0] === 'DELETE'));
        unset($this->deleteFailures[$ordered[1]->integrated_number]);
        $this->deleteTemplate($template)->assertOk()->assertJsonPath('complete', true);
        $this->assertSame($beforeCalls + 1, count(array_filter($this->calls, fn ($call) => $call[0] === 'DELETE')));
        $this->assertSame('deleted', $template->fresh()->status);
        $this->assertSame(2, $template->bindings()->count());
        $this->assertSame(['deleted'], $template->bindings()->pluck('provider_status')->unique()->values()->all());
        $this->assertSame($history, $campaign->fresh()->getAttributes());
        $this->assertSame('qa-provider-template', $a->fresh()->provider_template_id);
        $this->assertSame('qa-provider-template', $b->fresh()->provider_template_id);
        $this->provider[$first->integrated_number] = [$this->row($template)];
        app(Msg91TemplateSyncService::class)->syncSender($first);
        $this->assertSame('deleted', $template->fresh()->status);
        $this->assertSame('deleted', $a->fresh()->provider_status);
        $this->assertSame(0, app(Msg91CampaignTemplateResolver::class)->approvedFor($first->user, $first)->count());
        $this->deleteTemplate($template)->assertOk();
        $this->assertSame($beforeCalls + 1, count(array_filter($this->calls, fn ($call) => $call[0] === 'DELETE')));
    }

    public function test_ambiguous_timeout_generic_404_and_dry_run_never_report_success(): void
    {
        foreach (['timeout', '404', 'dry-run'] as $kind) {
            $template = $this->template('qa_delete_'.str_replace('-', '_', $kind)); $sender = $this->sender(); $this->bind($sender, $template);
            if ($kind === 'timeout') $this->deleteFailures[$sender->integrated_number] = 'timeout';
            if ($kind === '404') $this->deleteFailures[$sender->integrated_number] = [404, []];
            config(['services.msg91_campaigns.dry_run' => $kind === 'dry-run']);
            $this->deleteTemplate($template)->assertUnprocessable()->assertJsonPath('complete', false);
            $this->assertSame('unknown', DB::table('msg91_whatsapp_template_deletions')->where('template_id', $template->id)->value('state'));
            $this->assertSame('deleting', $template->fresh()->status);
            $this->assertNotSame('deleted', $sender->bindings()->sole()->provider_status);
            config(['services.msg91_campaigns.dry_run' => false]);
        }
    }

    public function test_provider_confirmed_absence_reconciles_without_delete_and_language_ambiguity_blocks_all_mutations(): void
    {
        $template = $this->template(); $sender = $this->sender(); $this->bind($sender, $template);
        $variant = $this->row($template); $variant['language'] = 'hi';
        $this->provider[$sender->integrated_number][] = $variant;
        $this->deleteTemplate($template)->assertUnprocessable();
        $this->assertSame('approved', $template->fresh()->status);
        $this->assertSame(0, DB::table('msg91_whatsapp_template_deletions')->where('template_id', $template->id)->count());
        $this->assertCount(0, array_filter($this->calls, fn ($call) => $call[0] === 'DELETE'));
        $this->provider[$sender->integrated_number] = [];
        $this->deleteTemplate($template)->assertOk()->assertJsonPath('complete', true);
        $this->assertCount(0, array_filter($this->calls, fn ($call) => $call[0] === 'DELETE'));
        $this->assertSame('deleted', $template->fresh()->status);
    }

    public function test_open_retryable_or_unresolved_campaigns_block_deletion_and_other_languages_block_name_only_deletion(): void
    {
        $template = $this->template(); $sender = $this->sender(); $this->bind($sender, $template);
        foreach (['queued', 'processing', 'retry_scheduled', 'unknown', 'failed'] as $status) {
            $campaign = $this->campaign($sender, $template, in_array($status, ['unknown', 'failed']) ? 'completed' : $status);
            $recipient = Recipient::create(['campaign_id' => $campaign->id, 'normalized_phone' => '12025550150', 'rendered_variables' => [],
                'status' => $status, 'attempts' => 0, 'failure_code' => 'sender_unavailable']);
            $this->deleteTemplate($template)->assertUnprocessable();
            $recipient->update(['status' => 'delivered']); $campaign->update(['status' => 'completed']);
        }
        Http::assertNothingSent();
        $other = $this->template($template->name); $other->update(['language' => 'hi']);
        $this->deleteTemplate($template)->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_all_roles_delete_authorization_and_super_admin_rendered_controls(): void
    {
        $template = $this->template(); $sender = $this->sender(); $this->bind($sender, $template);
        foreach (array_merge([UserType::ADMIN, UserType::OWNER, UserType::HR], UserType::ACCOUNTS_ROLES, UserType::OPERATIONS_ROLES, UserType::SALES_ROLES) as $role) {
            $this->actingAs($this->actor($role))->deleteJson(route('admin.msg91.templates.destroy', $template))->assertForbidden();
            if (!in_array($role, array_merge(UserType::ADMIN_ROLES, UserType::SALES_ROLES), true)) {
                $this->postJson(route('admin.msg91.templates.create'), [])->assertForbidden();
                $this->post(route('admin.msg91.templates.sync', $sender))->assertForbidden();
            }
        }
        Http::assertNothingSent();
        $page = $this->actingAs($this->actor(UserType::SUPER_ADMIN))->get(route('admin.msg91.templates.index'))->assertOk();
        $page->assertSee('data-msg91-open-delete-modal', false)->assertSee('data-sender-count="1"', false)
            ->assertSee('data-msg91-delete-sender-count', false)->assertSee('data-msg91-create-template-form', false);
        $this->assertMatchesRegularExpression('/<button\b[^>]*\bdata-msg91-open-delete-modal\b/', $page->getContent());
        file_put_contents(getenv('CRM_WAVE1_QA_ROOT').'/msg91-templates-super-admin.html', $page->getContent());
        $salesPage = $this->actingAs($sender->user)->get(route('admin.msg91.templates.index'))->assertOk();
        $this->assertDoesNotMatchRegularExpression('/<button\b[^>]*\bdata-msg91-open-delete-modal\b/', $salesPage->getContent());
        $salesPage->assertSee('data-msg91-create-template-form', false);
        file_put_contents(getenv('CRM_WAVE1_QA_ROOT').'/msg91-templates-sales.html', $salesPage->getContent());
    }

    public function test_central_and_personal_creation_keep_pending_approval_and_safe_400_feedback_and_visibility(): void
    {
        foreach ([UserType::SUPER_ADMIN, UserType::SALES_EXECUTIVE] as $role) {
            $actor = $this->actor($role); $sender = $this->sender($actor);
            $name = 'qa_create_'.str_replace('-', '_', (string) Str::uuid());
            $payload = ['sender_ids' => [$sender->id], 'name' => $name, 'language' => 'en', 'category' => 'UTILITY',
                'body' => 'Hello {{1}}', 'body_1' => 'customer_name'];
            $this->actingAs($actor)->postJson(route('admin.msg91.templates.create'), $payload)->assertCreated();
            $template = Template::where('name', $name)->sole();
            $this->assertSame($role === UserType::SUPER_ADMIN ? 'central' : 'personal', $template->scope);
            $this->assertSame($role === UserType::SUPER_ADMIN ? null : $actor->id, $template->owner_user_id);
            $this->assertSame('pending', $template->status);
            $this->assertSame('pending', $template->bindings()->sole()->provider_status);
            $this->assertSame(0, app(Msg91CampaignTemplateResolver::class)->approvedFor($actor, $sender)->count());
            $this->createFailure = ['errors' => [['message' => 'Invalid body; authkey=synthetic-qa-not-a-provider-key']]];
            $payload['name'] .= '_rejected';
            $this->postJson(route('admin.msg91.templates.create'), $payload)->assertUnprocessable()->assertSee('Invalid body')->assertDontSee('synthetic-qa-not-a-provider-key');
            $this->post(route('admin.msg91.templates.create'), $payload)->assertSessionHasErrors('msg91_template')->assertSessionHas('msg91_open_create_template', true);
            $this->assertFalse(Template::where('name', $payload['name'])->exists());
            $this->createFailure = null;
        }
        $admin = $this->actor(UserType::SUPER_ADMIN); $template = $this->template('qa_visibility');
        foreach ([false, true] as $enabled) {
            $this->actingAs($admin)->post(route('admin.msg91.templates.save'), ['template_id' => $template->id, 'crm_enabled' => $enabled])->assertRedirect();
            $this->assertSame($enabled, $template->fresh()->crm_enabled);
        }
    }

    public function test_simultaneous_reconciliation_uses_two_postgres_connections_without_duplicate_bindings(): void
    {
        $sender = $this->sender(); $userId = $sender->user_id;
        $template = $this->template('qa_concurrent_'.str_replace('-', '_', (string) Str::uuid()));
        $before = $template->getAttributes();
        DB::commit(); // Only newly created synthetic fixture IDs are visible to workers.
        $processes = []; $pipes = [];
        try {
            DB::beginTransaction();
            Sender::whereKey($sender->id)->lockForUpdate()->firstOrFail();
            foreach ([0, 1] as $index) {
                $processes[$index] = proc_open([PHP_BINARY, __DIR__.'/../Support/wave2_msg91_sync_worker.php', $sender->id, $template->id],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$index]);
                $this->assertIsResource($processes[$index]); fclose($pipes[$index][0]);
            }
            $waiting = 0;
            for ($attempt = 0; $attempt < 120 && $waiting < 2; $attempt++) {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $waiting = (int) DB::selectOne("SELECT count(*) AS n FROM pg_stat_activity WHERE application_name = 'msg91-sync-concurrency' AND wait_event_type = 'Lock'")->n;
                if ($waiting < 2) usleep(100000);
            }
            $this->assertSame(2, $waiting, 'Both independent workers must contend simultaneously.');
            DB::commit();
            $outputs = [];
            foreach ($processes as $index => $process) {
                $out = stream_get_contents($pipes[$index][1]); $error = stream_get_contents($pipes[$index][2]);
                fclose($pipes[$index][1]); fclose($pipes[$index][2]);
                $this->assertSame(0, proc_close($process), $error.$out);
                unset($processes[$index]);
                $outputs[] = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
            }
            $this->assertNotSame($outputs[0]['pid'], $outputs[1]['pid']);
            $this->assertSame([1, 1], array_column($outputs, 'count'));
            $this->assertSame(1, Binding::where('sender_id', $sender->id)->where('template_id', $template->id)->count());
            $this->assertSame($before, $template->fresh()->getAttributes());
            fwrite(STDOUT, json_encode(['msg91_concurrency' => $outputs, 'simultaneous_lock_waiters' => $waiting]).PHP_EOL);
        } finally {
            foreach ($processes as $process) if (is_resource($process)) proc_terminate($process);
            while (DB::transactionLevel()) DB::rollBack();
            $identity = DB::selectOne('SELECT current_database() AS db, current_user AS role');
            if ($identity->db !== 'accretion_wave1_qa' || $identity->role !== 'accretion_wave1_qa_role') throw new \RuntimeException('QA cleanup identity mismatch');
            DB::transaction(function () use ($sender, $template, $userId) {
                Binding::where('sender_id', $sender->id)->delete();
                Template::whereKey($template->id)->delete(); Sender::whereKey($sender->id)->delete(); User::whereKey($userId)->delete();
            });
        }
    }

    public function test_unknown_deletion_reconciles_confirmed_absence_without_resending_and_live_lease_rejects_duplicate(): void
    {
        $template = $this->template(); $sender = $this->sender(); $this->bind($sender, $template);
        $this->deleteFailures[$sender->integrated_number] = 'timeout';
        $this->deleteTemplate($template)->assertUnprocessable();
        $op = DB::table('msg91_whatsapp_template_deletions')->where('template_id', $template->id)->sole();
        $this->assertSame('unknown', $op->state);
        $this->assertSame(1, $op->attempts);
        DB::table('msg91_whatsapp_template_deletions')->where('id', $op->id)->update(['state' => 'in_progress', 'started_at' => now()]);
        $calls = count($this->calls);
        $this->deleteTemplate($template)->assertStatus(409);
        $this->assertCount($calls, $this->calls);
        DB::table('msg91_whatsapp_template_deletions')->where('id', $op->id)->update(['state' => 'unknown']);
        $this->provider[$sender->integrated_number] = []; // Provider read confirms earlier timed-out delete took effect.
        $this->deleteTemplate($template)->assertOk()->assertJsonPath('complete', true);
        $this->assertCount(1, array_filter($this->calls, fn ($call) => $call[0] === 'DELETE'));
        $done = DB::table('msg91_whatsapp_template_deletions')->where('id', $op->id)->sole();
        $this->assertSame('succeeded', $done->state);
        $audit = json_decode($done->audit_meta, true);
        $this->assertCount(2, $audit['attempts']);
        $this->assertTrue($audit['attempts'][1]['provider_confirmed_absent']);
        $this->assertNotEmpty($audit['attempts'][1]['actor_id']);
    }

    public function test_unsupported_components_and_sender_changes_during_fetch_cannot_become_send_ready(): void
    {
        $sender = $this->sender(); $template = $this->template();
        $template->update(['components' => [['type' => 'HEADER', 'format' => 'IMAGE'], ['type' => 'BODY', 'text' => 'Hello {{1}}']]]);
        $this->provider[$sender->integrated_number] = [$this->row($template)];
        app(Msg91TemplateSyncService::class)->syncSender($sender);
        $this->assertSame('unsupported', $sender->bindings()->sole()->provider_status);
        $this->assertSame(0, app(Msg91CampaignTemplateResolver::class)->approvedFor($sender->user, $sender)->count());
        // A real sender edit racing the read must invalidate the in-flight snapshot.
        $client = \Mockery::mock(Msg91Client::class);
        $client->shouldReceive('fetchTemplates')->once()->andReturnUsing(function () use ($sender, $template) {
            $sender->fresh()->update(['integrated_number' => '12025550198']);
            return [$this->row($template)];
        });
        try { (new Msg91TemplateSyncService($client))->syncSender($sender); $this->fail('Changed sender must reject old provider data'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Sender changed', $e->getMessage()); }
        $this->assertNull($sender->fresh()->verified_at);
        $this->assertSame('stale', $sender->bindings()->sole()->provider_status);
    }
}
