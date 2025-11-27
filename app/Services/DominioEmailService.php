<?php

namespace App\Services;

use App\Models\DominioEmail;
use Illuminate\Support\Collection;

class DominioEmailService
{
    /**
     * Metodo publico que verifica se o email do usuário é um domínio permitido
     *
     * @param string $email
     * @return bool
     */
    public function isEmailAutorizado(string $email): bool
    {
        return $this->verificaEmail($email);
    }


    /**
     * Retorna uma lista de dominios de email ativos.
     *
     * @return Collection
     */
    private function getAllEmails(): Collection
    {
        return DominioEmail::query()
            ->where('status', true) // Só os ativos
            ->pluck('dominio_email');
    }

    /**
     * Utiliza a lista de emails permitidos para verificar se o email do usuário é permitido
     *
     * @param string $email
     * @return bool
     */
    private function verificaEmail(string $email): bool
    {
        try {
            $dominio = strtolower(explode('@', $email)[1] ?? "");

            if (empty($dominio)) {
                return false;
            }

            $dominiosPermitidos = $this->getAllEmails()->toArray();

            $validate = in_array($dominio, $dominiosPermitidos);

            return $validate;
        } catch (\Throwable $e) {
            return false;
        }
    }
}