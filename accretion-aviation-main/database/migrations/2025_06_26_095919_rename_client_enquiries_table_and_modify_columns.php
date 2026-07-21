<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameClientEnquiriesTableAndModifyColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        // Rename tables
        Schema::rename('client_enquiries', 'leads');
        Schema::rename('enquiry_trip_segments', 'lead_rides');
        Schema::rename('client_followups', 'lead_followups');

        // Modify 'leads' table
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('next_follow_up');
            $table->dropColumn('enquiry_status');
        });

        // Modify 'lead_rides' table
        Schema::table('lead_rides', function (Blueprint $table) {
            $table->renameColumn('client_enquiry_id', 'lead_id');
        });

        // Modify 'lead_followup' table
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->renameColumn('client_enquiry_id', 'lead_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse column changes
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->renameColumn('lead_id', 'client_enquiry_id');
        });

        Schema::table('lead_rides', function (Blueprint $table) {
            $table->renameColumn('lead_id', 'client_enquiry_id');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('status', 'enquiry_status');
            $table->timestamp('next_follow_up')->nullable();
        });

        // Rename tables back
        Schema::rename('lead_followups', 'client_followups');
        Schema::rename('lead_rides', 'enquiry_trip_segments');
        Schema::rename('leads', 'client_enquiries');
    }
}
