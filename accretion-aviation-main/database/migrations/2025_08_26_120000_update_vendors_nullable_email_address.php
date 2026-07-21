<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // For PostgreSQL use raw statements to avoid requiring doctrine/dbal for simple ALTERs
        if (DB::getDriverName() === 'pgsql') {
            // Drop unique constraint if it exists and allow NULLs on email and address
            DB::statement('ALTER TABLE vendors DROP CONSTRAINT IF EXISTS vendors_email_unique');
            DB::statement('ALTER TABLE vendors ALTER COLUMN email DROP NOT NULL');
            DB::statement('ALTER TABLE vendors ALTER COLUMN address DROP NOT NULL');
            // make city_id nullable
            DB::statement('ALTER TABLE vendors ALTER COLUMN city_id DROP NOT NULL');
        } else {
            // For MySQL/SQLite and other drivers, we rely on Schema change().
            // Note: change() requires doctrine/dbal package (composer require doctrine/dbal) when altering columns.

            // Drop unique index if present
            Schema::table('vendors', function (Blueprint $table) {
                // Index name convention: vendors_email_unique
                if (Schema::hasColumn('vendors', 'email')) {
                    try {
                        $table->dropUnique('vendors_email_unique');
                    } catch (\Exception $e) {
                        // ignore if index doesn't exist
                    }
                }
            });

            // Make columns nullable using change()
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('email', 100)->nullable()->change();
                $table->text('address')->nullable()->change();
                $table->string('city_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (DB::getDriverName() === 'pgsql') {
            // Make columns NOT NULL again and recreate unique constraint
            DB::statement('ALTER TABLE vendors ALTER COLUMN email SET NOT NULL');
            DB::statement('ALTER TABLE vendors ADD CONSTRAINT IF NOT EXISTS vendors_email_unique UNIQUE (email)');
            DB::statement('ALTER TABLE vendors ALTER COLUMN address SET NOT NULL');
            DB::statement('ALTER TABLE vendors ALTER COLUMN city_id SET NOT NULL');
        } else {
            // For MySQL/SQLite and other drivers
            Schema::table('vendors', function (Blueprint $table) {
                // Revert nullability (requires doctrine/dbal)
                $table->string('email', 100)->nullable(false)->change();
                $table->text('address')->nullable(false)->change();
                $table->string('city_id')->nullable(false)->change();
                try {
                    $table->unique('email');
                } catch (\Exception $e) {
                    // ignore if cannot create index
                }
            });
        }
    }
};
