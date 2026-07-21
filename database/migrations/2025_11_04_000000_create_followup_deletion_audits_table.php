<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFollowupDeletionAuditsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('followup_deletion_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('followup_id')->index();
            $table->uuid('lead_id')->nullable()->index();
            $table->uuid('deleted_by_user_id')->nullable()->index();
            $table->uuid('created_by_user_id')->nullable()->index();
            $table->timestamp('deleted_at')->nullable();
            $table->text('deletion_reason')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('followup_deletion_audits');
    }
}
