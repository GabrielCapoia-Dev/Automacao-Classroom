<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('professores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('google_account_id')
                ->constrained('google_accounts')
                ->cascadeOnDelete();

            $table->foreignId('escola_id')
                ->constrained('escolas')
                ->cascadeOnDelete();

            $table->string('nome');
            $table->string('email');
            $table->string('classroom_user_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professores');
    }
};
