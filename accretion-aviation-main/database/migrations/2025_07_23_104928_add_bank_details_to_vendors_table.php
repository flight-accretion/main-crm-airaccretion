<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBankDetailsToVendorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->text('bank_details')->nullable()->comment('Bank details of the vendor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('bank_details');
        });
    }
}
