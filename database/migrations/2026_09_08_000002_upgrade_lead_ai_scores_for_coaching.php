<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpgradeLeadAiScoresForCoaching extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lead_ai_scores')) {
            return;
        }

        $this->addText('score_reason');
        $this->addJson('actions_json');
        $this->addString('next_commitment', 120);
        $this->addString('provider', 30);
        $this->addString('prompt_version', 60);
        $this->addUnsignedInteger('input_tokens');
        $this->addUnsignedInteger('cached_input_tokens');
        $this->addUnsignedInteger('output_tokens');
        $this->addUnsignedInteger('thinking_tokens');
        $this->addUnsignedInteger('total_tokens');
        $this->addUnsignedInteger('processing_ms');

        if (Schema::hasColumn('lead_ai_scores', 'next_commitment')) {
            Schema::table('lead_ai_scores', function (Blueprint $table) {
                $table->string('next_commitment', 120)
                    ->nullable()
                    ->change();
            });
        }

        if (Schema::hasColumn('lead_ai_scores', 'prompt_version')) {
            Schema::table('lead_ai_scores', function (Blueprint $table) {
                $table->string('prompt_version', 60)
                    ->nullable()
                    ->change();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('lead_ai_scores')) {
            return;
        }

        foreach (
            [
                'score_reason',
                'actions_json',
                'next_commitment',
                'provider',
                'prompt_version',
                'input_tokens',
                'cached_input_tokens',
                'output_tokens',
                'thinking_tokens',
                'total_tokens',
                'processing_ms',
            ] as $column
        ) {
            if (Schema::hasColumn('lead_ai_scores', $column)) {
                Schema::table('lead_ai_scores', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function addText(string $column): void
    {
        if (Schema::hasColumn('lead_ai_scores', $column)) {
            return;
        }

        Schema::table('lead_ai_scores', function (Blueprint $table) use ($column) {
            $table->text($column)->nullable();
        });
    }

    private function addJson(string $column): void
    {
        if (Schema::hasColumn('lead_ai_scores', $column)) {
            return;
        }

        Schema::table('lead_ai_scores', function (Blueprint $table) use ($column) {
            $table->json($column)->nullable();
        });
    }

    private function addString(
        string $column,
        int $length
    ): void {
        if (Schema::hasColumn('lead_ai_scores', $column)) {
            return;
        }

        Schema::table('lead_ai_scores', function (Blueprint $table) use ($column, $length) {
            $table->string($column, $length)->nullable();
        });
    }

    private function addUnsignedInteger(string $column): void
    {
        if (Schema::hasColumn('lead_ai_scores', $column)) {
            return;
        }

        Schema::table('lead_ai_scores', function (Blueprint $table) use ($column) {
            $table->unsignedInteger($column)->nullable();
        });
    }
}
