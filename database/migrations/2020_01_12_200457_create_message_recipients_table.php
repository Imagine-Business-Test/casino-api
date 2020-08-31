<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMessageRecipientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('message_recipients', function (Blueprint $table) {
            $table->engine = 'innoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('msg_id');
            $table->unsignedBigInteger('recipient');
            $table->enum('seen',[0,1])->default(0);
            $table->timestamp('date_seen');
            $table->timestamps();

            // indexing
            $table->index(['msg_id', 'recipient']);

            // relations
            $table->foreign('msg_id')
                ->references('id')
                ->on('message')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('recipient')
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
        Schema::dropIfExists('message_recipients');
    }
}
