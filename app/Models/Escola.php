<?php

namespace App\Models;

use App\Models\Traits\BelongsToGoogleAccount;
use Illuminate\Database\Eloquent\Model;

class Escola extends Model
{
    use BelongsToGoogleAccount;

    protected $fillable = [
        'google_account_id',
        'nome',
        'classroom_course_id',
    ];

    public function professores()
    {
        return $this->hasMany(Professor::class);
    }

    public function turmas()
    {
        return $this->hasMany(Turma::class);
    }

    public function atividades()
    {
        return $this->belongsToMany(Atividade::class, 'atividade_escola')->withPivot('classroom_coursework_id')->withTimestamps();
    }
}
