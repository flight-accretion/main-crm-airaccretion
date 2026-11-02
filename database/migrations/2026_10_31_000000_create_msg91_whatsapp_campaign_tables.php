<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMsg91WhatsappCampaignTables extends Migration
{
    public function up()
    {
        Schema::create('msg91_whatsapp_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('status', 20)->default('inactive');
            $table->timestamp('expires_at')->nullable();
            $table->uuid('activated_by')->nullable();
            $table->string('payment_reference', 191)->nullable();
            $table->json('audit_meta')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('activated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('msg91_whatsapp_senders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('integrated_number', 30)->unique();
            $table->string('status', 20)->default('inactive');
            $table->timestamp('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->json('verification_meta')->nullable();
            $table->timestamps();
            $table->index(['status', 'verified_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('msg91_whatsapp_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_user_id')->nullable();
            $table->string('scope', 20)->default('central');
            $table->string('name', 191);
            $table->string('language', 32);
            $table->string('category', 64)->nullable();
            $table->string('provider_template_id', 191)->nullable();
            $table->string('status', 30)->default('draft');
            $table->boolean('crm_enabled')->default(false);
            $table->json('components')->nullable();
            $table->json('variable_schema')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['owner_user_id', 'name', 'language']);
            $table->index(['status', 'crm_enabled', 'last_synced_at']);
            $table->foreign('owner_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('msg91_whatsapp_sender_template_bindings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sender_id');
            $table->uuid('template_id');
            $table->string('provider_template_id', 191)->nullable();
            $table->string('provider_status', 30)->default('unknown');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('provider_meta')->nullable();
            $table->timestamps();
            $table->unique(['sender_id', 'template_id']);
            $table->index(['provider_status', 'last_synced_at']);
            $table->foreign('sender_id')->references('id')->on('msg91_whatsapp_senders')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('msg91_whatsapp_templates')->cascadeOnDelete();
        });

        Schema::create('msg91_whatsapp_suppressions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('normalized_phone', 30)->unique();
            $table->string('reason', 191)->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('msg91_whatsapp_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('created_by');
            $table->uuid('sender_id');
            $table->uuid('template_id');
            $table->string('name', 191);
            $table->string('status', 30)->default('queued');
            $table->json('selection_snapshot');
            $table->unsignedInteger('selected_count')->default(0);
            $table->unsignedInteger('eligible_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->unsignedInteger('suppressed_count')->default(0);
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('unknown_count')->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->uuid('cancelled_by')->nullable();
            $table->timestamps();
            $table->index(['created_by', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('sender_id')->references('id')->on('msg91_whatsapp_senders')->restrictOnDelete();
            $table->foreign('template_id')->references('id')->on('msg91_whatsapp_templates')->restrictOnDelete();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('msg91_whatsapp_campaign_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('campaign_id');
            $table->uuid('lead_id')->nullable();
            $table->uuid('client_id')->nullable();
            $table->string('normalized_phone', 30);
            $table->json('rendered_variables');
            $table->string('status', 30)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('provider_request_id', 191)->nullable();
            $table->string('provider_message_id', 191)->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'normalized_phone']);
            $table->index(['campaign_id', 'status']);
            $table->index('provider_request_id');
            $table->index('provider_message_id');
            $table->foreign('campaign_id')->references('id')->on('msg91_whatsapp_campaigns')->cascadeOnDelete();
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });

        Schema::create('msg91_whatsapp_campaign_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('campaign_recipient_id')->nullable();
            $table->uuid('campaign_id')->nullable();
            $table->string('event_type', 50);
            $table->string('status', 30)->nullable();
            $table->string('provider_event_id', 191)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->unique(['provider_event_id', 'event_type']);
            $table->index(['campaign_id', 'created_at']);
            $table->foreign('campaign_recipient_id')->references('id')->on('msg91_whatsapp_campaign_recipients')->nullOnDelete();
            $table->foreign('campaign_id')->references('id')->on('msg91_whatsapp_campaigns')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('msg91_whatsapp_campaign_events');
        Schema::dropIfExists('msg91_whatsapp_campaign_recipients');
        Schema::dropIfExists('msg91_whatsapp_campaigns');
        Schema::dropIfExists('msg91_whatsapp_suppressions');
        Schema::dropIfExists('msg91_whatsapp_sender_template_bindings');
        Schema::dropIfExists('msg91_whatsapp_templates');
        Schema::dropIfExists('msg91_whatsapp_senders');
        Schema::dropIfExists('msg91_whatsapp_subscriptions');
    }
}
