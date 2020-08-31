<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePitRulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pit_rules', function (Blueprint $table) {
            $table->engine = 'innoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pit_id');
            $table->integer('bet_min');
            $table->integer('bet_max');
            $table->timestamps();

            // indexing
            $table->index(['pit_id']);

            // relations
            $table->foreign('pit_id')
                ->references('id')
                ->on('pits')
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
        Schema::dropIfExists('pit_rules');
    }
}
