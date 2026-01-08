<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('turmas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('google_account_id')
                ->constrained('google_accounts')
                ->cascadeOnDelete();

            $table->foreignId('escola_id')
                ->constrained('escolas')
                ->cascadeOnDelete();

            $table->string('nome');
            $table->string('classroom_topic_id')->nullable();

            $table->timestamps();

            $table->index(['google_account_id', 'escola_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turmas');
    }
};
