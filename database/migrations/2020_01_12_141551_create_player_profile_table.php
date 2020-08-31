<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlayerProfileTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('player_profile', function (Blueprint $table) {
            $table->engine = 'innoDB';
            $table->bigIncrements('user_id');
            $table->string('surname',20);
            $table->string('firstname',20);
            $table->string('phone',20);
            $table->string('email',50)->unique();
            $table->string('avatar',50)->nullable();
            $table->timestamps();

             // relations
             $table->foreign('user_id')
             ->references('id')
             ->on('player_auth')
             ->onDelete('cascade')
             ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('player_profile');
    }
}
