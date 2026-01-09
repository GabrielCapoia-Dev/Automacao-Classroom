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
        'descricao',
        'classroom_coursework_id',
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
}