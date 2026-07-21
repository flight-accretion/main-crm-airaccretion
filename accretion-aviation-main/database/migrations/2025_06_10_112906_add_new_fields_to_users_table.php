<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewFieldsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('address')->nullable();
            $table->string('contact_number')->nullable();
            $table->uuid('user_type_id')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->boolean('is_operation_user')->default(false);
            $table->timestamp('joining_date')->nullable();
            $table->timestamp('last_login')->nullable();
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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'contact_number',
                'user_type_id',
                'status',
                'is_operation_user',
                'joining_date',
                'last_login'
            ]);
            
        });
    }
}
