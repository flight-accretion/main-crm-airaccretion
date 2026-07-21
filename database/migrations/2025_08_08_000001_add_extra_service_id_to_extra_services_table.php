<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('extra_services', function (Blueprint $table) {
            $table->uuid('extra_service_id')->nullable()->after('service_id');
        });
    }

    public function down()
    {
        Schema::table('extra_services', function (Blueprint $table) {
            $table->dropColumn('extra_service_id');
        });
    }
};
