<?php

namespace App\Services;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Illuminate\Support\Str;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class GoogleService
{
    public function registrarOuLogar(SocialiteUserContract $oauthUser): ?User
    {
        $user = User::where('email', $oauthUser->getEmail())
            ->orWhere('google_email', $oauthUser->getEmail())
            ->first();

        if ($user == null) {
            $user = $this->registroGoogle($oauthUser);
        }

        if ($user && $user->email_approved) {
            $this->salvarTokens($user, $oauthUser);

            \Filament\Notifications\Notification::make()
                ->title('Acesso Permitido')
                ->body('Bem-vindo de volta!')
                ->success()
                ->send();

            return $user;
        }

        return $user;
    }

    private function registroGoogle(SocialiteUserContract $oauthUser): User|Notification
    {

        /** @var \App\Models\User|null $currentUser */
        $currentUser = Auth::user();

        if ($currentUser && !$currentUser->hasGoogleOauth()) {
            $currentUser->google_id = $oauthUser->getId();
            $currentUser->google_token = $oauthUser->token;
            $currentUser->google_refresh_token = $oauthUser->refreshToken ?? null;
            $currentUser->google_email = $oauthUser->getEmail();
            $currentUser->google_token_expires_in = now()->addSeconds(max(60, (int) $oauthUser->expiresIn - 60)) ?? null;
            $currentUser->save();

            return $currentUser;
        }

        if ($currentUser && $currentUser->hasGoogleOauth()) {
            $this->salvarTokens($currentUser, $oauthUser);
        }

        $email = $oauthUser->getEmail();

        //Se não tiver o email autorizado dispara uma exceção de email nao autorizado
        if (!app('App\Services\DominioEmailService')->isEmailAutorizado($email)) {
            return  \Filament\Notifications\Notification::make()
                ->title('Acesso Negado')
                ->body('Entre em contato com o administrador e solicite a aprovação do seu e-mail!')
                ->danger()
                ->send();
        }

        $user = User::create([
            'name' => $oauthUser->getName() ?? 'Usuário Sem Nome',
            'email' => $oauthUser->getEmail(),
            'password' => bcrypt(Str::random(16)),
            'email_approved' => false,
            'email_verified_at' => null,
        ]);

        $this->salvarTokens($user, $oauthUser);
        DB::afterCommit(function () use ($user) {
            $user->canAccessPanel(Filament::getPanel(), true);
        });

        return $user;
    }

    /**
     * Salva ou atualiza tokens do Google para o usuário.
     */
    private function salvarTokens(User $user, SocialiteUserContract $oauthUser): void
    {
        $refresh = $oauthUser->refreshToken ?? $user->google_refresh_token;

        $expiresIn = $oauthUser->expiresIn ?? 3600;
        $expiresAt = now()->addSeconds(max(60, (int) $expiresIn - 60));

        $user->forceFill([
            'google_token' => $oauthUser->token,
            'google_refresh_token' => $refresh,
            'google_token_expires_in' => $expiresAt,
        ])->save();
    }

    /**
     * Retorna um Google Client autenticado para o usuário.
     */
    public function getGoogleClient(User $user): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));
        $client->setAccessToken([
            'access_token'  => $user->google_token,
            'refresh_token' => $user->google_refresh_token,
            'expires_in'    => $user->google_token_expires_in,
        ]);

        if ($client->isAccessTokenExpired() && $user->google_refresh_token) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
            $user->update([
                'google_token' => $newToken['access_token'] ?? null,
                'google_token_expires_in' => $newToken['expires_in'] ?? null,
            ]);
        }

        return $client;
    }
}