<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHouseSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('house_settings', function (Blueprint $table) {
            $table->engine = "innoDB";
            $table->bigIncrements('id');
            $table->string('business_name', 30)->nullable();
            $table->string('business_title', 100)->nullable();
            $table->string('business_desc', 500)->nullable();
            $table->string('business_email', 100)->nullable();
            $table->integer('session_expires')->nullable()->comment('time to terminate sessions works with LAST_LOGIN in seconds ');
            $table->integer('bet_min')->default(1000);
            $table->integer('bet_max')->default(1000000);
            $table->string('slogan', 40)->nullable();
            $table->string('contact', 50)->nullable();
            $table->enum('can_login', [1, 0])->default(1);
            $table->string('site_other_name', 40)->nullable();
            $table->string('about', 1000)->nullable();
            $table->string('terms', 1000)->nullable();
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
        Schema::dropIfExists('house_settings');
    }
}
