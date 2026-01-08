<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleAccount extends Model
{
    protected $table = 'google_accounts';

    protected $fillable = [
        'email',
        'google_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
    ];

    protected $hidden = [
        'google_id',
        'access_token',
        'refresh_token',
    ];

    /**
     * Retorna a conta mais recentemente atualizada
     */
    public static function main(): ?self
    {
        return self::orderByDesc('updated_at')->first();
    }

    /**
     * Verifica se existe alguma conta registrada
     */
    public static function hasMain(): bool
    {
        return self::exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::hasMain();
    }

    /**
     * Indica se o token está expirado
     */
    public function tokenExpired(): bool
    {
        return !$this->token_expires_at || $this->token_expires_at->isPast();
    }
}
