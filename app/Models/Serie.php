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
        'nome'
    ];

    public function googleAccount()
    {
        return $this->belongsTo(GoogleAccount::class);
    }

    public function turmas()
    {
        return $this->hasMany(Turma::class);
    }

    /**
     * Scope para filtrar séries da conta MAIN
     */
    public function scopeFromMainAccount(Builder $query): Builder
    {
        $accountId = GoogleAccount::main()?->id;
        
        return $query->when($accountId, fn($q) => $q->where('google_account_id', $accountId));
    }
}