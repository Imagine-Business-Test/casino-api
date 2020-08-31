<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExchangeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('exchange', function (Blueprint $table) {
            $table->engine = "innoDB";
            $table->bigIncrements('id');
            $table->unsignedBigInteger('exchange_type');
            $table->integer('amount');
            $table->unsignedBigInteger('player');
            $table->unsignedBigInteger('cashier');
            $table->unsignedBigInteger('supervisor');
            $table->timestamps();

            // indexing
            $table->index(['exchange_type', 'player', 'cashier', 'supervisor']);

            // relations
            $table->foreign('exchange_type')
                ->references('id')
                ->on('exchange_type')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('player')
                ->references('id')
                ->on('player_auth')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('cashier')
                ->references('id')
                ->on('admin_auth')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('supervisor')
                ->references('id')
                ->on('admin_auth')
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
        Schema::dropIfExists('exchange');
    }
}
