<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->string('cor', 20)->nullable()->after('nome');
        });

        // Atribui cores automaticamente para séries existentes
        $series = \App\Models\Serie::all();
        $cores = \App\Models\Serie::coresPadrao();
        
        foreach ($series as $index => $serie) {
            $serie->update(['cor' => $cores[$index % count($cores)]]);
        }
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn('cor');
        });
    }
};