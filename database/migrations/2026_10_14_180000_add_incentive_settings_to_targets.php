<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIncentiveSettingsToTargets extends Migration
{
    public function up(): void
    {
        Schema::table('targets', function (Blueprint $table) {
            $table->boolean('incentive_enabled')->default(false);
            $table->decimal('incentive_multiplier', 10, 8)->nullable();
            $table->decimal('incentive_team_multiplier', 10, 8)->nullable();
            $table->string('incentive_basis', 20)->nullable();
            $table->json('incentive_product_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('targets', function (Blueprint $table) {
            $table->dropColumn(['incentive_enabled', 'incentive_multiplier', 'incentive_team_multiplier', 'incentive_basis', 'incentive_product_ids']);
        });
    }
}
