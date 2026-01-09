<?php

namespace App\Services;

use App\Models\GoogleAccount;
use Google\Client as GoogleClient;
use RuntimeException;

class GoogleMainService
{
    /**
     * Retorna a conta Google MAIN ativa
     */
    public function getMainAccount(): GoogleAccount
    {
        $account = GoogleAccount::main();

        if (! $account) {
            throw new RuntimeException('Nenhuma conta Google MAIN conectada.');
        }

        return $account;
    }

    /**
     * Salva ou atualiza tokens da conta MAIN
     */
    public function salvarTokens(
        string $googleId,
        string $email,
        string $accessToken,
        ?string $refreshToken,
        int $expiresIn
    ): GoogleAccount {
        $expiresAt = now()->addSeconds(max(60, $expiresIn - 60));

        // Se já existe pelo ID ou email, apenas atualiza os tokens
        $account = GoogleAccount::where('google_id', encrypt($googleId))
            ->orWhere('email', $email)
            ->first();

        if ($account) {
            $account->update([
                'access_token'       => $accessToken,
                'refresh_token'      => $refreshToken,
                'token_expires_at'   => $expiresAt,
            ]);
        } else {

            $account = GoogleAccount::create([
                'google_id'          => $googleId,
                'email'              => $email,
                'access_token'       => $accessToken,
                'refresh_token'      => $refreshToken,
                'token_expires_at'   => $expiresAt,
            ]);
        }

        return $account;
    }

    /**
     * Retorna um Google Client autenticado com a conta MAIN
     */
    public function getGoogleClient(): GoogleClient
    {
        $account = $this->getMainAccount();

        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google_main.redirect'));
        $client->setAccessType('offline');
        $client->setScopes([
            'openid',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile',

            'https://www.googleapis.com/auth/classroom.courses',
            'https://www.googleapis.com/auth/classroom.courses.readonly',

            'https://www.googleapis.com/auth/classroom.coursework.students',
            'https://www.googleapis.com/auth/classroom.coursework.students.readonly',
            'https://www.googleapis.com/auth/classroom.coursework.me',
            'https://www.googleapis.com/auth/classroom.coursework.me.readonly',

            'https://www.googleapis.com/auth/classroom.student-submissions.students.readonly',
            'https://www.googleapis.com/auth/classroom.student-submissions.me.readonly',

            'https://www.googleapis.com/auth/classroom.rosters.readonly',

            'https://www.googleapis.com/auth/classroom.profile.emails',
            'https://www.googleapis.com/auth/classroom.profile.photos',

            'https://www.googleapis.com/auth/classroom.guardianlinks.students',
            'https://www.googleapis.com/auth/classroom.guardianlinks.students.readonly',

            'https://www.googleapis.com/auth/classroom.push-notifications',

            'https://www.googleapis.com/auth/drive',
            'https://www.googleapis.com/auth/drive.readonly',
        ]);


        $client->setAccessToken([
            'access_token'  => $account->access_token,
            'refresh_token' => $account->refresh_token,
            'expires_in'    => $account->token_expires_at
                ? $account->token_expires_at->timestamp - time()
                : 3600,
            'created' => time(),
        ]);

        // Refresh automático se necessário
        if ($client->isAccessTokenExpired() && $account->refresh_token) {
            $newToken = $client->fetchAccessTokenWithRefreshToken(decrypt($account->refresh_token));

            if (isset($newToken['error'])) {
                throw new RuntimeException('Falha ao atualizar token MAIN: ' . $newToken['error_description'] ?? $newToken['error']);
            }

            $account->update([
                'access_token'     => encrypt($newToken['access_token']),
                'token_expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
            ]);

            $client->setAccessToken([
                'access_token'  => $account->access_token,
                'refresh_token' => $account->refresh_token,
                'expires_in'    => $account->token_expires_at->timestamp - time(),
                'created'       => time(),
            ]);
        }

        return $client;
    }
}
