<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationMastersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
       Schema::create('notification_masters', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_number'); // required
            $table->string('email_id')->nullable();
            $table->boolean('status')->default(1); // 1 = active
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notification_masters');
    }
}
