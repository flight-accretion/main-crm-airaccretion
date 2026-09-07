<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBookingEmailTemplatesTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('booking_email_templates')) {
            return;
        }

        Schema::create('booking_email_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject', 500);
            $table->text('body');
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_email_templates');
    }
}
