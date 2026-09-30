<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGoogleSyncToLeadChat extends Migration
{
      public function up(): void
    {
        Schema::table(
            'lead_chat_conversations',
            function (Blueprint $table) {

                $table->string(
                    'google_space_name',
                    255
                )
                    ->nullable();

                $table->string(
                    'google_thread_name',
                    500
                )
                    ->nullable()
                    ->unique();

                $table->string(
                    'google_thread_key',
                    255
                )
                    ->nullable()
                    ->unique();

                $table->timestamp(
                    'google_synced_at'
                )
                    ->nullable();
            }
        );


        Schema::table(
            'lead_chat_messages',
            function (Blueprint $table) {

                /*
                 * crm
                 * google_chat
                 */
                $table->string(
                    'source',
                    30
                )
                    ->default('crm')
                    ->index();


                $table->string(
                    'google_message_name',
                    500
                )
                    ->nullable()
                    ->unique();


                $table->string(
                    'google_sender_name',
                    500
                )
                    ->nullable();


                $table->timestamp(
                    'google_create_time'
                )
                    ->nullable();


                $table->timestamp(
                    'google_update_time'
                )
                    ->nullable();


                /*
                 * pending
                 * syncing
                 * synced
                 * failed
                 * not_required
                 */
                $table->string(
                    'google_sync_status',
                    30
                )
                    ->nullable()
                    ->index();


                $table->text(
                    'google_sync_error'
                )
                    ->nullable();
            }
        );


        Schema::create(
            'google_chat_subscriptions',
            function (Blueprint $table) {

                $table->uuid('id')
                    ->primary();

                $table->string(
                    'google_name',
                    500
                )
                    ->nullable()
                    ->unique();

                $table->string(
                    'target_resource',
                    500
                )
                    ->nullable();

                $table->timestamp(
                    'expire_time'
                )
                    ->nullable()
                    ->index();

                $table->timestamp(
                    'last_renewed_at'
                )
                    ->nullable();

                $table->string(
                    'status',
                    30
                )
                    ->default('pending')
                    ->index();

                $table->text(
                    'last_error'
                )
                    ->nullable();

                $table->timestamps();
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'google_chat_subscriptions'
        );


        Schema::table(
            'lead_chat_messages',
            function (Blueprint $table) {

                $table->dropColumn([

                    'source',

                    'google_message_name',

                    'google_sender_name',

                    'google_create_time',

                    'google_update_time',

                    'google_sync_status',

                    'google_sync_error',
                ]);
            }
        );


        Schema::table(
            'lead_chat_conversations',
            function (Blueprint $table) {

                $table->dropColumn([

                    'google_space_name',

                    'google_thread_name',

                    'google_thread_key',

                    'google_synced_at',
                ]);
            }
        );
    }
}
