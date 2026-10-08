<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_audits')) {
            Schema::table('activity_audits', function (Blueprint $table) {
                if (!Schema::hasColumn('activity_audits', 'actor_id')) {
                    $table->uuid('actor_id')->nullable()->index();
                }
                if (!Schema::hasColumn('activity_audits', 'actor_name')) {
                    $table->string('actor_name')->nullable();
                }
                if (!Schema::hasColumn('activity_audits', 'actor_role')) {
                    $table->string('actor_role')->nullable();
                }
                if (!Schema::hasColumn('activity_audits', 'module')) {
                    $table->string('module', 80)->nullable();
                }
                if (!Schema::hasColumn('activity_audits', 'action')) {
                    $table->string('action', 120)->nullable();
                }
                if (!Schema::hasColumn('activity_audits', 'entity_id')) {
                    $table->uuid('entity_id')->nullable()->index();
                }
                if (!Schema::hasColumn('activity_audits', 'entity_type')) {
                    $table->string('entity_type')->nullable();
                }
                if (!Schema::hasColumn('activity_audits', 'lead_id')) {
                    $table->uuid('lead_id')->nullable()->index();
                }
                if (!Schema::hasColumn('activity_audits', 'client_id')) {
                    $table->uuid('client_id')->nullable()->index();
                }
                if (!Schema::hasColumn('activity_audits', 'old_values')) {
                    $table->json('old_values')->nullable();
                }
                if (!Schema::hasColumn('activity_audits', 'new_values')) {
                    $table->json('new_values')->nullable();
                }
                if (!Schema::hasColumn('activity_audits', 'meta')) {
                    $table->json('meta')->nullable();
                }
                if (!Schema::hasColumn('activity_audits', 'ip_address')) {
                    $table->ipAddress('ip_address')->nullable();
                }
                if (!Schema::hasColumn('activity_audits', 'user_agent')) {
                    $table->string('user_agent', 500)->nullable();
                }
                if (!Schema::hasColumn('activity_audits', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('activity_audits', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });

            try {
                Schema::table('activity_audits', function (Blueprint $table) {
                    $table->index(['module', 'action']);
                    $table->index(['lead_id', 'created_at']);
                });
            } catch (\Throwable $e) {
                report($e);
            }

            return;
        }

        Schema::create('activity_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('module', 80);
            $table->string('action', 120);
            $table->uuid('entity_id')->nullable();
            $table->string('entity_type')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->uuid('client_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('meta')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['module', 'action']);
            $table->index('actor_id');
            $table->index('entity_id');
            $table->index('lead_id');
            $table->index('client_id');
            $table->index('created_at');
            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_audits');
    }
};
