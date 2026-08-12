<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadTransfersTable extends Migration
{
    public function up()
    {
        Schema::create('lead_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('lead_id');

            $table->uuid('from_user_id');

            $table->uuid('to_user_id');

            $table->uuid('requested_by');

            $table->string('status', 20)
                ->default('pending');

            $table->text('reason')
                ->nullable();

            $table->text('response_note')
                ->nullable();

            $table->timestamp('responded_at')
                ->nullable();

            $table->uuid('responded_by')
                ->nullable();

            $table->timestamps();

            $table->foreign('lead_id')
                ->references('id')
                ->on('leads')
                ->onDelete('cascade');

            $table->foreign('from_user_id')
                ->references('id')
                ->on('users');

            $table->foreign('to_user_id')
                ->references('id')
                ->on('users');

            $table->foreign('requested_by')
                ->references('id')
                ->on('users');

            $table->foreign('responded_by')
                ->references('id')
                ->on('users');

            $table->index([
                'to_user_id',
                'status'
            ]);

            $table->index([
                'lead_id',
                'status'
            ]);
        });
    }

    public function down()
    {
        Schema::dropIfExists('lead_transfers');
    }
}