<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_messages')) {
            return;
        }

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_messages', 'media_provider')) {
                $table->string('media_provider')->nullable();
            }

            if (!Schema::hasColumn('whatsapp_messages', 'media_provider_id')) {
                $table->string('media_provider_id')->nullable();
            }

            if (!Schema::hasColumn('whatsapp_messages', 'media_mime_type')) {
                $table->string('media_mime_type')->nullable();
            }

            if (!Schema::hasColumn('whatsapp_messages', 'media_file_name')) {
                $table->string('media_file_name')->nullable();
            }

            if (!Schema::hasColumn('whatsapp_messages', 'google_drive_file_id')) {
                $table->string('google_drive_file_id')->nullable();
            }

            if (!Schema::hasColumn('whatsapp_messages', 'google_drive_view_url')) {
                $table->text('google_drive_view_url')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('whatsapp_messages')) {
            return;
        }

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            foreach ([
                'google_drive_view_url',
                'google_drive_file_id',
                'media_file_name',
                'media_mime_type',
                'media_provider_id',
                'media_provider',
            ] as $column) {
                if (Schema::hasColumn('whatsapp_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
