<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsDnpToCallSummaryIntegrationsTable extends Migration
{
    public function up()
    {
        Schema::table('call_summary_integrations', function (Blueprint $table) {
            $table->boolean('is_dnp')
                ->default(false)
                ->after('sentiment_score');
        });
    }

    public function down()
    {
        Schema::table('call_summary_integrations', function (Blueprint $table) {
            $table->dropColumn('is_dnp');
        });
    }
}