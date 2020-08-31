<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pits', function (Blueprint $table) {
            $table->engine = 'innoDB';
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('label', 50);
            $table->unsignedBigInteger('dealer');
            $table->unsignedBigInteger('pit_boss');
            $table->unsignedBigInteger('operator');
            $table->unsignedBigInteger('pit_game_type');
            $table->enum('in_service', [1, 0])->default(1);
            $table->timestamps();

            // indexing
            $table->index(['dealer', 'pit_boss', 'operator', 'pit_game_type']);

            // relations
            $table->foreign('dealer')
                ->references('id')
                ->on('admin_auth')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('pit_boss')
                ->references('id')
                ->on('admin_auth')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('operator')
                ->references('id')
                ->on('admin_auth')
                ->onDelete('cascade')
                ->onUpdate('cascade');


            $table->foreign('pit_game_type')
                ->references('id')
                ->on('pit_types')
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
        Schema::dropIfExists('pits');
    }
}
