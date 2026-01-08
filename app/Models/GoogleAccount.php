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
        'is_main',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'is_main' => 'boolean',
    ];

    protected $hidden = [
        'google_id',
        'access_token',
        'refresh_token',
    ];

    /**
     * Retorna a conta MAIN do sistema
     */
    public static function main(): ?self
    {
        return self::where('is_main', true)->first();
    }

    public static function hasMain(): bool
    {
        return self::where('is_main', true)->exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return GoogleAccount::hasMain();
    }

    /**
     * Indica se o token está expirado
     */
    public function tokenExpired(): bool
    {
        return !$this->token_expires_at || $this->token_expires_at->isPast();
    }
}
