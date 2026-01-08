<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('atividade_professor', function (Blueprint $table) {
            $table->id();

            $table->foreignId('atividade_id')
                ->constrained('atividades')
                ->cascadeOnDelete();

            $table->foreignId('professor_id')
                ->constrained('professores')
                ->cascadeOnDelete();

            $table->unique(['atividade_id', 'professor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atividade_professor');
    }
};

