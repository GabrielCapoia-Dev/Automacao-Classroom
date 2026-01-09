<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAtividadeEscolaTable extends Migration
{
    public function up()
    {
        Schema::create('atividade_escola', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atividade_id')->constrained('atividades')->cascadeOnDelete();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->string('classroom_coursework_id')->nullable(); // id do coursework no Classroom
            $table->timestamps();
            $table->unique(['atividade_id', 'escola_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('atividade_escola');
    }
}
