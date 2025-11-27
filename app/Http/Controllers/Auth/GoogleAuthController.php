<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleService;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        $queryParams = [
            'prompt' => 'select_account',
        ];
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$user?->hasGoogleOauth()) {
            $queryParams['prompt'] = 'select_account';
            $queryParams['access_type'] = 'offline';
            $queryParams['include_granted_scopes'] = 'true';
        }

        return Socialite::driver('google')
            ->scopes([
                'openid',
                'email',
                'profile',
                'https://www.googleapis.com/auth/drive.metadata.readonly',
                'https://www.googleapis.com/auth/spreadsheets.readonly'

            ])
            ->with($queryParams)
            ->redirect();
    }


    public function callback(GoogleService $service)
    {
        try {
            $oauthUser = Socialite::driver('google')->user();

            $user = $service->registrarOuLogar($oauthUser);
            \Filament\Facades\Filament::auth()->login($user, true);

            return redirect()->intended(\Filament\Facades\Filament::getUrl());
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('filament.admin.auth.login')
                ->withErrors(['google' => 'Falha ao autenticar com Google: ' . $e->getMessage()]);
        }
    }
}