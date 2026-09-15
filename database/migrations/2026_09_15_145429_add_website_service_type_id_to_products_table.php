<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWebsiteServiceTypeIdToProductsTable extends Migration
{
  public function up(): void
    {
        Schema::table(
            'products',
            function (Blueprint $table) {

                $table
                    ->unsignedBigInteger(
                        'website_service_type_id'
                    )
                    ->nullable()
                    ->unique();

            }
        );
    }


    public function down(): void
    {
        Schema::table(
            'products',
            function (Blueprint $table) {

                $table->dropUnique([
                    'website_service_type_id',
                ]);

                $table->dropColumn(
                    'website_service_type_id'
                );

            }
        );
    }
}
