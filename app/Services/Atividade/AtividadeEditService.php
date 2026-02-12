<?php

namespace App\Services\Atividade;

use App\Models\Atividade;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Turma;
use App\Services\GoogleDriveService;
use App\Services\GoogleMainService;
use Google\Service\Classroom;
use Google\Service\Classroom\CourseWork;
use Google\Service\Classroom\ModifyCourseWorkAssigneesRequest;
use Google\Service\Classroom\ModifyIndividualStudentsOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AtividadeEditService
{
    public function __construct(
        protected GoogleMainService $googleService
    ) {}

    /**
     * Atualiza conteúdo e professores das escolas existentes
     */
    public function atualizarAtividade(Atividade $atividade, array $data): array
    {
        $client = $this->googleService->getGoogleClient();
        $classroom = app(Classroom::class, ['client' => $client]);

        $escolasSelecionadas = $data['editar_todas_escolas_existentes']
            ? $atividade->escolas
            : $atividade->escolas()->whereIn(
                'escola_id',
                $data['escolas_existentes_selecionadas'] ?? []
            )->get();

        $resultados = ['sucessos' => 0, 'falhas' => 0, 'detalhes' => []];

        // ✅ Verifica se realmente há algo para atualizar
        $precisaAtualizar = isset($data['titulo']) || isset($data['descricao']) || isset($data['professores_ids']);

        if (!$precisaAtualizar) {
            Log::info("Nada para atualizar - pulando", ['atividade_id' => $atividade->id]);
            return $resultados;
        }

        try {
            foreach ($escolasSelecionadas as $escola) {
                $pivot = $atividade->escolas()
                    ->where('escola_id', $escola->id)
                    ->first()
                    ?->pivot;

                if (!$pivot || !$pivot->classroom_coursework_id) {
                    Log::warning("Escola sem coursework_id - pulando", [
                        'atividade_id' => $atividade->id,
                        'escola_id' => $escola->id
                    ]);
                    continue;
                }

                // Edita atividade existente
                $this->editarAtividadeExistente(
                    classroom: $classroom,
                    atividade: $atividade,
                    escola: $escola,
                    courseworkId: $pivot->classroom_coursework_id,
                    data: $data
                );

                $resultados['sucessos']++;
            }

            // Atualiza dados da atividade no banco
            if (isset($data['titulo']) || isset($data['descricao'])) {
                $novoTitulo = $atividade->total_partes > 1
                    ? numeroParaRomano($atividade->numero_parte) . " - {$data['titulo']}"
                    : $data['titulo'];

                $atividade->update([
                    'titulo' => $novoTitulo,
                    'titulo_original' => $data['titulo'] ?? $atividade->titulo_original,
                    'descricao' => $data['descricao'] ?? $atividade->descricao,
                ]);
            }

            // Atualiza professores no banco
            if (isset($data['professores_ids'])) {
                $atividade->professores()->sync($data['professores_ids']);
            }
        } catch (\Throwable $e) {
            Log::error('Erro ao editar atividade', [
                'atividade_id' => $atividade->id,
                'erro' => $e->getMessage()
            ]);
            throw $e;
        }

        return $resultados;
    }

    protected function editarAtividadeExistente(
        Classroom $classroom,
        Atividade $atividade,
        Escola $escola,
        string $courseworkId,
        array $data
    ): void {
        $updateMask = [];
        $courseWork = new CourseWork();

        // ✅ Verifica se título realmente mudou
        if (isset($data['titulo'])) {
            $novoTitulo = $atividade->total_partes > 1
                ? numeroParaRomano($atividade->numero_parte) . " - {$data['titulo']}"
                : $data['titulo'];

            if ($novoTitulo !== $atividade->titulo) {
                $courseWork->setTitle($novoTitulo);
                $updateMask[] = 'title';
            }
        }

        // ✅ Verifica se descrição realmente mudou
        if (isset($data['descricao']) && $data['descricao'] !== $atividade->descricao) {
            $courseWork->setDescription($data['descricao']);
            $updateMask[] = 'description';
        }

        // Atualiza apenas se houver mudanças
        if ($updateMask) {
            $classroom->courses_courseWork->patch(
                $escola->classroom_course_id,
                $courseworkId,
                $courseWork,
                ['updateMask' => implode(',', $updateMask)]
            );

            Log::info("✓ Classroom atualizado", [
                'atividade_id' => $atividade->id,
                'escola' => $escola->nome,
                'campos' => $updateMask
            ]);
        }

        // Atualiza professores
        if (isset($data['professores_ids'])) {
            $this->editarAssignees($classroom, $atividade, $escola, $courseworkId, $data['professores_ids']);
        }
    }

    protected function editarAssignees(
        Classroom $classroom,
        Atividade $atividade,
        Escola $escola,
        string $courseworkId,
        array $professoresIds
    ): void {
        $atuais = $atividade->professores()
            ->where('escola_id', $escola->id)
            ->whereNotNull('classroom_user_id')
            ->pluck('classroom_user_id')
            ->toArray();

        $novos = Professor::whereIn('id', $professoresIds)
            ->where('escola_id', $escola->id)
            ->whereNotNull('classroom_user_id')
            ->pluck('classroom_user_id')
            ->toArray();

        $add = array_values(array_diff($novos, $atuais));
        $remove = array_values(array_diff($atuais, $novos));

        if (empty($add) && empty($remove)) {
            return;
        }

        $options = new ModifyIndividualStudentsOptions();
        if ($add) $options->setAddStudentIds($add);
        if ($remove) $options->setRemoveStudentIds($remove);

        $request = new ModifyCourseWorkAssigneesRequest();
        $request->setAssigneeMode('INDIVIDUAL_STUDENTS');
        $request->setModifyIndividualStudentsOptions($options);

        try {
            $classroom->courses_courseWork->modifyAssignees(
                $escola->classroom_course_id,
                $courseworkId,
                $request
            );

            Log::info("✓ Professores atualizados", [
                'atividade_id' => $atividade->id,
                'escola' => $escola->nome,
                '+' => count($add),
                '-' => count($remove)
            ]);
        } catch (\Exception $e) {
            Log::error("✗ Erro ao modificar professores", [
                'erro' => $e->getMessage()
            ]);
        }
    }

    /**
     * Adiciona novas escolas (com arquivos corretos)
     */
    public function adicionarNovasEscolas(Atividade $atividade, array $novasEscolasIds): array
    {
        $resultados = ['sucessos' => 0, 'falhas' => 0, 'detalhes' => []];

        DB::beginTransaction();

        try {
            foreach ($novasEscolasIds as $escolaId) {
                $escola = Escola::find($escolaId);

                if (!$escola || !$escola->classroom_course_id) {
                    $resultados['falhas']++;
                    continue;
                }

                // ✅ Pula se já existe
                if ($atividade->escolas()->where('escola_id', $escola->id)->exists()) {
                    continue;
                }

                $resultado = $this->criarAtividadeParaEscola($atividade, $escola);

                if ($resultado['sucesso']) {
                    $resultados['sucessos']++;
                } else {
                    $resultados['falhas']++;
                }

                $resultados['detalhes'][] = $resultado;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Erro ao adicionar escolas', ['erro' => $e->getMessage()]);
            throw $e;
        }

        return $resultados;
    }

    protected function criarAtividadeParaEscola(Atividade $atividade, Escola $escola): array
    {
        try {
            $turma = Turma::where('escola_id', $escola->id)
                ->where('serie_id', $atividade->serie_id)
                ->whereNotNull('classroom_topic_id')
                ->firstOrFail();

            $professores = Professor::where('escola_id', $escola->id)
                ->whereHas('turmas', fn($q) => $q->where('turmas.id', $turma->id))
                ->whereNotNull('classroom_user_id')
                ->get();

            if ($professores->isEmpty()) {
                throw new \Exception('Sem professores');
            }

            // ✅ USA OS ARQUIVOS SALVOS NO BANCO (não busca do Drive novamente)
            $arquivos = $atividade->arquivos_parte ?? [];

            Log::info("Criando atividade para nova escola", [
                'atividade_id' => $atividade->id,
                'escola' => $escola->nome,
                'parte' => $atividade->numero_parte,
                'arquivos' => count($arquivos)
            ]);

            // Cria no Classroom
            $envioService = app(AtividadeEnvioService::class);
            $resultado = $envioService->enviarParaEscola(
                dadosEscola: [
                    'escola' => $escola,
                    'turma_principal' => $turma,
                    'professores' => $professores,
                    'classroom_course_id' => $escola->classroom_course_id
                ],
                titulo: $atividade->titulo,
                descricao: $atividade->descricao,
                arquivos: $arquivos, // ✅ Arquivos corretos da parte
                numeroParte: $atividade->numero_parte,
                totalPartes: $atividade->total_partes
            );

            if (!$resultado['sucesso']) {
                throw new \Exception($resultado['erro'] ?? 'Erro no Classroom');
            }

            // Vincula escola e professores
            $atividade->escolas()->attach($escola->id, [
                'classroom_coursework_id' => $resultado['coursework_id']
            ]);

            $atividade->professores()->syncWithoutDetaching($professores->pluck('id'));

            Log::info("✓ Nova escola adicionada", [
                'escola' => $escola->nome,
                'coursework' => $resultado['coursework_id'],
                'arquivos' => count($arquivos)
            ]);

            return [
                'sucesso' => true,
                'escola' => $escola->nome,
                'coursework_id' => $resultado['coursework_id']
            ];
        } catch (\Exception $e) {
            Log::error("✗ Falha ao criar para escola", [
                'escola' => $escola->nome ?? 'N/A',
                'erro' => $e->getMessage()
            ]);

            return [
                'sucesso' => false,
                'escola' => $escola->nome ?? 'N/A',
                'erro' => $e->getMessage()
            ];
        }
    }
}
