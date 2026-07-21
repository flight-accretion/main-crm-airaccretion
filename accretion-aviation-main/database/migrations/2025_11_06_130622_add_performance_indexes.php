<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPerformanceIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add indexes to leads table for frequently queried columns
        Schema::table('leads', function (Blueprint $table) {
            // Index for representative filtering
            if (!Schema::hasColumn('leads', 'representative_user_id')) {
                $table->index('representative_user_id');
            } else {
                try {
                    $table->index('representative_user_id');
                } catch (\Exception $e) {
                    // Index might already exist
                }
            }
            
            // Index for created_at (sorting)
            try {
                $table->index('created_at');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // Index for client_id (foreign key)
            try {
                $table->index('client_id');
            } catch (\Exception $e) {
                // Index might already exist
            }
        });

        // Add indexes to lead_followups table
        Schema::table('lead_followups', function (Blueprint $table) {
            try {
                $table->index('lead_id');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            try {
                $table->index(['lead_id', 'created_at']);
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            try {
                $table->index('status');
            } catch (\Exception $e) {
                // Index might already exist
            }
        });

        // Add indexes to clients table
        Schema::table('clients', function (Blueprint $table) {
            try {
                $table->index('name');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            try {
                $table->index('email');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            try {
                $table->index('status');
            } catch (\Exception $e) {
                // Index might already exist
            }
        });

        // Add indexes to lead_rides table
        Schema::table('lead_rides', function (Blueprint $table) {
            try {
                $table->index('lead_id');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            try {
                $table->index(['from_date', 'to_date']);
            } catch (\Exception $e) {
                // Index might already exist
            }
        });

        // Add indexes to payment_audit_trails table
        Schema::table('payment_audit_trail', function (Blueprint $table) {
            try {
                $table->index('payment_status');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            try {
                $table->index('lead_followup_id');
            } catch (\Exception $e) {
                // Index might already exist
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['representative_user_id']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['client_id']);
        });

        Schema::table('lead_followups', function (Blueprint $table) {
            $table->dropIndex(['lead_id']);
            $table->dropIndex(['lead_id', 'created_at']);
            $table->dropIndex(['status']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['email']);
            $table->dropIndex(['status']);
        });

        Schema::table('lead_rides', function (Blueprint $table) {
            $table->dropIndex(['lead_id']);
            $table->dropIndex(['from_date', 'to_date']);
        });

        Schema::table('payment_audit_trails', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['lead_followup_id']);
        });
    }
}
