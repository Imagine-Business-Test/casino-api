<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlayerAuthTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('player_auth', function (Blueprint $table) {
            $table->engine = 'innoDB';
            $table->bigIncrements('id');
            $table->string('username', 100)->unique();
            $table->string('user_code', 30)->nullable();
            $table->string('password', 100);
            $table->string('email_veri_code', 100)->nullable();
            $table->enum('email_verified', [1, 0])->default(0);
            $table->string('phone_veri_code', 20)->nullable();
            $table->enum('phone_verified', [1, 0])->default(0);
            $table->timestamp('last_login')->comment('for new account this time is the CURRENT TIMESTAMP but for subsequent the time is UPDATED ');
            $table->timestamp('last_action')->comment('last action user performed in the system this is used to know when to timeout the session ');
            $table->enum('user_disabled', [1, 0])->default(0)->comment('This is the admins tool to activate or deactivate this user ');
            $table->timestamp('date_disabled');
            $table->enum('user_banned', [1, 0])->default(0)->comment('This user has commited an offence and now banned from the casino house. Likely to be blacklisted for the general public to see and be aware. ');
            $table->timestamp('date_banned');
            $table->string('activation_code', 200)->nullable();
            $table->enum('activation_code_activated', [1, 0])->default(0);
            $table->timestamp('activation_code_expire');
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
        Schema::dropIfExists('player_auth');
    }
}
