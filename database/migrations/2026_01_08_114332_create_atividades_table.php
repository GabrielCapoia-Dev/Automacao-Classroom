<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('atividades', function (Blueprint $table) {
            $table->id();

            $table->foreignId('google_account_id')
                ->constrained('google_accounts')
                ->cascadeOnDelete();

            $table->foreignId('turma_id')
                ->constrained('turmas')
                ->cascadeOnDelete();

            $table->foreignId('serie_id')
                ->constrained('series')
                ->cascadeOnDelete();

            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->string('classroom_coursework_id')->nullable();

            $table->timestamps();

            $table->index(['google_account_id', 'turma_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atividades');
    }
};
