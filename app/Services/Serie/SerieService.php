<?php

namespace App\Services\Serie;

use App\Models\Serie;

class SerieService
{
    public static function getCorPorNomeSerie(?string $nomeSerie): string
    {
        if (!$nomeSerie) return 'gray';

        $cores = ['primary', 'success', 'warning', 'danger', 'info', 'gray'];

        // Extrai números da série (1º ANO = 1, 2º ANO = 2, etc)
        preg_match('/(\d+)/', $nomeSerie, $matches);

        if (!empty($matches[1])) {
            // Usa o número extraído como índice
            $indice = ((int)$matches[1] - 1) % count($cores);
        } else {
            // Fallback: usa hash para séries sem número
            $indice = abs(crc32($nomeSerie)) % count($cores);
        }

        return $cores[$indice];
    }
}
