<?php

namespace App\Http\Controllers;

use App\Models\GoogleAccount;
use Laravel\Socialite\Facades\Socialite;

class GoogleMainAuthController
{
    public function redirect()
    {
        return Socialite::driver('google')
            ->redirectUrl(config('services.google_main.redirect'))
            ->scopes([
                'https://www.googleapis.com/auth/classroom.courses',
                'https://www.googleapis.com/auth/classroom.coursework.students',
                'https://www.googleapis.com/auth/classroom.rosters',
                'https://www.googleapis.com/auth/drive.readonly',
            ])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
            ])
            ->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')
            ->redirectUrl(config('services.google_main.redirect'))
            ->stateless()
            ->user();


        GoogleAccount::query()->update(['is_main' => false]);

        GoogleAccount::updateOrCreate(
            ['email' => $googleUser->email],
            [
                'google_id' => encrypt($googleUser->id),
                'access_token' => encrypt($googleUser->token),
                'refresh_token' => encrypt($googleUser->refreshToken),
                'token_expires_at' => now()->addSeconds($googleUser->expiresIn),
                'is_main' => true,
            ]
        );

        return redirect()->route('filament.admin.pages.configuracoes');
    }
}
