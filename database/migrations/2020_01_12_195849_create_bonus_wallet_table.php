<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBonusWalletTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bonus_wallet', function (Blueprint $table) {
            $table->engine = "innoDB";
            $table->bigIncrements('id');
            $table->string('wallet_id')->unique();
            $table->integer('bonus_amount');
            $table->enum('redeemed', [1, 0])->default(0)->comment("Tells if this user has cashed out this bonus with the cashier or not ");
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('granted_by')->comment('the super admin/manager that dispatched this bonus ');
            $table->enum('disabled', [1, 0])->default(0)->comment('This is the admins tool to activate or deactivate records ');
            $table->timestamp('date_disabled');
            $table->timestamps();

            // indexing
            $table->index(['player_id', 'granted_by']);

            // relations
            $table->foreign('player_id')
                ->references('id')
                ->on('player_auth')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('granted_by')
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
        Schema::dropIfExists('bonus_wallet');
    }
}
