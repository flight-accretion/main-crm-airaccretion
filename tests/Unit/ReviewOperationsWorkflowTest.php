<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\User;
use App\Services\Operations\OperationCaseService;
use App\Services\Review\ReviewInboundRouter;
use App\Services\Review\ReviewWorkflowService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewOperationsWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    public function test_operation_cases_do_not_change_crm_lead_or_followup_status(): void
    {
        [$lead, $followup, $salesUser, $operationsUser] = $this->createLeadScenario();

        $service = app(OperationCaseService::class);

        $case = $service->open(
            $lead,
            'review',
            ['source' => 'test'],
            $salesUser->id
        );
        $duplicate = $service->open($lead, 'review', ['source' => 'test-again']);
        $started = $service->start($case, $operationsUser);
        $completed = $service->complete($started, $operationsUser, 'Handled by operations.');

        $this->assertSame($case->id, $duplicate->id);
        $this->assertSame('completed', $completed->status);
        $this->assertSame($operationsUser->id, $completed->assigned_to);
        $this->assertSame($operationsUser->id, $completed->completed_by);

        $this->assertDatabaseCount('operation_cases', 1);
        $this->assertDatabaseCount('operation_case_activities', 3);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'representative_user_id' => $salesUser->id,
        ]);

        $this->assertDatabaseHas('lead_followups', [
            'id' => $followup->id,
            'lead_id' => $lead->id,
            'status' => 5,
            'total_amount' => 125000,
            'received_amount' => 125000,
        ]);
    }

    public function test_completed_ride_review_workflow_opens_separate_review_case_once(): void
    {
        Queue::fake();

        [$lead, $followup, $salesUser] = $this->createLeadScenario();

        $review = app(ReviewWorkflowService::class)
            ->startForCompletedRide($lead);
        $second = app(ReviewWorkflowService::class)
            ->startForCompletedRide($lead);

        $this->assertSame($review->id, $second->id);
        $this->assertSame('waiting_for_reply', $review->status);
        $this->assertSame('9876543210', $review->customer_phone);
        $this->assertNotNull($review->operation_case_id);

        $this->assertDatabaseCount('operation_cases', 1);
        $this->assertDatabaseCount('review_conversations', 1);
        $this->assertDatabaseHas('operation_cases', [
            'id' => $review->operation_case_id,
            'lead_id' => $lead->id,
            'type' => 'review',
            'status' => 'pending',
        ]);

        Queue::assertPushed(
            \App\Jobs\Review\SendInitialReviewTemplate::class,
            fn ($job) => $job->reviewId === $review->id
        );

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'representative_user_id' => $salesUser->id,
        ]);
        $this->assertDatabaseHas('lead_followups', [
            'id' => $followup->id,
            'status' => 5,
        ]);
    }

    public function test_review_inbound_router_accepts_existing_incoming_direction(): void
    {
        Queue::fake();

        [$lead] = $this->createLeadScenario();

        $conversationId = (string) Str::uuid();
        $messageId = (string) Str::uuid();
        $reviewId = (string) Str::uuid();

        DB::table('whatsapp_contacts')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Review Customer',
            'normalized_phone' => '9876543210',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('whatsapp_conversations')->insert([
            'id' => $conversationId,
            'contact_id' => DB::table('whatsapp_contacts')->value('id'),
            'lead_id' => $lead->id,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('review_conversations')->insert([
            'id' => $reviewId,
            'lead_id' => $lead->id,
            'whatsapp_conversation_id' => $conversationId,
            'customer_phone' => '9876543210',
            'status' => 'waiting_for_reply',
            'customer_replied' => false,
            'needs_human' => false,
            'reminder_count' => 1,
            'next_reminder_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $message = \App\Models\WhatsAppMessage::create([
            'id' => $messageId,
            'conversation_id' => $conversationId,
            'direction' => 'incoming',
            'sender_type' => 'customer',
            'message_type' => 'image',
            'body' => 'Photo attached',
            'message_at' => now(),
        ]);

        app(ReviewInboundRouter::class)->handle($message);

        $this->assertDatabaseHas('review_conversations', [
            'id' => $reviewId,
            'customer_replied' => true,
            'next_reminder_at' => null,
        ]);

        Queue::assertPushed(
            \App\Jobs\Review\ProcessReviewInboundMessage::class,
            fn ($job) => $job->reviewId === $reviewId
                && $job->messageId === $messageId
        );
        Queue::assertPushed(
            \App\Jobs\Review\StoreReviewMediaToGoogleDrive::class,
            fn ($job) => $job->messageId === $messageId
                && $job->reviewId === $reviewId
        );
    }

    public function test_human_needed_review_opens_operations_case_and_notifies_ops_once(): void
    {
        [$lead, $followup, $salesUser, $operationsUser] = $this->createLeadScenario();
        $operationsUser->update(['contact_number' => '9876500000']);

        config()->set('review.notify_operations', true);
        config()->set('review.operations_template_name', 'ops_review_alert');
        config()->set('review.operations_recipient_user_ids', [$operationsUser->id]);
        config()->set('review.operations_recipient_numbers', []);

        $conversationId = (string) Str::uuid();
        $reviewId = (string) Str::uuid();
        $messageId = (string) Str::uuid();

        DB::table('whatsapp_contacts')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Review Customer',
            'normalized_phone' => '9876543210',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('whatsapp_conversations')->insert([
            'id' => $conversationId,
            'contact_id' => DB::table('whatsapp_contacts')->value('id'),
            'lead_id' => $lead->id,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Models\ReviewConversation::create([
            'id' => $reviewId,
            'lead_id' => $lead->id,
            'whatsapp_conversation_id' => $conversationId,
            'customer_phone' => '9876543210',
            'status' => 'waiting_for_reply',
            'customer_replied' => true,
            'needs_human' => false,
            'reminder_count' => 0,
        ]);

        \App\Models\WhatsAppMessage::create([
            'id' => $messageId,
            'conversation_id' => $conversationId,
            'direction' => 'incoming',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'body' => 'Bad experience, please call me.',
            'message_at' => now(),
        ]);

        $ai = new class extends \App\Services\Review\ReviewAiService {
            public function __construct()
            {
            }

            public function process(
                \App\Models\ReviewConversation $review,
                string $message
            ): array {
                return [
                    'sentiment' => 'negative',
                    'intent' => 'complaint',
                    'needs_human' => true,
                    'review_eligible' => false,
                    'operations_action' => 'review',
                    'summary' => 'Customer reported a bad experience.',
                    'reply' => '',
                ];
            }
        };

        $outbound = new class extends \App\Services\WhatCrmOutboundMessageService {
            public array $templates = [];

            public function __construct()
            {
            }

            public function sendTemplate(array $data): array
            {
                $this->templates[] = $data;

                return [
                    'success' => true,
                    'conversation_id' => (string) Str::uuid(),
                ];
            }

            public function sendText(array $data): array
            {
                return ['success' => true];
            }
        };

        $whatsApp = new \App\Services\Review\ReviewWhatsAppService($outbound);
        $job = new \App\Jobs\Review\ProcessReviewInboundMessage($reviewId, $messageId);

        $job->handle($ai, app(OperationCaseService::class), $whatsApp);
        $job->handle($ai, app(OperationCaseService::class), $whatsApp);

        $this->assertDatabaseCount('operation_cases', 1);
        $this->assertDatabaseHas('operation_cases', [
            'lead_id' => $lead->id,
            'type' => 'review',
            'status' => 'pending',
        ]);

        $this->assertNotNull(
            \App\Models\ReviewConversation::find($reviewId)->operations_notified_at
        );
        $this->assertCount(1, $outbound->templates);
        $this->assertSame('9876500000', $outbound->templates[0]['number']);
        $this->assertSame('ops_review_alert', $outbound->templates[0]['template_name']);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'representative_user_id' => $salesUser->id,
        ]);
        $this->assertDatabaseHas('lead_followups', [
            'id' => $followup->id,
            'status' => 5,
            'total_amount' => 125000,
            'received_amount' => 125000,
        ]);
    }

    private function createLeadScenario(): array
    {
        $salesUser = User::create([
            'name' => 'Sales Owner',
            'email' => Str::uuid() . '@example.test',
            'password' => 'secret',
            'status' => 1,
        ]);

        $operationsUser = User::create([
            'name' => 'Operations User',
            'email' => Str::uuid() . '@example.test',
            'password' => 'secret',
            'status' => 1,
        ]);

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Review Customer',
            'contact_number' => '+91 98765 43210',
            'status' => 1,
        ]);

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $salesUser->id,
            'service_ids' => [],
            'product_ids' => [],
        ]);

        $followup = LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'status' => 5,
            'followup_note' => 'Ride has been completed successfully.',
            'followed_by' => $salesUser->id,
            'total_amount' => 125000,
            'received_amount' => 125000,
            'next_followup_date' => null,
        ]);

        return [$lead, $followup, $salesUser, $operationsUser];
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('contact_number')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('contact_number')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable();
            $table->uuid('representative_user_id')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->integer('status')->nullable();
            $table->uuid('followed_by')->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->decimal('received_amount', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('ai_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('agent_type', 50)->default('generic');
            $table->uuid('ai_model_profile_id')->nullable();
            $table->text('prompt')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('operation_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->string('type', 30);
            $table->string('status', 30)->default('pending');
            $table->uuid('assigned_to')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('completed_by')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('operation_case_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_case_id');
            $table->uuid('lead_id');
            $table->uuid('user_id')->nullable();
            $table->string('action', 60);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('review_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->uuid('operation_case_id')->nullable();
            $table->uuid('ai_agent_id')->nullable();
            $table->uuid('whatsapp_conversation_id')->nullable();
            $table->string('customer_phone', 30);
            $table->string('status', 40)->default('waiting_for_reply');
            $table->string('sentiment', 20)->nullable();
            $table->string('intent', 30)->nullable();
            $table->boolean('customer_replied')->default(false);
            $table->boolean('needs_human')->default(false);
            $table->unsignedTinyInteger('reminder_count')->default(0);
            $table->timestamp('initial_message_sent_at')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamp('next_reminder_at')->nullable();
            $table->timestamp('review_link_sent_at')->nullable();
            $table->timestamp('operations_notified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('ai_state')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('normalized_phone', 30)->unique();
            $table->string('raw_phone', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contact_id');
            $table->uuid('lead_id')->nullable();
            $table->uuid('assigned_user_id')->nullable();
            $table->string('status', 30)->default('open');
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->uuid('lead_followup_id')->nullable();
            $table->uuid('ai_reply_batch_id')->nullable();
            $table->timestamp('ai_processed_at')->nullable();
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('direction', 20);
            $table->string('sender_type', 30);
            $table->uuid('sender_user_id')->nullable();
            $table->string('message_type', 30)->default('text');
            $table->text('body')->nullable();
            $table->string('provider_status', 50)->nullable();
            $table->timestamp('message_at')->nullable();
            $table->timestamp('crm_read_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('media_provider')->nullable();
            $table->string('media_provider_id')->nullable();
            $table->string('media_mime_type')->nullable();
            $table->string('media_file_name')->nullable();
            $table->string('google_drive_file_id')->nullable();
            $table->text('google_drive_view_url')->nullable();
            $table->timestamps();
        });
    }
}
