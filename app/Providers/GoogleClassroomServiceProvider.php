<?php

namespace App\Providers;

use App\Models\GoogleAccount;
use App\Services\GoogleMainService;
use Google\Client as GoogleClient;
use Google\Service\Classroom;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class GoogleClassroomServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Classroom::class, function () {

            /** @var GoogleAccount|null $mainAccount */
            $mainAccount = GoogleAccount::main();

            if (!$mainAccount) {
                throw new RuntimeException('Conta Google MAIN não configurada.');
            }

            $client = new GoogleClient();
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));

            $client->setAccessToken([
                'access_token'  => $mainAccount->access_token,
                'refresh_token' => $mainAccount->refresh_token,
                'expires_at'    => optional($mainAccount->token_expires_at)->timestamp,
            ]);

            // 🔥 REFRESH SE NECESSÁRIO
            if ($client->isAccessTokenExpired()) {

                if (!$mainAccount->refresh_token) {
                    throw new RuntimeException('Refresh token inexistente.');
                }

                $newToken = $client->fetchAccessTokenWithRefreshToken(
                    $mainAccount->refresh_token
                );

                // ✅ validação obrigatória
                if (isset($newToken['error'])) {
                    throw new RuntimeException(
                        'Erro ao renovar token Google: ' . $newToken['error_description']
                    );
                }

                $mainAccount->update([
                    'access_token'     => $newToken['access_token'],
                    'token_expires_at' => now()->addSeconds($newToken['expires_in']),
                ]);

                $client->setAccessToken($newToken);
            }

            return new Classroom($client);
        });
    }
}
