<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGamesUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('games_users', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user')->unsigned();
            $table->integer('game')->unsigned();
            $table->enum('ready_status', ['ready', 'not ready'])->default('not ready');
            $table->softDeletes();
            $table->timestamps();


            $table->foreign('game')->references('id')->on('games');
            $table->foreign('user')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('games_users');
    }
}
