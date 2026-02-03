<?php

namespace App\Services\Atividade;

use App\Models\Atividade;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Turma;
use App\Services\GoogleMainService;
use Google\Service\Classroom;
use Google\Service\Classroom\CourseWork;

class AtividadeSyncService
{
    public function __construct(
        protected GoogleMainService $googleService
    ) {}

    public function syncAtividades(): array
    {
        $stats = ['criadas' => 0, 'vinculadas' => 0, 'ignoradas' => 0];
        
        $client = $this->googleService->getGoogleClient();
        $classroom = app(Classroom::class, ['client' => $client]);

        Escola::query()
            ->whereNotNull('classroom_course_id')
            ->each(function (Escola $escola) use ($classroom, &$stats) {
                $this->syncAtividadesDaEscola($classroom, $escola, $stats);
            });

        return $stats;
    }

    protected function syncAtividadesDaEscola(Classroom $classroom, Escola $escola, array &$stats): void
    {
        $pageToken = null;
        $mainAccountId = $this->googleService->getMainAccount()->id;

        do {
            $response = $this->retry(function () use ($classroom, $escola, $pageToken) {
                return $classroom->courses_courseWork->listCoursesCourseWork(
                    $escola->classroom_course_id,
                    [
                        'pageSize' => 100,
                        'pageToken' => $pageToken,
                    ]
                );
            });

            /** @var CourseWork[] $courseWorks */
            $courseWorks = $response->getCourseWork() ?? [];

            foreach ($courseWorks as $courseWork) {
                $resultado = $this->processarCourseWork($courseWork, $escola, $mainAccountId);
                $stats[$resultado]++;
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);
    }

    protected function processarCourseWork(CourseWork $courseWork, Escola $escola, int $mainAccountId): string
    {
        $courseworkId = $courseWork->getId();

        // Verifica se este coursework específico já foi importado
        $jaImportado = Atividade::whereHas('escolas', function ($q) use ($courseworkId) {
            $q->where('classroom_coursework_id', $courseworkId);
        })->exists();

        if ($jaImportado) {
            return 'ignoradas';
        }

        // Encontra a turma pelo topicId
        $turma = null;
        $serieId = null;
        
        if ($courseWork->getTopicId()) {
            $turma = Turma::where('classroom_topic_id', $courseWork->getTopicId())
                ->where('escola_id', $escola->id)
                ->first();
            
            $serieId = $turma?->serie_id;
        }

        // Se não tem série, ignora
        if (!$serieId) {
            return 'ignoradas';
        }

        // Extrai título base e número da parte
        $tituloOriginal = $courseWork->getTitle();
        $numeroParte = 1;
        
        // Detecta padrão "Parte X - Título"
        if (preg_match('/^Parte\s*(\d+)\s*[-–]\s*(.+)$/i', $tituloOriginal, $matches)) {
            $numeroParte = (int) $matches[1];
            $tituloOriginal = trim($matches[2]);
        }

        // Busca atividade existente com mesmo título e série
        $atividade = Atividade::where('titulo_original', $tituloOriginal)
            ->where('serie_id', $serieId)
            ->where('numero_parte', $numeroParte)
            ->first();

        $isNova = false;

        if (!$atividade) {
            // Cria nova atividade
            $atividade = Atividade::create([
                'google_account_id' => $mainAccountId,
                'turma_id' => $turma?->id,
                'serie_id' => $serieId,
                'titulo' => $courseWork->getTitle(),
                'titulo_original' => $tituloOriginal,
                'numero_parte' => $numeroParte,
                'total_partes' => 1, // Será atualizado depois se necessário
                'descricao' => $courseWork->getDescription(),
            ]);
            $isNova = true;
        }

        // Vincula escola (se ainda não vinculada)
        if (!$atividade->escolas()->where('escola_id', $escola->id)->exists()) {
            $atividade->escolas()->attach($escola->id, [
                'classroom_coursework_id' => $courseworkId,
            ]);
        }

        // Vincula professores
        $this->vincularProfessores($atividade, $courseWork, $escola);

        // Atualiza total_partes se necessário
        $this->atualizarTotalPartes($tituloOriginal, $serieId);

        return $isNova ? 'criadas' : 'vinculadas';
    }

    protected function vincularProfessores(Atividade $atividade, CourseWork $courseWork, Escola $escola): void
    {
        $assigneeMode = $courseWork->getAssigneeMode();

        if ($assigneeMode === 'INDIVIDUAL_STUDENTS') {
            $individualOptions = $courseWork->getIndividualStudentsOptions();
            $studentIds = $individualOptions ? $individualOptions->getStudentIds() : [];

            if (!empty($studentIds)) {
                $professores = Professor::where('escola_id', $escola->id)
                    ->whereIn('classroom_user_id', $studentIds)
                    ->pluck('id');

                $atividade->professores()->syncWithoutDetaching($professores);
            }
        } else {
            // ALL_STUDENTS
            $professores = Professor::where('escola_id', $escola->id)
                ->whereNotNull('classroom_user_id')
                ->pluck('id');

            $atividade->professores()->syncWithoutDetaching($professores);
        }
    }

    protected function atualizarTotalPartes(string $tituloOriginal, int $serieId): void
    {
        $maiorParte = Atividade::where('titulo_original', $tituloOriginal)
            ->where('serie_id', $serieId)
            ->max('numero_parte');

        if ($maiorParte > 1) {
            Atividade::where('titulo_original', $tituloOriginal)
                ->where('serie_id', $serieId)
                ->update(['total_partes' => $maiorParte]);
        }
    }

    protected function retry(callable $callback, int $maxAttempts = 5)
    {
        $attempt = 0;
        $delay = 1;

        beginning:
        try {
            return $callback();
        } catch (\Google\Service\Exception $e) {
            $attempt++;

            $errors = $e->getErrors();
            $reason = $errors[0]['reason'] ?? null;

            if (
                $attempt < $maxAttempts &&
                in_array($reason, ['backendError', 'internalError', 'rateLimitExceeded'])
            ) {
                sleep($delay);
                $delay *= 2;
                goto beginning;
            }

            throw $e;
        }
    }
}