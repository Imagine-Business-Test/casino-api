<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePitEventLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pit_event_log', function (Blueprint $table) {
            $table->engine = 'innoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('event_type');
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('player_id');
            $table->integer('amount');
            $table->timestamps();

            // indexing
            $table->index(['event_type', 'session_id','player_id']);

            // relations
            $table->foreign('event_type')
                ->references('id')
                ->on('pit_event_types')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('session_id')
                ->references('id')
                ->on('pit_session')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('player_id')
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
        Schema::dropIfExists('pit_event_log');
    }
}
