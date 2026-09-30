<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSkyrackLeadCancellationRequestsTable extends Migration
{
    public function up(): void
    {
        Schema::create('skyrack_lead_cancellation_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('integration', 64)->default('skyrack');
            $table->uuid('request_id');
            $table->uuid('lead_id')->index();
            $table->string('actor_key', 128);
            $table->char('payload_hash', 64);
            $table->string('state', 24)->default('processing');
            $table->unsignedSmallInteger('http_code')->nullable();
            $table->json('response_json')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['integration', 'request_id'], 'skyrack_cancel_request_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skyrack_lead_cancellation_requests');
    }
}
