<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminAuthTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admin_auth', function (Blueprint $table) {
            $table->engine = 'innoDB';
            $table->bigIncrements('id');
            $table->string('username', 100)->unique();
            $table->unsignedBigInteger('role')->comment('The role of this admin user. Referencing the role table.');
            $table->string('user_code', 30)->nullable();
            $table->string('password', 100);
            $table->string('email_veri_code', 100)->nullable();
            $table->enum('email_verified', [1, 0])->default(0);
            $table->string('phone_veri_code', 20)->nullable();
            $table->enum('phone_verified', [1, 0])->default(0);
            $table->timestamp('last_login');
            $table->timestamp('last_action');
            $table->enum('user_disabled', [1, 0])->default(0);
            $table->timestamp('date_disabled');
            $table->string('activation_code', 200)->nullable();
            $table->enum('activation_code_activated', [1, 0])->default(0);
            $table->timestamp('activation_code_expire');
            $table->timestamps();
            $table->softDeletes();

            // indexing
            $table->index(['role']);

            // relations
            $table->foreign('role')
                ->references('id')
                ->on('admin_role')
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
        Schema::dropIfExists('admin_auth');
    }
}
