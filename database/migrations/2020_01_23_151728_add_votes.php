<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVotes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user')->unsigned();
            $table->integer('game')->unsigned();
            $table->integer('round');
            $table->integer('subround')->default(0);
            $table->integer('answer')->unsigned();
            $table->integer('type')->unsigned()->nullable();
            $table->integer('value')->unsigned()->nullable();
            $table->timestamps();

            $table->foreign('game')->references('id')->on('games');
            $table->foreign('user')->references('id')->on('users');
            $table->foreign('answer')->references('id')->on('answers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('votes', function (Blueprint $table) {
            //
        });
    }
}
