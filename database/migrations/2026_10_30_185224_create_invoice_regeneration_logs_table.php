<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceRegenerationLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
{
    Schema::create('invoice_regeneration_logs', function (Blueprint $table) {
        $table->uuid('id')->primary();

        $table->uuid('invoice_id');
        $table->uuid('regenerated_by')->nullable();

        $table->timestamp('regenerated_at');

        $table->text('note')->nullable();

        $table->timestamps();

        $table->index('invoice_id');
        $table->index('regenerated_by');
        $table->index('regenerated_at');

        $table->foreign('invoice_id')
            ->references('id')
            ->on('invoices')
            ->onDelete('cascade');

        $table->foreign('regenerated_by')
            ->references('id')
            ->on('users')
            ->onDelete('set null');
    });
}

    /**
     * Reverse the migrations.
     *
     * @return void
     */
 public function down()
{
    Schema::dropIfExists('invoice_regeneration_logs');
}
}
