<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmailRoutingToLeadAllocationSettings extends Migration
{
    public function up()
    {
        /*
        |--------------------------------------------------------------------------
        | Add email_charter_owner_user_id only if missing
        |--------------------------------------------------------------------------
        */
        if (
            !Schema::hasColumn(
                'lead_allocation_settings',
                'email_charter_owner_user_id'
            )
        ) {
            Schema::table(
                'lead_allocation_settings',
                function (Blueprint $table) {
                    $table->uuid(
                        'email_charter_owner_user_id'
                    )->nullable();
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Add email_charter_product_ids only if missing
        |--------------------------------------------------------------------------
        */
        if (
            !Schema::hasColumn(
                'lead_allocation_settings',
                'email_charter_product_ids'
            )
        ) {
            Schema::table(
                'lead_allocation_settings',
                function (Blueprint $table) {
                    $table->json(
                        'email_charter_product_ids'
                    )->nullable();
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Add FK only if owner column exists
        |--------------------------------------------------------------------------
        |
        | We catch duplicate-constraint errors because an earlier migration
        | may already have created this foreign key.
        |
        */
        try {
            Schema::table(
                'lead_allocation_settings',
                function (Blueprint $table) {
                    $table->foreign(
                        'email_charter_owner_user_id',
                        'lead_allocation_settings_email_charter_owner_fk'
                    )
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
            );
        } catch (\Throwable $e) {
            /*
             * Existing FK is acceptable.
             * Do not fail migration just because it was already created.
             */
        }
    }


    public function down()
    {
        /*
        |--------------------------------------------------------------------------
        | Remove FK safely
        |--------------------------------------------------------------------------
        */
        try {
            Schema::table(
                'lead_allocation_settings',
                function (Blueprint $table) {
                    $table->dropForeign(
                        'lead_allocation_settings_email_charter_owner_fk'
                    );
                }
            );
        } catch (\Throwable $e) {
            // FK may not exist.
        }


        /*
        |--------------------------------------------------------------------------
        | Remove columns only if present
        |--------------------------------------------------------------------------
        */
        if (
            Schema::hasColumn(
                'lead_allocation_settings',
                'email_charter_product_ids'
            )
        ) {
            Schema::table(
                'lead_allocation_settings',
                function (Blueprint $table) {
                    $table->dropColumn(
                        'email_charter_product_ids'
                    );
                }
            );
        }


        if (
            Schema::hasColumn(
                'lead_allocation_settings',
                'email_charter_owner_user_id'
            )
        ) {
            Schema::table(
                'lead_allocation_settings',
                function (Blueprint $table) {
                    $table->dropColumn(
                        'email_charter_owner_user_id'
                    );
                }
            );
        }
    }
}