<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCallSummaryIntegrationsTable extends Migration
{
    public function up()
    {
        Schema::create('call_summary_integrations', function (Blueprint $table) {

            $table->uuid('id')->primary();

            /*
            |--------------------------------------------------------------------------
            | Idempotency
            |--------------------------------------------------------------------------
            |
            | Third-party does not provide a reliable call ID.
            | CRM generates this fingerprint from:
            |
            | phone + agent + start + end + direction
            |
            */
            $table->string('call_fingerprint', 64)->unique();

            /*
            |--------------------------------------------------------------------------
            | Incoming call information
            |--------------------------------------------------------------------------
            */

            $table->string('phone_number', 50);

            $table->string(
                'normalized_phone',
                20
            )->nullable();

            $table->text('summary');

            $table->timestamp(
                'followup_date'
            )->nullable();

            $table->timestamp(
                'call_start_at'
            );

            $table->timestamp(
                'call_end_at'
            );

            $table->string(
                'agent_name',
                150
            );

            $table->string(
                'normalized_agent_name',
                150
            )->nullable();

            $table->string(
                'direction',
                20
            );

            $table->decimal(
                'sentiment_score',
                5,
                2
            )->nullable();


            /*
            |--------------------------------------------------------------------------
            | Matching result
            |--------------------------------------------------------------------------
            */

            $table->uuid(
                'ivr_call_log_id'
            )->nullable();

            $table->uuid(
                'lead_id'
            )->nullable();

            $table->uuid(
                'agent_user_id'
            )->nullable();

            $table->uuid(
                'followup_id'
            )->nullable();


            /*
            |--------------------------------------------------------------------------
            | Matching information
            |--------------------------------------------------------------------------
            */

            $table->integer(
                'match_score'
            )->nullable();

            $table->string(
                'match_method',
                100
            )->nullable();


            /*
            |--------------------------------------------------------------------------
            | Processing state
            |--------------------------------------------------------------------------
            */

            $table->string(
                'status',
                50
            )->default('received');

            $table->integer(
                'attempt_count'
            )->default(0);

            $table->text(
                'last_error'
            )->nullable();

            $table->timestamp(
                'processed_at'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Original request
            |--------------------------------------------------------------------------
            */

            $table->json(
                'payload'
            )->nullable();

            $table->timestamps();


            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                'normalized_phone'
            );

            $table->index(
                'normalized_agent_name'
            );

            $table->index(
                'call_start_at'
            );

            $table->index(
                'status'
            );

            $table->index(
                'lead_id'
            );

            $table->index(
                'ivr_call_log_id'
            );

            $table->index(
                'agent_user_id'
            );
        });
    }


    public function down()
    {
        Schema::dropIfExists(
            'call_summary_integrations'
        );
    }
}