<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadChatTables extends Migration
{
   public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | One permanent chat conversation per Lead
        |--------------------------------------------------------------------------
        */
        Schema::create('lead_chat_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('lead_id')
                ->unique();

            $table->timestamp('last_message_at')
                ->nullable()
                ->index();

            $table->timestamps();
        });


        /*
        |--------------------------------------------------------------------------
        | Chat tasks
        |--------------------------------------------------------------------------
        */
        Schema::create('lead_chat_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('conversation_id')
                ->index();

            $table->uuid('lead_id')
                ->index();

            $table->string('title', 255);

            $table->text('description')
                ->nullable();

            $table->string('priority', 20)
                ->default('normal')
                ->index();

            $table->string('status', 20)
                ->default('active')
                ->index();

            /*
             * sales | operations
             */
            $table->string('assigned_role', 30)
                ->nullable()
                ->index();

            $table->uuid('assigned_user_id')
                ->nullable()
                ->index();

            $table->timestamp('due_at')
                ->nullable()
                ->index();

            $table->uuid('created_by')
                ->index();

            $table->uuid('completed_by')
                ->nullable()
                ->index();

            $table->timestamp('completed_at')
                ->nullable();

            $table->timestamps();
        });


        /*
        |--------------------------------------------------------------------------
        | Messages
        |--------------------------------------------------------------------------
        */
        Schema::create('lead_chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('conversation_id')
                ->index();

            $table->uuid('lead_id')
                ->index();

            $table->uuid('sender_user_id')
                ->nullable()
                ->index();

            /*
             * text | task | system
             */
            $table->string('message_type', 20)
                ->default('text')
                ->index();

            $table->text('body')
                ->nullable();

            $table->uuid('reply_to_message_id')
                ->nullable()
                ->index();

            $table->uuid('task_id')
                ->nullable()
                ->index();

            $table->boolean('is_pinned')
                ->default(false)
                ->index();

            $table->uuid('pinned_by')
                ->nullable();

            $table->timestamp('pinned_at')
                ->nullable();

            $table->timestamp('edited_at')
                ->nullable();

            /*
             * Keep audit record.
             * Do not physically delete chat messages.
             */
            $table->timestamp('deleted_at')
                ->nullable()
                ->index();

            $table->timestamps();

            $table->index([
                'conversation_id',
                'created_at',
            ]);
        });


        /*
        |--------------------------------------------------------------------------
        | Attachments
        |--------------------------------------------------------------------------
        */
        Schema::create('lead_chat_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('message_id')
                ->index();

            $table->uuid('uploaded_by')
                ->index();

            $table->string('file_name', 255);

            $table->string('disk', 50)
                ->default('local');

            $table->string('path', 1000);

            $table->string('mime_type', 150)
                ->nullable();

            $table->unsignedBigInteger('size_bytes')
                ->nullable();

            $table->timestamps();
        });


        /*
        |--------------------------------------------------------------------------
        | Per-user read position
        |--------------------------------------------------------------------------
        */
        Schema::create('lead_chat_reads', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('conversation_id')
                ->index();

            $table->uuid('user_id')
                ->index();

            $table->uuid('last_read_message_id')
                ->nullable();

            $table->timestamp('last_read_at')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'conversation_id',
                'user_id',
            ]);
        });


        /*
        |--------------------------------------------------------------------------
        | Header notification cards
        |--------------------------------------------------------------------------
        */
        Schema::create('lead_chat_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id')
                ->index();

            $table->uuid('lead_id')
                ->index();

            $table->uuid('conversation_id')
                ->index();

            $table->uuid('message_id')
                ->nullable()
                ->index();

            $table->uuid('task_id')
                ->nullable()
                ->index();

            $table->string('type', 50)
                ->index();

            $table->string('title', 255);

            $table->text('body')
                ->nullable();

            /*
             * read != clear
             *
             * read_at:
             * notification remains visible
             *
             * cleared_at:
             * hidden from this user's notification popup
             */
            $table->timestamp('read_at')
                ->nullable()
                ->index();

            $table->timestamp('cleared_at')
                ->nullable()
                ->index();

            $table->timestamps();

            $table->index([
                'user_id',
                'read_at',
                'cleared_at',
            ]);
        });


        /*
        |--------------------------------------------------------------------------
        | Emoji reactions
        |--------------------------------------------------------------------------
        */
        Schema::create('lead_chat_reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('message_id')
                ->index();

            $table->uuid('user_id')
                ->index();

            $table->string('reaction', 20);

            $table->timestamps();

            $table->unique([
                'message_id',
                'user_id',
                'reaction',
            ]);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('lead_chat_reactions');

        Schema::dropIfExists('lead_chat_notifications');

        Schema::dropIfExists('lead_chat_reads');

        Schema::dropIfExists('lead_chat_attachments');

        Schema::dropIfExists('lead_chat_messages');

        Schema::dropIfExists('lead_chat_tasks');

        Schema::dropIfExists('lead_chat_conversations');
    }
}
