<?php

namespace App\Models;

use App\Models\Traits\BelongsToGoogleAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Serie extends Model
{
    use BelongsToGoogleAccount;

    protected $fillable = [
        'google_account_id',
        'nome',
        'cor' // ✅ Novo campo
    ];

    // Cores padrão caso não tenha sido definida
    public static function coresPadrao(): array
    {
        return [
            'blue', 'green', 'red', 'yellow', 'purple', 
            'pink', 'indigo', 'cyan', 'orange', 'lime'
        ];
    }

    public function googleAccount()
    {
        return $this->belongsTo(GoogleAccount::class);
    }

    public function turmas()
    {
        return $this->hasMany(Turma::class);
    }

    public function scopeFromMainAccount(Builder $query): Builder
    {
        $accountId = GoogleAccount::main()?->id;
        
        return $query->when($accountId, fn($q) => $q->where('google_account_id', $accountId));
    }
}