<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_email_template_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('booking_email_template_id')->index();
            $table->unsignedInteger('version');
            $table->string('subject', 500);
            $table->text('body');
            $table->string('change_note', 500)->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['booking_email_template_id', 'version'], 'booking_email_revision_version_unique');
        });
        // Preserve the existing live content before the first versioned edit.
        if (Schema::hasTable('booking_email_templates')) {
            foreach (DB::table('booking_email_templates')->get() as $template) {
                DB::table('booking_email_template_revisions')->insert([
                    'id'=>(string) Str::uuid(), 'booking_email_template_id'=>$template->id,
                    'version'=>1, 'subject'=>$template->subject, 'body'=>$template->body,
                    'change_note'=>'Existing template snapshot', 'created_by'=>$template->updated_by,
                    'created_at'=>$template->updated_at ?: now(), 'updated_at'=>now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_email_template_revisions');
    }
};
