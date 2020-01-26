<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAnswers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user')->unsigned();
            $table->integer('game')->unsigned();
            $table->integer('round')->default(0);
            $table->integer('subround')->default(0);
            $table->integer('question')->unsigned();
            $table->string('answer', 1024)->nullable();
            $table->timestamps();

            $table->foreign('game')->references('id')->on('games');
            $table->foreign('user')->references('id')->on('users');
            $table->foreign('question')->references('id')->on('questions');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('answers');
    }
}
