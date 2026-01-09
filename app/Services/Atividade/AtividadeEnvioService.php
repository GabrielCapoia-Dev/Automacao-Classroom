<?php

namespace App\Services\Atividade;

use App\Models\Atividade;
use App\Models\Escola;
use App\Models\Turma;
use App\Services\GoogleMainService;
use Google\Service\Classroom;
use Google\Service\Classroom\CourseWork;
use Google\Service\Classroom\Material;
use Google\Service\Classroom\Link;
use Illuminate\Support\Facades\Log;
use Google\Service\Classroom\DriveFile;
use Google\Service\Classroom\SharedDriveFile;

class AtividadeEnvioService
{
    protected bool $cancelado = false;
    protected int $maxTentativas = 3;
    protected int $arquivosPorParte = 5;

    public function __construct(
        protected GoogleMainService $googleService
    ) {}

    public function cancelar(): void
    {
        $this->cancelado = true;
    }

    public function foiCancelado(): bool
    {
        return $this->cancelado;
    }

    public function enviarAtividade(array $data, ?callable $progressCallback = null): array
    {
        $this->cancelado = false;

        $titulo = $data['titulo'];
        $descricao = $data['descricao'] ?? '';
        $serieId = $data['serie_id'];
        $escolaIds = $data['escolas'] ?? [];
        $arquivos = $data['arquivos_drive'] ?? [];

        // Remove "all" e garante valores únicos
        $escolaIds = array_values(array_unique(array_filter($escolaIds, fn($id) => $id !== 'all')));

        Log::info('=== INICIANDO ENVIO DE ATIVIDADE ===', [
            'titulo' => $titulo,
            'serie_id' => $serieId,
            'escolas_selecionadas' => $escolaIds,
            'total_arquivos' => count($arquivos)
        ]);

        if (empty($escolaIds)) {
            throw new \Exception('Nenhuma escola selecionada');
        }

        // Divide os arquivos em partes
        $partes = $this->dividirArquivosEmPartes($arquivos);
        $totalPartes = count($partes);

        Log::info('Arquivos divididos em partes', [
            'total_partes' => $totalPartes,
            'arquivos_por_parte' => array_map(fn($p) => count($p), $partes)
        ]);

        // Busca turmas e professores por escola
        $escolasComDados = $this->montarDadosEnvio($escolaIds, $serieId);

        Log::info('=== DADOS MONTADOS ===', [
            'escolas_validas' => count($escolasComDados),
            'escolas_detalhes' => array_map(fn($e) => [
                'nome' => $e['escola']->nome,
                'id' => $e['escola']->id,
                'turmas' => $e['turmas']->pluck('nome')->toArray(),
                'professores' => $e['professores']->count()
            ], $escolasComDados)
        ]);

        if (empty($escolasComDados)) {
            throw new \Exception('Nenhuma escola válida encontrada com turmas e professores vinculados à série selecionada.');
        }

        $resultados = [
            'total_escolas' => count($escolasComDados),
            'total_partes' => $totalPartes,
            'total_envios' => count($escolasComDados) * $totalPartes,
            'sucessos' => 0,
            'falhas' => 0,
            'cancelado' => false,
            'detalhes' => []
        ];

        Log::info('=== INÍCIO DO ENVIO ===', [
            'total_atividades_a_criar' => $resultados['total_envios'],
            'estrategia' => 'Enviar todas as escolas para Parte 1, depois todas para Parte 2, etc.'
        ]);

        // Envia parte por parte para todas as escolas
        foreach ($partes as $numeroParte => $arquivosDaParte) {
            if ($this->foiCancelado()) {
                $resultados['cancelado'] = true;
                break;
            }

            $tituloComParte = $totalPartes > 1
                ? "Parte {$numeroParte} - {$titulo}"
                : $titulo;

            Log::info("========== INICIANDO PARTE {$numeroParte}/{$totalPartes} ==========", [
                'titulo' => $tituloComParte,
                'arquivos' => count($arquivosDaParte)
            ]);

            foreach ($escolasComDados as $index => $dadosEscola) {
                if ($this->foiCancelado()) {
                    $resultados['cancelado'] = true;
                    break 2;
                }

                Log::info("  → Enviando para escola " . ($index + 1) . "/" . count($escolasComDados), [
                    'escola' => $dadosEscola['escola']->nome,
                    'parte' => $numeroParte
                ]);

                $resultado = $this->enviarParaEscola(
                    $dadosEscola,
                    $tituloComParte,
                    $descricao,
                    $arquivosDaParte,
                    $numeroParte,
                    $totalPartes
                );

                if ($resultado['sucesso']) {
                    $resultados['sucessos']++;
                    Log::info("  ✓ Sucesso!", ['escola' => $dadosEscola['escola']->nome]);
                } else {
                    $resultados['falhas']++;
                    Log::error("  ✗ Falha!", [
                        'escola' => $dadosEscola['escola']->nome,
                        'erro' => $resultado['erro']
                    ]);
                }

                $resultados['detalhes'][] = $resultado;

                if ($progressCallback) {
                    $progressCallback([
                        'escola' => $dadosEscola['escola']->nome,
                        'parte' => $numeroParte,
                        'total_partes' => $totalPartes,
                        'progresso' => ($resultados['sucessos'] + $resultados['falhas']) / $resultados['total_envios'] * 100,
                        'resultado' => $resultado
                    ]);
                }

                usleep(500000); // 0.5 segundo
            }

            Log::info("========== PARTE {$numeroParte} CONCLUÍDA ==========", [
                'sucessos_ate_agora' => $resultados['sucessos'],
                'falhas_ate_agora' => $resultados['falhas']
            ]);
        }

        Log::info('=== ENVIO FINALIZADO ===', [
            'total_sucessos' => $resultados['sucessos'],
            'total_falhas' => $resultados['falhas'],
            'cancelado' => $resultados['cancelado']
        ]);

        return $resultados;
    }

    protected function dividirArquivosEmPartes(array $arquivos): array
    {
        if (empty($arquivos)) {
            return [1 => []];
        }

        $totalArquivos = count($arquivos);

        if ($totalArquivos <= $this->arquivosPorParte) {
            return [1 => $arquivos];
        }

        $partes = [];
        $numeroPartes = (int) ceil($totalArquivos / $this->arquivosPorParte);

        for ($i = 0; $i < $numeroPartes; $i++) {
            $offset = $i * $this->arquivosPorParte;
            $partes[$i + 1] = array_slice($arquivos, $offset, $this->arquivosPorParte);
        }

        return $partes;
    }

    protected function montarDadosEnvio(array $escolaIds, int $serieId): array
    {
        $dados = [];
        $escolasProcessadas = []; // Evita duplicatas

        foreach ($escolaIds as $escolaId) {
            // Evita processar a mesma escola duas vezes
            if (in_array($escolaId, $escolasProcessadas)) {
                Log::warning("Escola duplicada ignorada", ['escola_id' => $escolaId]);
                continue;
            }

            $escola = Escola::find($escolaId);

            if (!$escola || !$escola->classroom_course_id) {
                Log::warning("Escola ignorada: sem classroom_course_id", [
                    'escola_id' => $escolaId,
                    'existe' => $escola ? 'sim' : 'não'
                ]);
                continue;
            }

            $turmas = Turma::where('escola_id', $escolaId)
                ->where('serie_id', $serieId)
                ->whereNotNull('classroom_topic_id')
                ->get();

            if ($turmas->isEmpty()) {
                Log::warning("Escola ignorada: sem turmas da série com topic_id", [
                    'escola_id' => $escolaId,
                    'escola_nome' => $escola->nome,
                    'serie_id' => $serieId
                ]);
                continue;
            }

            $turmaIds = $turmas->pluck('id')->toArray();

            $professores = \App\Models\Professor::whereHas('turmas', function ($q) use ($turmaIds) {
                $q->whereIn('turmas.id', $turmaIds);
            })
                ->where('escola_id', $escolaId)
                ->whereNotNull('classroom_user_id')
                ->get();

            if ($professores->isEmpty()) {
                Log::warning("Escola ignorada: sem professores vinculados", [
                    'escola_id' => $escolaId,
                    'escola_nome' => $escola->nome,
                    'turmas_ids' => $turmaIds
                ]);
                continue;
            }

            $escolasProcessadas[] = $escolaId;

            $dados[] = [
                'escola' => $escola,
                'turmas' => $turmas,
                'turma_principal' => $turmas->first(),
                'professores' => $professores,
                'classroom_course_id' => $escola->classroom_course_id
            ];
        }

        return $dados;
    }

    protected function enviarParaEscola(
        array $dadosEscola,
        string $titulo,
        string $descricao,
        array $arquivos,
        int $numeroParte,
        int $totalPartes
    ): array {
        $escola = $dadosEscola['escola'];
        $turmaPrincipal = $dadosEscola['turma_principal'];
        $professores = $dadosEscola['professores'];
        $courseId = $dadosEscola['classroom_course_id'];

        $tentativa = 0;
        $ultimoErro = null;

        while ($tentativa < $this->maxTentativas) {
            if ($this->foiCancelado()) {
                return [
                    'sucesso' => false,
                    'escola' => $escola->nome,
                    'parte' => $numeroParte,
                    'erro' => 'Cancelado pelo usuário',
                    'tentativa' => $tentativa
                ];
            }

            $tentativa++;

            try {
                $client = $this->googleService->getGoogleClient();
                $classroom = app(Classroom::class, ['client' => $client]);

                $courseWork = new CourseWork();
                $courseWork->setTitle($titulo);
                $courseWork->setDescription($descricao);
                $courseWork->setState('PUBLISHED');
                $courseWork->setWorkType('ASSIGNMENT');
                $courseWork->setTopicId($turmaPrincipal->classroom_topic_id);

                if (!empty($arquivos)) {
                    $materials = [];

                    foreach ($arquivos as $arquivo) {
                        /**
                         * @var mixed
                         */
                        $driveFile = new DriveFile();
                        $driveFile->setId($arquivo['id']); // ID do arquivo no Drive
                        $driveFile->setTitle($arquivo['nome']);
                        $driveFile->setShareMode('STUDENT_COPY'); // 🔥 ESSENCIAL

                        $material = new Material();
                        $material->setDriveFile($driveFile);

                        $materials[] = $material;
                    }

                    $courseWork->setMaterials($materials);
                }

                $studentIds = $professores->pluck('classroom_user_id')->filter()->values()->toArray();

                if (!empty($studentIds)) {
                    $individualStudentsOptions = new \Google\Service\Classroom\IndividualStudentsOptions();
                    $individualStudentsOptions->setStudentIds($studentIds);
                    $courseWork->setIndividualStudentsOptions($individualStudentsOptions);
                    $courseWork->setAssigneeMode('INDIVIDUAL_STUDENTS');
                } else {
                    $courseWork->setAssigneeMode('ALL_STUDENTS');
                }

                // Envia para o Classroom
                $resultado = $classroom->courses_courseWork->create($courseId, $courseWork);

                if (!$resultado || !$resultado->getId()) {
                    throw new \Exception('Atividade criada mas sem ID retornado');
                }

                // Salva no banco de dados
                $atividade = Atividade::create([
                    'google_account_id' => $this->googleService->getMainAccount()->id,
                    'turma_id' => $turmaPrincipal->id,
                    'serie_id' => $turmaPrincipal->serie_id,
                    'titulo' => $titulo,
                    'descricao' => $descricao,
                ]);

                // Vincula à escola com o classroom_coursework_id no pivot
                $atividade->escolas()->attach($escola->id, [
                    'classroom_coursework_id' => $resultado->getId()
                ]);

                // Vincula aos professores
                $professorIds = $professores->pluck('id')->toArray();
                if (!empty($professorIds)) {
                    $atividade->professores()->attach($professorIds);
                    Log::info("Professores vinculados", [
                        'atividade_id' => $atividade->id,
                        'professor_ids' => $professorIds
                    ]);

                    // Verifica se realmente salvou
                    $atividadeComProfs = Atividade::with('professores')->find($atividade->id);
                    Log::info("Professores carregados após attach", [
                        'count' => $atividadeComProfs->professores->count(),
                        'nomes' => $atividadeComProfs->professores->pluck('nome')->toArray()
                    ]);
                }
                Log::info("✓ Atividade criada com sucesso", [
                    'escola' => $escola->nome,
                    'turma' => $turmaPrincipal->nome,
                    'parte' => $numeroParte,
                    'coursework_id' => $resultado->getId(),
                    'professores_vinculados' => count($professorIds),
                    'tentativa' => $tentativa
                ]);

                // SUCESSO: retorna imediatamente
                return [
                    'sucesso' => true,
                    'escola' => $escola->nome,
                    'turma' => $turmaPrincipal->nome,
                    'parte' => $numeroParte,
                    'coursework_id' => $resultado->getId(),
                    'professores_vinculados' => count($professorIds),
                    'tentativa' => $tentativa
                ];
            } catch (\Exception $e) {
                $ultimoErro = $e->getMessage();

                Log::warning("✗ Erro na tentativa {$tentativa}/{$this->maxTentativas}", [
                    'escola' => $escola->nome,
                    'turma' => $turmaPrincipal->nome,
                    'parte' => $numeroParte,
                    'erro' => $ultimoErro
                ]);

                if ($tentativa < $this->maxTentativas) {
                    $tempoEspera = 2 * $tentativa;
                    Log::info("  Aguardando {$tempoEspera}s antes da próxima tentativa...");
                    sleep($tempoEspera);
                }
            }
        }

        Log::error("✗ Falha após {$this->maxTentativas} tentativas", [
            'escola' => $escola->nome,
            'turma' => $turmaPrincipal->nome,
            'parte' => $numeroParte,
            'ultimo_erro' => $ultimoErro
        ]);

        return [
            'sucesso' => false,
            'escola' => $escola->nome,
            'turma' => $turmaPrincipal->nome,
            'parte' => $numeroParte,
            'erro' => $ultimoErro,
            'tentativa' => $tentativa
        ];
    }
}
