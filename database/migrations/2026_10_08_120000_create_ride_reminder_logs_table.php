<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ride_reminder_logs')) {
            Schema::create('ride_reminder_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('ride_id');
                $table->uuid('lead_id')->nullable();
                $table->unsignedSmallInteger('hours_before');
                $table->string('channel', 30);
                $table->string('recipient')->nullable();
                $table->string('status')->default('pending');
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->unique(['ride_id', 'hours_before', 'channel'], 'ride_reminder_logs_unique_delivery');
                $table->index('ride_id');
                $table->index('lead_id');
                $table->index('status');
            });

            return;
        }

        Schema::table('ride_reminder_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('ride_reminder_logs', 'hours_before')) {
                $table->unsignedSmallInteger('hours_before')->nullable()->after('lead_id');
            }

            if (!Schema::hasColumn('ride_reminder_logs', 'channel')) {
                $table->string('channel', 30)->nullable()->after('hours_before');
            }

            if (!Schema::hasColumn('ride_reminder_logs', 'recipient')) {
                $table->string('recipient')->nullable()->after('channel');
            }

            if (!Schema::hasColumn('ride_reminder_logs', 'status')) {
                $table->string('status')->default('pending')->after('recipient');
            }

            if (!Schema::hasColumn('ride_reminder_logs', 'error_message')) {
                $table->text('error_message')->nullable()->after('status');
            }

            if (!Schema::hasColumn('ride_reminder_logs', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('error_message');
            }
        });

        if (Schema::hasColumn('ride_reminder_logs', 'reminder_type')) {
            DB::table('ride_reminder_logs')
                ->whereNull('hours_before')
                ->update(['hours_before' => DB::raw('reminder_type')]);
        }

        if (Schema::hasColumn('ride_reminder_logs', 'template_name')) {
            DB::table('ride_reminder_logs')
                ->whereNull('channel')
                ->whereNotNull('template_name')
                ->update(['channel' => 'booking']);
        }

        DB::table('ride_reminder_logs')
            ->whereNull('channel')
            ->update(['channel' => 'booking']);

        DB::table('ride_reminder_logs')
            ->whereNull('status')
            ->update(['status' => 'sent']);

        try {
            Schema::table('ride_reminder_logs', function (Blueprint $table) {
                $table->unique(['ride_id', 'hours_before', 'channel'], 'ride_reminder_logs_unique_delivery');
            });
        } catch (\Throwable $e) {
            // Existing duplicate data or index already present should not block production deployment.
        }

        try {
            Schema::table('ride_reminder_logs', function (Blueprint $table) {
                $table->index('status');
            });
        } catch (\Throwable $e) {
            // Index may already exist.
        }
    }

    public function down(): void
    {
        // No-op by design. This migration repairs production schema and must not
        // delete ride reminder delivery history during rollback.
    }
};
