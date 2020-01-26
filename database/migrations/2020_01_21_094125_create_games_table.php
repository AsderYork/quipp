<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGamesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('games', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('creator')->unsigned();
            $table->dateTime('started')->nullable();
            $table->enum('status', ['started', 'answers', 'voting', 'showing_results', 'end'])->default('started');
            $table->integer('round')->default(0);
            $table->integer('subround')->default(0);
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('creator')->references('id')->on('users');


        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('games');
    }
}
