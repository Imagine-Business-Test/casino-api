<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMessageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('message', function (Blueprint $table) {
            $table->engine = 'innoDB';
            $table->bigIncrements('id');
            $table->string('title', 200);
            $table->text('body');
            $table->enum('meant_for', [1, 2])->comment('1 is PERSONS and 2 is aLL ');
            $table->enum('display_type', [1, 0])->default(1)->comment('where 1 meait should popup on user loginns ');
            $table->enum('visibility', [1, 0])->default(1);
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
        Schema::dropIfExists('message');
    }
}
