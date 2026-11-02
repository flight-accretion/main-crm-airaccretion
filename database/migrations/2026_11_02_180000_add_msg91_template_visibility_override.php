<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msg91_whatsapp_templates', function (Blueprint $table) {
            $table->boolean('crm_visibility_overridden')->default(false)->after('crm_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('msg91_whatsapp_templates', function (Blueprint $table) {
            $table->dropColumn('crm_visibility_overridden');
        });
    }
};
