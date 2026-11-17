<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('msg91_whatsapp_senders', function (Blueprint $table) {
            $table->string('template_sync_status', 30)->default('pending');
            $table->timestamp('templates_synced_at')->nullable();
            $table->string('template_sync_error', 500)->nullable();
        });
        Schema::create('msg91_whatsapp_template_deletions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_id');
            $table->uuid('sender_id');
            $table->uuid('binding_id');
            $table->string('integrated_number', 30);
            $table->string('template_name', 191);
            $table->uuid('requested_by');
            $table->string('state', 30)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->uuid('attempt_token')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('audit_meta')->nullable();
            $table->timestamps();
            $table->unique(['template_id', 'integrated_number'], 'msg91_template_delete_identity');
            $table->foreign('template_id')->references('id')->on('msg91_whatsapp_templates')->restrictOnDelete();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('msg91_whatsapp_template_deletions');
        Schema::table('msg91_whatsapp_senders', fn (Blueprint $table) => $table->dropColumn(['template_sync_status', 'templates_synced_at', 'template_sync_error']));
    }
};
