<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomerSentTrackingToVouchers extends Migration
{
     public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->timestamp('customer_sent_at')
                ->nullable()
                ->after('operation_team_user_id')
                ->index();

            $table->uuid('customer_sent_by')
                ->nullable()
                ->after('customer_sent_at')
                ->index();

            $table->string('customer_sent_via', 30)
                ->nullable()
                ->after('customer_sent_by');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn([
                'customer_sent_at',
                'customer_sent_by',
                'customer_sent_via',
            ]);
        });
    }

}
