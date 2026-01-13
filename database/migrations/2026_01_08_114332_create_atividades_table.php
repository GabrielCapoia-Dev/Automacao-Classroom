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
                ->nullable() // ✅ NULLABLE
                ->constrained('turmas')
                ->cascadeOnDelete();

            $table->foreignId('serie_id')
                ->constrained('series')
                ->cascadeOnDelete();

            $table->string('titulo');
            $table->string('titulo_original')->nullable();
            $table->integer('numero_parte')->default(1);
            $table->integer('total_partes')->default(1);
            $table->text('descricao')->nullable();
            $table->string('classroom_coursework_id')->nullable();
            
            // ✅ Campos do Drive
            $table->string('drive_folder_id')->nullable();
            $table->string('drive_folder_url')->nullable();

            $table->timestamps();

            $table->index(['google_account_id', 'turma_id']);
            $table->index(['titulo_original', 'serie_id', 'numero_parte']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atividades');
    }
};