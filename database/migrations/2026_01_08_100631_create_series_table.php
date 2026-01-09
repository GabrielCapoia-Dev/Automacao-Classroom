<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('google_account_id')->constrained('google_accounts')->onDelete('cascade');
            $table->string('nome');
            $table->timestamps();

            // Unique composto: mesmo nome pode existir em contas diferentes
            $table->unique(['google_account_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};