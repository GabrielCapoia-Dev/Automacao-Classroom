<?php

namespace App\Models;

use App\Models\Traits\BelongsToGoogleAccount;
use Illuminate\Database\Eloquent\Model;

class Atividade extends Model
{
    use BelongsToGoogleAccount;

    protected $fillable = [
        'google_account_id',
        'turma_id',
        'serie_id',
        'titulo',
        'titulo_original',
        'numero_parte',
        'total_partes',
        'descricao',
        'classroom_coursework_id',
        'drive_folder_id',
        'drive_folder_url',
        'arquivos_parte',
    ];

    protected $casts = [
        'arquivos_parte' => 'array', // ✅ Auto-decode JSON
    ];

    public function turma()
    {
        return $this->belongsTo(Turma::class);
    }

    public function serie()
    {
        return $this->belongsTo(Serie::class);
    }

    public function professores()
    {
        return $this->belongsToMany(
            Professor::class,
            'atividade_professor',
            'atividade_id',
            'professor_id'
        )->withTimestamps();
    }

    public function escolas()
    {
        return $this->belongsToMany(
            Escola::class,
            'atividade_escola',
            'atividade_id',
            'escola_id'
        )->withPivot('classroom_coursework_id')->withTimestamps();
    }

    /**
     * Retorna todas as partes desta atividade
     */
    public function todasAsPartes()
    {
        if (!$this->titulo_original) {
            return collect([$this]);
        }

        return self::where('titulo_original', $this->titulo_original)
            ->where('serie_id', $this->serie_id)
            ->orderBy('numero_parte')
            ->get();
    }

    /**
     * Retorna turmas relacionadas
     */
    public function getTurmasRelacionadas()
    {
        return Turma::whereIn('escola_id', $this->escolas->pluck('id'))
            ->where('serie_id', $this->serie_id)
            ->get();
    }

    /**
     * Scope para pegar apenas a primeira parte de cada atividade (para listagem)
     */
    public function scopeApenasAtividadesPrincipais($query)
    {
        return $query->where(function ($q) {
            $q->where('numero_parte', 1)
                ->orWhereNull('titulo_original');
        });
    }
}
