<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAtividadeProfessorTable extends Migration
{
    public function up()
    {
        Schema::create('atividade_professor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atividade_id')->constrained('atividades')->cascadeOnDelete();
            $table->foreignId('professor_id')->constrained('professores')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['atividade_id','professor_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('atividade_professor');
    }
}
