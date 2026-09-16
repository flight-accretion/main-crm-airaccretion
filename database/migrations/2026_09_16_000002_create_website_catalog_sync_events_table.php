<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('website_catalog_sync_events')) {
            return;
        }

        Schema::create(
            'website_catalog_sync_events',
            function (Blueprint $table) {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->string('event_type', 80);
                $table->unsignedBigInteger('website_service_type_id')
                    ->index();
                $table->uuid('product_id')->nullable()->index();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('website_catalog_sync_events');
    }
};
