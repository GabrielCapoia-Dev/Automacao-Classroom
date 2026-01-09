<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Support\Facades\Cache;
use App\Models\GoogleAccount;

class GoogleDriveService
{
    private $client;
    private $service;

    public function __construct()
    {
        // Busca a conta MAIN
        $account = GoogleAccount::main();
        
        if (!$account) {
            throw new \Exception('Nenhuma conta Google conectada. Por favor, conecte sua conta Google primeiro.');
        }

        // Configura o cliente Google
        $this->client = new Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google_main.redirect'));
        $this->client->setAccessType('offline');
        
        // Define os escopos necessários
        $this->client->setScopes([
            'https://www.googleapis.com/auth/drive.readonly',
        ]);

        // Descriptografa os tokens
        $accessToken = $account->access_token;
        $refreshToken = $account->refresh_token;

        // Configura o token de acesso
        $this->client->setAccessToken([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => $account->token_expires_at
                ? $account->token_expires_at->timestamp - time()
                : 3600,
            'created' => time(),
        ]);

        // Refresh automático se necessário
        if ($this->client->isAccessTokenExpired() && $refreshToken) {
            $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($newToken['error'])) {
                throw new \Exception('Falha ao atualizar token: ' . ($newToken['error_description'] ?? $newToken['error']));
            }

            // Atualiza o token no banco
            $account->update([
                'access_token'     => encrypt($newToken['access_token']),
                'token_expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
            ]);

            // Atualiza o cliente com o novo token
            $this->client->setAccessToken([
                'access_token'  => $newToken['access_token'],
                'refresh_token' => $refreshToken,
                'expires_in'    => $newToken['expires_in'] ?? 3600,
                'created'       => time(),
            ]);
        }

        $this->service = new Drive($this->client);
    }

    public function listarArquivos($folderId)
    {
        try {
            // Cache por 5 minutos
            return Cache::remember("drive_files_{$folderId}", 300, function () use ($folderId) {
                $arquivos = [];
                $pageToken = null;

                do {
                    $response = $this->service->files->listFiles([
                        'q' => "'{$folderId}' in parents and trashed=false",
                        'pageSize' => 100,
                        'pageToken' => $pageToken,
                        'fields' => 'nextPageToken, files(id, name, mimeType, size, thumbnailLink, iconLink, webViewLink)',
                        'orderBy' => 'name',
                        'supportsAllDrives' => true,
                        'includeItemsFromAllDrives' => true
                    ]);

                    foreach ($response->getFiles() as $file) {
                        $arquivos[] = [
                            'id' => $file->getId(),
                            'nome' => $file->getName(),
                            'tipo' => $this->obterTipoArquivo($file->getMimeType()),
                            'mimeType' => $file->getMimeType(),
                            'tamanho' => $this->formatarTamanho($file->getSize()),
                            'icone' => $this->obterIcone($file->getMimeType()),
                            'thumbnail' => $file->getThumbnailLink(),
                            'iconLink' => $file->getIconLink(),
                            'webViewLink' => $file->getWebViewLink()
                        ];
                    }

                    $pageToken = $response->getNextPageToken();
                } while ($pageToken);

                return $arquivos;
            });
        } catch (\Exception $e) {
            // Limpa o cache em caso de erro
            Cache::forget("drive_files_{$folderId}");
            
            throw new \Exception("Erro ao buscar arquivos: " . $e->getMessage());
        }
    }

    private function obterTipoArquivo($mimeType)
    {
        $tipos = [
            'application/pdf' => 'pdf',
            'application/vnd.google-apps.document' => 'doc',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.google-apps.spreadsheet' => 'xls',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.google-apps.presentation' => 'ppt',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/mpeg' => 'mpeg',
            'video/quicktime' => 'mov',
            'application/vnd.google-apps.folder' => 'folder',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
        ];

        foreach ($tipos as $mime => $ext) {
            if (str_contains($mimeType, $mime)) {
                return $ext;
            }
        }

        return 'file';
    }

    private function obterIcone($mimeType)
    {
        $icones = [
            'pdf' => 'heroicon-o-document-text',
            'doc' => 'heroicon-o-document-text',
            'docx' => 'heroicon-o-document-text',
            'xls' => 'heroicon-o-table-cells',
            'xlsx' => 'heroicon-o-table-cells',
            'ppt' => 'heroicon-o-presentation-chart-line',
            'pptx' => 'heroicon-o-presentation-chart-line',
            'jpg' => 'heroicon-o-photo',
            'jpeg' => 'heroicon-o-photo',
            'png' => 'heroicon-o-photo',
            'gif' => 'heroicon-o-photo',
            'mp4' => 'heroicon-o-film',
            'mpeg' => 'heroicon-o-film',
            'mov' => 'heroicon-o-film',
            'folder' => 'heroicon-o-folder',
            'zip' => 'heroicon-o-archive-box',
        ];

        $tipo = $this->obterTipoArquivo($mimeType);
        return $icones[$tipo] ?? 'heroicon-o-document';
    }

    private function formatarTamanho($bytes)
    {
        if (!$bytes || $bytes == 0) return '0 B';
        
        $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log(1024));
        
        return round($bytes / pow(1024, $i), 2) . ' ' . $unidades[$i];
    }

    /**
     * Valida se o folderId é válido
     */
    public function validarFolderId($folderId): bool
    {
        try {
            $this->service->files->get($folderId, [
                'fields' => 'id',
                'supportsAllDrives' => true
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtém informações da pasta
     */
    public function obterInfoPasta($folderId): ?array
    {
        try {
            $file = $this->service->files->get($folderId, [
                'fields' => 'id, name, mimeType',
                'supportsAllDrives' => true
            ]);

            return [
                'id' => $file->getId(),
                'nome' => $file->getName(),
                'tipo' => $file->getMimeType()
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verifica se há uma conta Google conectada
     */
    public static function temContaConectada(): bool
    {
        return GoogleAccount::hasMain();
    }
}