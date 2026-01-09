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
     * Retorna a conta mais recentemente atualizada e valida o token.
     * Se o token expirou, retorna null.
     */
    public static function main(): ?self
    {
        $account = self::orderByDesc('updated_at')->first();

        if (! $account) {
            return null;
        }

        // Verifica se o token ainda é válido
        if (! $account->token_expires_at || $account->token_expires_at->isPast()) {
            return null; // token expirado, considera não conectada
        }

        return $account;
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
