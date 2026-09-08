<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExtendLeadAiScoresForCoaching extends Migration
{
    public function up(): void
    {
        Schema::table('lead_ai_scores', function (Blueprint $table) {
            $table->text('score_reason')->nullable();
            $table->json('actions_json')->nullable();
            $table->string('next_commitment', 80)->nullable();

            $table->string('provider', 30)->nullable();
            $table->string('prompt_version', 50)->nullable();

            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('cached_input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->unsignedInteger('processing_ms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lead_ai_scores', function (Blueprint $table) {
            $table->dropColumn([
                'score_reason',
                'actions_json',
                'next_commitment',
                'provider',
                'prompt_version',
                'input_tokens',
                'cached_input_tokens',
                'output_tokens',
                'total_tokens',
                'processing_ms',
            ]);
        });
    }
}
