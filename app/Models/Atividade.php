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
        'titulo',
        'descricao',
        'classroom_coursework_id',
    ];

    public function turma()
    {
        return $this->belongsTo(Turma::class);
    }

    public function professores()
    {
        return $this->belongsToMany(Professor::class);
    }
}

