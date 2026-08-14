<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappLeadIntegrationsTable extends Migration
{
    public function up()
    {
        Schema::create(
            'whatsapp_lead_integrations',
            function (Blueprint $table) {

                $table->uuid('id')->primary();

                $table->uuid('lead_id')->unique();

                $table->uuid('product_id')->nullable()->index();

                $table->string('phone', 30)->index();

                $table->string('external_id')
                    ->nullable()
                    ->unique();

                $table->string('status', 50)
                    ->default('received');

                $table->uuid('assigned_user_id')
                    ->nullable()
                    ->index();

                $table->boolean('callback_sent')
                    ->default(false);

                $table->unsignedInteger('callback_attempts')
                    ->default(0);

                $table->text('callback_error')
                    ->nullable();

                $table->json('payload')
                    ->nullable();

                $table->timestamp('assigned_at')
                    ->nullable();

                $table->timestamps();

                $table->foreign('lead_id')
                    ->references('id')
                    ->on('leads')
                    ->cascadeOnDelete();

                $table->foreign('product_id')
                    ->references('id')
                    ->on('products')
                    ->nullOnDelete();

                $table->foreign('assigned_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        );
    }

    public function down()
    {
        Schema::dropIfExists(
            'whatsapp_lead_integrations'
        );
    }
}