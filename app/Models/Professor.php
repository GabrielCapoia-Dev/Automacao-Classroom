<?php

namespace App\Models;

use App\Models\Traits\BelongsToGoogleAccount;
use Illuminate\Database\Eloquent\Model;

class Professor extends Model
{
    use BelongsToGoogleAccount;

    protected $table = 'professores';

    protected $fillable = [
        'google_account_id',
        'escola_id',
        'nome',
        'email',
        'classroom_user_id',
    ];

    public function escola()
    {
        return $this->belongsTo(Escola::class);
    }

    public function turmas()
    {
        return $this->belongsToMany(Turma::class);
    }

    public function atividades()
    {
        return $this->belongsToMany(Atividade::class);
    }
}
