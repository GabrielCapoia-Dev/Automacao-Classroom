<?php

namespace App\Services\Escola;

use App\Models\Escola;
use App\Models\GoogleAccount;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EscolaService
{
    public function __construct(
        protected EscolaClassService $classService
    ) {}

    /**
     * Criação de Escola
     * - Cria curso no Classroom
     * - Persiste no banco
     */
    public function create(array $data): Escola
    {
        $googleAccount = GoogleAccount::main();

        if (! $googleAccount) {
            throw new RuntimeException('Nenhuma conta Google MAIN conectada.');
        }

        return DB::transaction(function () use ($data, $googleAccount) {
            // 1️⃣ cria no Classroom
            $classroomCourseId = $this->classService->criar($data['nome'], $googleAccount);


            // 2️⃣ salva no banco
            return Escola::create([
                'google_account_id'   => $googleAccount->id,
                'nome'                => $data['nome'],
                'classroom_course_id' => $classroomCourseId,
            ]);
        });
    }

    /**
     * Atualização de Escola
     * - Atualiza curso no Classroom
     * - Atualiza no banco
     */
    public function update(Escola $escola, array $data): Escola
    {
        return DB::transaction(function () use ($escola, $data) {
            if (! $escola->classroom_course_id) {
                throw new RuntimeException('Escola sem vínculo com o Classroom.');
            }

            // 1️⃣ atualiza no Classroom
            $this->classService->atualizar(
                $escola->classroom_course_id,
                $data['nome']
            );

            // 2️⃣ atualiza no banco
            $escola->update([
                'nome' => $data['nome'],
            ]);

            return $escola;
        });
    }

    /**
     * Exclusão de Escola
     * - Arquiva curso no Classroom
     * - Remove do banco
     */
    public function delete(Escola $escola): void
    {
        DB::transaction(function () use ($escola) {
            if ($escola->classroom_course_id) {
                // 1️⃣ arquiva no Classroom (NUNCA delete)
                $this->classService->arquivar(
                    $escola->classroom_course_id
                );
            }

            // 2️⃣ remove do banco
            $escola->delete();
        });
    }
}
