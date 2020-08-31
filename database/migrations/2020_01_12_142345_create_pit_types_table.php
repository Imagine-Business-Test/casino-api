<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePitTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pit_types', function (Blueprint $table) {
            $table->engine = 'innoDB';
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('type_desc', 500)->nullable();
            $table->string('house_edge', 5)->nullable();
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
        Schema::dropIfExists('pit_types');
    }
}
