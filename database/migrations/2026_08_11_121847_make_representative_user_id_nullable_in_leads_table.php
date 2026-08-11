<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeRepresentativeUserIdNullableInLeadsTable extends Migration
{
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->uuid('representative_user_id')
                ->nullable()
                ->change();
        });
    }

    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->uuid('representative_user_id')
                ->nullable(false)
                ->change();
        });
    }
}