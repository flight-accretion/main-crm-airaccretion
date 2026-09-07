<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBookingEmailNoteToProductsAndServices extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('products')
            && !Schema::hasColumn('products', 'booking_email_note')
        ) {
            Schema::table('products', function (Blueprint $table) {
                $column = $table->text('booking_email_note')->nullable();

                if (Schema::hasColumn('products', 'user_ids')) {
                    $column->after('user_ids');
                }
            });
        }

        if (
            Schema::hasTable('services')
            && !Schema::hasColumn('services', 'booking_email_note')
        ) {
            Schema::table('services', function (Blueprint $table) {
                $column = $table->text('booking_email_note')->nullable();

                if (Schema::hasColumn('services', 'terms_and_conditions')) {
                    $column->after('terms_and_conditions');
                }
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('products')
            && Schema::hasColumn('products', 'booking_email_note')
        ) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('booking_email_note');
            });
        }

        if (
            Schema::hasTable('services')
            && Schema::hasColumn('services', 'booking_email_note')
        ) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('booking_email_note');
            });
        }
    }
}
