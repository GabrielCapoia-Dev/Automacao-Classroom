<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Client as GoogleClient;
use Google\Service\Classroom;
use App\Models\GoogleAccount;
use Carbon\Carbon;
use RuntimeException;

class TestGoogleClassroom extends Command
{
    protected $signature = 'google:test-classroom';
    protected $description = 'Testa conexão com Google Classroom';

    public function handle()
    {
        /** @var GoogleAccount|null $account */
        $account = GoogleAccount::main();

        if (! $account) {
            $this->error('Conta Google principal não encontrada.');
            return;
        }

        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google_main.redirect'));
        $client->setAccessType('offline');
        $client->setScopes([Classroom::CLASSROOM_COURSES]);

        // Converte token_expires_at do banco em segundos restantes
        $expiresIn = $account->token_expires_at
            ? Carbon::parse($account->token_expires_at)->timestamp - time()
            : 3600;

        $client->setAccessToken([
            'access_token'  => $account->access_token,          // se criptografado, faça decrypt()
            'refresh_token' => $account->refresh_token ? $account->refresh_token : null,
            'expires_in'    => max(60, $expiresIn),            // garante mínimo de 60s
            'created'       => time(),
        ]);

        // Refresh automático se necessário
        if ($client->isAccessTokenExpired()) {
            $this->warn('Token expirado. Tentando refresh...');

            if (! $account->refresh_token) {
                $this->error('Refresh token não disponível.');
                return;
            }

            $newToken = $client->fetchAccessTokenWithRefreshToken($account->refresh_token);

            if (isset($newToken['error'])) {
                $this->error('Falha no refresh token');
                dump($newToken);
                return;
            }

            $this->info('Refresh OK');

            // Atualiza o DB com o novo access token
            $account->update([
                'access_token'     => $newToken['access_token'] ?? $account->access_token,
                'token_expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
            ]);

            // Atualiza o client com o novo token
            $client->setAccessToken([
                'access_token'  => $newToken['access_token'],
                'refresh_token' => $account->refresh_token,
                'expires_in'    => $newToken['expires_in'] ?? 3600,
                'created'       => time(),
            ]);
        }

        $service = new Classroom($client);

        try {
            $courses = $service->courses->listCourses();
            $this->info('API FUNCIONANDO');
            $this->line('Cursos encontrados: ' . count($courses->getCourses() ?? []));
        } catch (\Throwable $e) {
            $this->error('Erro ao acessar Classroom');
            $this->error($e->getMessage());
        }
    }
}
