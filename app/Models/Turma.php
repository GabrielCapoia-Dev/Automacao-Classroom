<?php

namespace App\Models;

use App\Models\Traits\BelongsToGoogleAccount;
use Illuminate\Database\Eloquent\Model;

class Turma extends Model
{
    use BelongsToGoogleAccount;

    protected $fillable = [
        'google_account_id',
        'escola_id',
        'nome',
        'classroom_topic_id',
    ];

    public function escola()
    {
        return $this->belongsTo(Escola::class);
    }

    public function professores()
    {
        return $this->belongsToMany(Professor::class);
    }

    public function atividades()
    {
        return $this->hasMany(Atividade::class);
    }
}
