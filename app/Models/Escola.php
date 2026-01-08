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

    public function googleAccount()
    {
        return $this->belongsTo(GoogleAccount::class);
    }

    public function turmas()
    {
        return $this->hasMany(Turma::class);
    }
}

