<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmailCharterOwnerToLeadAllocationSettings extends Migration
{
    public function up()
    {
        Schema::table('lead_allocation_settings', function (Blueprint $table) {
            $table->uuid('email_charter_owner_user_id')
                ->nullable()
                ->after('allocation_method');

            $table->foreign('email_charter_owner_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('lead_allocation_settings', function (Blueprint $table) {
            $table->dropForeign([
                'email_charter_owner_user_id'
            ]);

            $table->dropColumn(
                'email_charter_owner_user_id'
            );
        });
    }
}