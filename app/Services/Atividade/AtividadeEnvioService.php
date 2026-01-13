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
    public bool $cancelado = false;
    public int $maxTentativas = 3;
    public int $arquivosPorParte = 7;

    public function __construct(
        public GoogleMainService $googleService
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

        $partes = $this->dividirArquivosEmPartes($arquivos);
        $totalPartes = count($partes);
        $escolasComDados = $this->montarDadosEnvio($escolaIds, $serieId);

        if (empty($escolasComDados)) {
            throw new \Exception('Nenhuma escola válida encontrada.');
        }

        $resultados = [
            'total_escolas' => count($escolasComDados),
            'total_partes' => $totalPartes,
            'total_envios' => count($escolasComDados) * $totalPartes,
            'sucessos' => 0,
            'falhas' => 0,
            'cancelado' => false,
            'detalhes' => [],
            'atividades_criadas' => []
        ];

        // 🔥 CRIA UMA ATIVIDADE POR PARTE (não por escola)
        foreach ($partes as $numeroParte => $arquivosDaParte) {
            if ($this->foiCancelado()) {
                $resultados['cancelado'] = true;
                break;
            }

            $tituloComParte = $totalPartes > 1
                ? "Parte {$numeroParte} - {$titulo}"
                : $titulo;

            // ✅ CRIA UMA ÚNICA ATIVIDADE NO BANCO
            $atividade = Atividade::create([
                'google_account_id' => $this->googleService->getMainAccount()->id,
                'turma_id' => null,
                'serie_id' => $serieId,
                'titulo' => $tituloComParte,
                'titulo_original' => $titulo,
                'numero_parte' => $numeroParte,
                'total_partes' => $totalPartes,
                'descricao' => $descricao,
                'drive_folder_id' => $data['drive_folder_id'] ?? null,
                'drive_folder_url' => $data['drive_folder_url'] ?? null,
                'arquivos_parte' => $arquivosDaParte, // ✅ Salva os arquivos desta parte
            ]);

            Log::info("========== INICIANDO PARTE {$numeroParte}/{$totalPartes} ==========", [
                'titulo' => $tituloComParte,
                'atividade_id' => $atividade->id,
                'arquivos' => count($arquivosDaParte)
            ]);

            $todosOsProfessores = [];

            // Envia para cada escola
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

                    // ✅ VINCULA A ESCOLA À ATIVIDADE
                    $atividade->escolas()->attach($dadosEscola['escola']->id, [
                        'classroom_coursework_id' => $resultado['coursework_id']
                    ]);

                    // Coleta IDs dos professores
                    $todosOsProfessores = array_merge(
                        $todosOsProfessores,
                        $dadosEscola['professores']->pluck('id')->toArray()
                    );

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

                usleep(500000);
            }

            // ✅ VINCULA TODOS OS PROFESSORES DE UMA VEZ
            if (!empty($todosOsProfessores)) {
                $atividade->professores()->attach(array_unique($todosOsProfessores));
            }

            $resultados['atividades_criadas'][] = $atividade->id;

            Log::info("========== PARTE {$numeroParte} CONCLUÍDA ==========", [
                'atividade_id' => $atividade->id,
                'escolas_vinculadas' => $atividade->escolas->count(),
                'professores_vinculados' => $atividade->professores->count()
            ]);
        }

        Log::info('=== ENVIO FINALIZADO ===', [
            'total_sucessos' => $resultados['sucessos'],
            'total_falhas' => $resultados['falhas'],
            'atividades_criadas' => $resultados['atividades_criadas'],
            'cancelado' => $resultados['cancelado']
        ]);

        return $resultados;
    }

    public function dividirArquivosEmPartes(array $arquivos): array
    {
        if (empty($arquivos)) {
            return [1 => []];
        }

        $totalArquivos = count($arquivos);

        // Se tiver 7 ou menos arquivos, retorna tudo na parte 1
        if ($totalArquivos <= $this->arquivosPorParte) {
            return [1 => $arquivos];
        }

        // ✅ Separa arquivos prioritários (Plano de ensino)
        $arquivosPrioritarios = [];
        $arquivosNormais = [];

        foreach ($arquivos as $arquivo) {
            $nome = $arquivo['nome'] ?? '';

            // Verifica se contém "Plano de ensino" (case-insensitive)
            if (stripos($nome, 'Plano de ensino') !== false) {
                $arquivosPrioritarios[] = $arquivo;
            } else {
                $arquivosNormais[] = $arquivo;
            }
        }

        Log::info("Divisão de arquivos", [
            'total' => $totalArquivos,
            'prioritarios' => count($arquivosPrioritarios),
            'normais' => count($arquivosNormais),
            'limite_por_parte' => $this->arquivosPorParte
        ]);

        $partes = [];
        $numeroParte = 1;

        // ✅ PARTE 1: Prioriza "Plano de ensino"
        $parte1 = [];

        // Adiciona arquivos prioritários primeiro
        foreach ($arquivosPrioritarios as $arquivo) {
            if (count($parte1) < $this->arquivosPorParte) {
                $parte1[] = $arquivo;
            } else {
                // Se já encheu com prioritários, joga o resto nos arquivos normais
                $arquivosNormais[] = $arquivo;
            }
        }

        // Completa a parte 1 com arquivos normais se ainda tiver espaço
        while (count($parte1) < $this->arquivosPorParte && !empty($arquivosNormais)) {
            $parte1[] = array_shift($arquivosNormais);
        }

        $partes[1] = $parte1;

        Log::info("Parte 1 criada", [
            'total_arquivos' => count($parte1),
            'prioritarios_incluidos' => count(array_filter($parte1, function ($arq) {
                return stripos($arq['nome'] ?? '', 'Plano de ensino') !== false;
            }))
        ]);

        // ✅ DEMAIS PARTES: Distribui os arquivos restantes
        $arquivosRestantes = $arquivosNormais;
        $numeroParte = 2;

        while (!empty($arquivosRestantes)) {
            $partes[$numeroParte] = array_splice($arquivosRestantes, 0, $this->arquivosPorParte);

            Log::info("Parte {$numeroParte} criada", [
                'total_arquivos' => count($partes[$numeroParte])
            ]);

            $numeroParte++;
        }

        Log::info("Divisão finalizada", [
            'total_partes' => count($partes),
            'distribuicao' => array_map('count', $partes)
        ]);

        return $partes;
    }   

    public function montarDadosEnvio(array $escolaIds, int $serieId): array
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

    public function enviarParaEscola(
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
                        $driveFile = new DriveFile();
                        $driveFile->setId($arquivo['id']);
                        $driveFile->setTitle($arquivo['nome']);

                        $sharedDriveFile = new SharedDriveFile();
                        $sharedDriveFile->setDriveFile($driveFile);
                        $sharedDriveFile->setShareMode('STUDENT_COPY');

                        $material = new Material();
                        $material->setDriveFile($sharedDriveFile);

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

                $resultado = $classroom->courses_courseWork->create($courseId, $courseWork);

                if (!$resultado || !$resultado->getId()) {
                    throw new \Exception('Atividade criada mas sem ID retornado');
                }

                Log::info("✓ Atividade criada no Classroom", [
                    'escola' => $escola->nome,
                    'turma' => $turmaPrincipal->nome,
                    'coursework_id' => $resultado->getId()
                ]);

                // ✅ RETORNA APENAS O ID DO COURSEWORK
                return [
                    'sucesso' => true,
                    'escola' => $escola->nome,
                    'turma' => $turmaPrincipal->nome,
                    'parte' => $numeroParte,
                    'coursework_id' => $resultado->getId(),
                    'tentativa' => $tentativa
                ];
            } catch (\Exception $e) {
                $ultimoErro = $e->getMessage();

                Log::warning("✗ Erro na tentativa {$tentativa}/{$this->maxTentativas}", [
                    'escola' => $escola->nome,
                    'erro' => $ultimoErro
                ]);

                if ($tentativa < $this->maxTentativas) {
                    $tempoEspera = 2 * $tentativa;
                    sleep($tempoEspera);
                }
            }
        }

        return [
            'sucesso' => false,
            'escola' => $escola->nome,
            'turma' => $turmaPrincipal->nome ?? 'N/A',
            'parte' => $numeroParte,
            'erro' => $ultimoErro,
            'tentativa' => $tentativa
        ];
    }
}
