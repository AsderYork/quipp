<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuestions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('question', 1024);
            $table->timestamps();
        });

        DB::table('questions')->insert(
            [
                ['question' => 'Именно с этими словами лучше всего начинать игру'],
                ['question' => 'Тяжелее, чем сделать игру, может быть только _______'],
                ['question' => 'Вычитка из C&D: "Вы можете продолжать использовать эту идею если  _______"'],
                ['question' => 'Вопрос номер 1  _______"'],
                ['question' => 'Анектдоты несмешные  _______"'],
                ['question' => 'Картина маслом  _______"'],
                ['question' => 'Пирамида маслоу  _______"'],
                ['question' => 'Ардженекидзе _______"'],
                ['question' => 'Ди энтвуд  _______"'],
                ['question' => 'Морнинг вуд  _______"'],
                ['question' => 'Апофеоз зоефопА  _______"'],
            ]
        );

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('questions');
    }
}
