<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappAiReplyBatchesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('whatsapp_ai_reply_batches')) {
            Schema::create(
                'whatsapp_ai_reply_batches',
                function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->uuid('conversation_id');
                    $table->string('status', 30)->default('pending');
                    $table->timestamp('process_after')->nullable();
                    $table->timestamp('locked_at')->nullable();
                    $table->timestamp('processed_at')->nullable();
                    $table->uuid('response_message_id')->nullable();
                    $table->uuid('assigned_user_id')->nullable();
                    $table->string('detected_product')->nullable();
                    $table->text('error')->nullable();
                    $table->json('message_ids')->nullable();
                    $table->timestamps();

                    $table->index('conversation_id');
                    $table->index('status');
                    $table->index('process_after');
                    $table->index('assigned_user_id');

                    $table->foreign('conversation_id')
                        ->references('id')
                        ->on('whatsapp_conversations')
                        ->cascadeOnDelete();

                    $table->foreign('assigned_user_id')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
            );
        }
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_ai_reply_batches');
    }
}
