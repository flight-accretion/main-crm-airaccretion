<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_agent_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('ai_agent_id')->nullable()->index();
            $table->string('kind', 30);
            $table->string('outcome', 20);
            $table->unsignedInteger('duration_ms');
            $table->dateTime('occurred_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_runs');
    }
};
