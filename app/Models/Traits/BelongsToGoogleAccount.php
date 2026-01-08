<?php

namespace App\Models\Traits;

use App\Models\GoogleAccount;

trait BelongsToGoogleAccount
{
    protected static function booted()
    {
        static::creating(function ($model) {
            $model->google_account_id = GoogleAccount::main()->id;
        });

        static::addGlobalScope('googleAccount', function ($query) {
            if ($main = GoogleAccount::main()) {
                $query->where('google_account_id', $main->id);
            }
        });
    }
}
