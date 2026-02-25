<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AtividadeResource\Pages;
use App\Models\Atividade;
use App\Models\Professor;
use App\Models\Serie;
use App\Services\GoogleDriveService;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use App\Services\Serie\SerieService;

class AtividadeResource extends Resource
{
    protected static ?string $model = Atividade::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static ?string $modelLabel = 'Rotina';
    protected static ?string $navigationGroup = "Rotinas";
    public static ?string $pluralModelLabel = 'Rotinas';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Campos compartilhados
                Fieldset::make('Configurações Gerais')
                    ->schema([
                        Select::make('serie_id')
                            ->label('Série')
                            ->live()
                            ->required()
                            ->columnSpan(1)
                            ->options(
                                Serie::fromMainAccount()->orderBy('nome')->pluck('nome', 'id')
                            ),

                        Textarea::make('descricao')
                            ->label('Descrição (compartilhada)')
                            ->rows(4)
                            ->columnSpanFull(),

                        CheckboxList::make('escolas')
                            ->label('Escolas')
                            ->columnSpanFull()
                            ->reactive()
                            ->columns(3)
                            ->helperText(fn(callable $get) => $get('serie_id') ? null : 'Selecione uma Série')
                            ->options(function (callable $get) {
                                $serieId = $get('serie_id');

                                if (!$serieId) {
                                    return [];
                                }

                                $escolas = \App\Models\Escola::whereHas('turmas', function ($q) use ($serieId) {
                                    $q->where('serie_id', $serieId)
                                        ->whereHas('professores');
                                })
                                    ->orderBy('nome')
                                    ->pluck('nome', 'id')
                                    ->toArray();

                                return empty($escolas)
                                    ? []
                                    : ['all' => 'Todas'] + $escolas;
                            })
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if (!$state || !in_array('all', $state, true)) {
                                    return;
                                }

                                $serieId = $get('serie_id');

                                if (!$serieId) {
                                    return;
                                }

                                $ids = \App\Models\Escola::whereHas('turmas', function ($q) use ($serieId) {
                                    $q->where('serie_id', $serieId)
                                        ->whereHas('professores');
                                })
                                    ->pluck('id')
                                    ->toArray();

                                $set('escolas', $ids);
                            }),
                    ]),

                // Repeater de atividades
                Repeater::make('atividades')
                    ->label('Atividades para Enviar')
                    ->addActionLabel('Adicionar Atividade')
                    ->reorderable(false)
                    ->columnSpanFull()
                    ->defaultItems(1)
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('titulo')
                                    ->label('Título')
                                    ->required()
                                    ->columnSpan(5),

                                TextInput::make('url')
                                    ->label('URL da Pasta')
                                    ->url()
                                    ->required()
                                    ->columnSpan(5)
                                    ->live(),

                                Actions::make([
                                    Action::make('carregarArquivos')
                                        ->label('Carregar')
                                        ->icon('heroicon-o-arrow-down-tray')
                                        ->color('success')
                                        ->size('sm')
                                        ->disabled(fn() => !GoogleDriveService::temContaConectada())
                                        ->action(function (callable $get, callable $set, $arguments) {
                                            $url = $get('url');

                                            if (!$url) {
                                                Notification::make()
                                                    ->title('Erro')
                                                    ->body('Insira uma URL válida do Google Drive')
                                                    ->danger()
                                                    ->send();
                                                return;
                                            }

                                            try {
                                                preg_match('/folders\/([a-zA-Z0-9_-]+)/', $url, $matches);

                                                if (empty($matches[1])) {
                                                    throw new \Exception('URL inválida. Use uma URL de pasta compartilhada.');
                                                }

                                                $folderId = $matches[1];
                                                $driveService = new GoogleDriveService();

                                                if (!$driveService->validarFolderId($folderId)) {
                                                    throw new \Exception('Pasta não encontrada ou sem permissão.');
                                                }

                                                $arquivos = $driveService->listarArquivos($folderId);

                                                if (empty($arquivos)) {
                                                    Notification::make()
                                                        ->title('Pasta vazia')
                                                        ->warning()
                                                        ->send();
                                                    $set('arquivos_drive', []);
                                                    return;
                                                }

                                                $set('drive_folder_id', $folderId);
                                                $set('arquivos_drive', $arquivos);

                                                Notification::make()
                                                    ->title('Sucesso!')
                                                    ->body(count($arquivos) . ' arquivo(s) carregado(s)')
                                                    ->success()
                                                    ->send();
                                            } catch (\Exception $e) {
                                                Notification::make()
                                                    ->title('Erro')
                                                    ->body($e->getMessage())
                                                    ->danger()
                                                    ->send();
                                            }
                                        })
                                ])
                                    ->columnSpan(2)
                                    ->extraAttributes(['style' => 'margin-top: 1.5rem;']),
                            ]),

                        // Preview dos arquivos
                        Forms\Components\Placeholder::make('preview_arquivos')
                            ->label('')
                            ->content(function (callable $get) {
                                $arquivos = $get('arquivos_drive') ?? [];

                                if (empty($arquivos)) {
                                    return 'Nenhum arquivo carregado';
                                }

                                $total = count($arquivos);
                                $partes = (int) ceil($total / 10);
                                $nomes = collect($arquivos)->pluck('name')->take(3)->implode(', ');

                                return "📁 {$total} arquivo(s) | {$partes} parte(s) | {$nomes}" . ($total > 3 ? '...' : '');
                            })
                            ->hidden(fn(callable $get) => empty($get('arquivos_drive'))),

                        // Campos ocultos
                        Forms\Components\Hidden::make('drive_folder_id'),
                        Forms\Components\Hidden::make('arquivos_drive')
                            ->default([]),
                    ])
                    ->itemLabel(function (array $state): ?string {
                        $titulo = $state['titulo'] ?? 'Nova Atividade';
                        $arquivos = $state['arquivos_drive'] ?? [];
                        $total = count($arquivos);

                        return $total > 0
                            ? "{$titulo} ({$total} arquivos)"
                            : $titulo;
                    }),

                // Resumo e botão de envio
                Fieldset::make('Enviar')
                    ->schema([
                        Forms\Components\Placeholder::make('resumo')
                            ->label('Resumo do Envio')
                            ->content(function (callable $get) {
                                $atividades = $get('atividades') ?? [];
                                $escolas = array_filter($get('escolas') ?? [], fn($id) => $id !== 'all');

                                $totalAtividades = 0;
                                $totalPartes = 0;

                                foreach ($atividades as $ativ) {
                                    $arquivos = $ativ['arquivos_drive'] ?? [];
                                    if (!empty($arquivos) && !empty($ativ['titulo'])) {
                                        $totalAtividades++;
                                        $totalPartes += (int) ceil(count($arquivos) / 5);
                                    }
                                }

                                $totalEscolas = count($escolas);
                                $totalEnvios = $totalPartes * $totalEscolas;

                                return "📊 {$totalAtividades} atividade(s) | {$totalPartes} parte(s) | {$totalEscolas} escola(s) | {$totalEnvios} envio(s) total";
                            }),

                        Actions::make([
                            Action::make('enviarTodas')
                                ->label('Enviar Todas as Atividades')
                                ->icon('heroicon-o-paper-airplane')
                                ->color('primary')
                                ->size('lg')
                                ->requiresConfirmation()
                                ->modalHeading('Confirmar Envio em Lote')
                                ->modalDescription(function (callable $get) {
                                    $atividades = $get('atividades') ?? [];
                                    $escolas = array_filter($get('escolas') ?? [], fn($id) => $id !== 'all');

                                    $totalAtividades = 0;
                                    $totalPartes = 0;

                                    foreach ($atividades as $ativ) {
                                        $arquivos = $ativ['arquivos_drive'] ?? [];
                                        if (!empty($arquivos) && !empty($ativ['titulo'])) {
                                            $totalAtividades++;
                                            $totalPartes += (int) ceil(count($arquivos) / 5);
                                        }
                                    }

                                    $totalEnvios = $totalPartes * count($escolas);

                                    return "Você está prestes a enviar {$totalAtividades} atividade(s) para " . count($escolas) . " escola(s). " .
                                        "Total de {$totalEnvios} envios. Este processo pode levar vários minutos.";
                                })
                                ->modalSubmitActionLabel('Sim, enviar todas!')
                                ->disabled(function (callable $get) {
                                    $serie = $get('serie_id');
                                    $escolas = array_filter($get('escolas') ?? [], fn($id) => $id !== 'all');
                                    $atividades = $get('atividades') ?? [];

                                    if (empty($serie) || empty($escolas)) {
                                        return true;
                                    }

                                    // Verifica se tem pelo menos uma atividade válida
                                    foreach ($atividades as $ativ) {
                                        if (!empty($ativ['titulo']) && !empty($ativ['arquivos_drive'])) {
                                            return false;
                                        }
                                    }

                                    return true;
                                })
                                ->action(function (callable $get, callable $set) {
                                    try {
                                        $envioService = new \App\Services\Atividade\AtividadeEnvioService(
                                            app(\App\Services\GoogleMainService::class)
                                        );

                                        $atividades = $get('atividades') ?? [];
                                        $escolas = array_filter($get('escolas') ?? [], fn($id) => $id !== 'all');
                                        $serieId = $get('serie_id');
                                        $descricao = $get('descricao');

                                        $totalSucessos = 0;
                                        $totalFalhas = 0;
                                        $atividadesEnviadas = 0;

                                        foreach ($atividades as $index => $ativ) {
                                            if (empty($ativ['titulo']) || empty($ativ['arquivos_drive'])) {
                                                continue;
                                            }

                                            // Monta dados para o serviço
                                            $dadosAtividade = [
                                                'titulo' => $ativ['titulo'],
                                                'descricao' => $descricao,
                                                'serie_id' => $serieId,
                                                'escolas' => $escolas,
                                                'drive_folder_id' => $ativ['drive_folder_id'],
                                                'arquivos_drive' => $ativ['arquivos_drive'],
                                            ];

                                            $resultados = $envioService->enviarAtividade(
                                                $dadosAtividade,
                                            );

                                            $totalSucessos += $resultados['sucessos'];
                                            $totalFalhas += $resultados['falhas'];
                                            $atividadesEnviadas++;
                                        }

                                        $mensagem = "Envio concluído! {$atividadesEnviadas} atividade(s) | " .
                                            "Sucessos: {$totalSucessos} | Falhas: {$totalFalhas}";

                                        if ($totalFalhas > 0) {
                                            Notification::make()
                                                ->title('Envio Concluído com Erros')
                                                ->body($mensagem)
                                                ->warning()
                                                ->send();
                                        } else {
                                            Notification::make()
                                                ->title('Sucesso!')
                                                ->body($mensagem)
                                                ->success()
                                                ->send();
                                        }

                                        // Limpa o formulário
                                        $set('atividades', [['titulo' => '', 'url' => '', 'arquivos_drive' => []]]);
                                    } catch (\Exception $e) {

                                        Notification::make()
                                            ->title('Erro ao Enviar')
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                })
                        ])
                    ])
                    ->hidden(function (callable $get) {
                        $escolas = array_filter($get('escolas') ?? [], fn($id) => $id !== 'all');
                        $atividades = $get('atividades') ?? [];

                        if (empty($escolas)) {
                            return true;
                        }

                        foreach ($atividades as $ativ) {
                            if (!empty($ativ['arquivos_drive'])) {
                                return false;
                            }
                        }

                        return true;
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->apenasAtividadesPrincipais())
            ->columns([
                Tables\Columns\TextColumn::make('titulo_original')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->default(fn($record) => $record->titulo),

                Tables\Columns\TextColumn::make('total_partes')
                    ->label('Partes')
                    ->formatStateUsing(fn($state) => $state > 1 ? "{$state} partes" : '1 parte')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('serie.nome')
                    ->label('Série')
                    ->sortable()
                    ->badge()
                    ->color(fn($record) => app(SerieService::class)->getCorPorNomeSerie($record->serie?->nome))
                    ->searchable(),

                Tables\Columns\TextColumn::make('escolas_count')
                    ->label('Nº Escolas')
                    ->counts('escolas')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('professores_count')
                    ->label('Nº Professores')
                    ->counts('professores')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('reajustarTodasAtividades')
                    ->label('Reajustar Todos os Professores')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reajustar Professores de TODAS as Atividades')
                    ->modalDescription('⚠️ Esta ação vai sincronizar os professores de TODAS as atividades com base nos vínculos atuais das turmas no SISTEMA (banco de dados). Esta operação pode levar vários minutos.')
                    ->modalSubmitActionLabel('Sim, Reajustar Todas')
                    ->action(function () {

                        try {

                            $editService = new \App\Services\Atividade\AtividadeEditService(
                                app(\App\Services\GoogleMainService::class)
                            );

                            $atividadesPrincipais = \App\Models\Atividade::apenasAtividadesPrincipais()->get();

                            $totalAtualizadas = 0;

                            DB::beginTransaction();

                            foreach ($atividadesPrincipais as $atividadePrincipal) {

                                $todasAsPartes = $atividadePrincipal->todasAsPartes();

                                foreach ($todasAsPartes as $parte) {

                                    $parte->load(['escolas', 'serie']);

                                    foreach ($parte->escolas as $escola) {

                                        // Professores corretos baseados nas turmas atuais
                                        $professoresCorretos = \App\Models\Professor::query()
                                            ->where('escola_id', $escola->id)
                                            ->whereHas('turmas', function ($q) use ($parte) {
                                                $q->where('serie_id', $parte->serie_id);
                                            })
                                            ->whereNotNull('classroom_user_id')
                                            ->pluck('id')
                                            ->toArray();

                                        // Atualiza banco
                                        $parte->professores()->syncWithoutDetaching($professoresCorretos);

                                        // 🔥 Atualiza GOOGLE CLASSROOM
                                        $editService->atualizarAtividade($parte, [
                                            'editar_todas_escolas_existentes' => true,
                                            'professores_ids' => $professoresCorretos,
                                        ]);

                                        $totalAtualizadas++;
                                    }
                                }
                            }

                            DB::commit();

                            \Filament\Notifications\Notification::make()
                                ->title('Reajuste completo')
                                ->body("{$totalAtualizadas} atividades sincronizadas com o Classroom")
                                ->success()
                                ->persistent()
                                ->send();
                        } catch (\Throwable $e) {

                            DB::rollBack();

                            \Filament\Notifications\Notification::make()
                                ->title('Erro ao sincronizar')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

            ])
            ->filters([
                Tables\Filters\SelectFilter::make('serie')
                    ->label('Série')
                    ->relationship('serie', 'nome')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('escolas')
                    ->label('Escolas')
                    ->relationship('escolas', 'nome')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('editar')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->slideOver()
                    ->modalWidth('5xl')
                    ->modalHeading(fn(Atividade $record) => "Editar: {$record->titulo_original}")
                    ->fillForm(function (Atividade $record): array {
                        $formData = [
                            'titulo' => $record->titulo_original ?? $record->titulo,
                            'descricao' => $record->descricao,
                            'editar_todas_escolas_existentes' => true,
                            'escolas_existentes_selecionadas' => [],
                            'novas_escolas' => [],
                        ];

                        foreach ($record->escolas as $escola) {
                            $professoresDaEscola = $record->professores()
                                ->where('escola_id', $escola->id)
                                ->pluck('professores.id')
                                ->toArray();

                            $formData["professores_escola_{$escola->id}"] = $professoresDaEscola;
                        }

                        return $formData;
                    })
                    ->form(fn(Atividade $record) => [

                        Forms\Components\Section::make('Editar Escolas Existentes')
                            ->description('Escolha quais escolas que JÁ receberam esta atividade você deseja atualizar')
                            ->schema([
                                Forms\Components\Toggle::make('editar_todas_escolas_existentes')
                                    ->label('Atualizar TODAS as escolas que já receberam')
                                    ->default(true)
                                    ->live()
                                    ->helperText('Quando ativado, as mudanças serão aplicadas em todas as escolas que já receberam esta atividade'),

                                Forms\Components\CheckboxList::make('escolas_existentes_selecionadas')
                                    ->label('Ou selecione escolas específicas para atualizar:')
                                    ->options(fn() => $record->escolas->pluck('nome', 'id'))
                                    ->hidden(fn(callable $get) => $get('editar_todas_escolas_existentes'))
                                    ->columns(2)
                                    ->helperText('Selecione apenas as escolas que você deseja atualizar'),
                            ]),

                        Forms\Components\Section::make('Adicionar Novas Escolas')
                            ->description('Envie esta atividade para escolas que ainda NÃO receberam')
                            ->schema([
                                Forms\Components\CheckboxList::make('novas_escolas')
                                    ->label('Selecione novas escolas para receber esta atividade')
                                    ->options(function () use ($record) {
                                        $serieId = $record->serie_id;
                                        $escolasQueJaReceberam = $record->escolas->pluck('id')->toArray();

                                        return \App\Models\Escola::whereHas('turmas', function ($q) use ($serieId) {
                                            $q->where('serie_id', $serieId)
                                                ->whereHas('professores');
                                        })
                                            ->whereNotIn('id', $escolasQueJaReceberam)
                                            ->whereNotNull('classroom_course_id')
                                            ->orderBy('nome')
                                            ->pluck('nome', 'id')
                                            ->toArray();
                                    })
                                    ->live()
                                    ->columns(2)
                                    ->helperText('Estas escolas têm a mesma série mas ainda não receberam esta atividade')
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) use ($record) {
                                        $novasEscolas = $state ?? [];
                                        $serieId = $record->serie_id;

                                        foreach ($novasEscolas as $escolaId) {
                                            $professoresDaEscola = Professor::where('escola_id', $escolaId)
                                                ->whereHas('turmas', function ($q) use ($serieId) {
                                                    $q->where('serie_id', $serieId);
                                                })
                                                ->whereNotNull('classroom_user_id')
                                                ->pluck('id')
                                                ->toArray();

                                            $set("professores_escola_{$escolaId}", $professoresDaEscola);
                                        }
                                    }),
                            ])
                            ->collapsible()
                            ->visible(function () use ($record) {
                                $serieId = $record->serie_id;
                                $escolasQueJaReceberam = $record->escolas->pluck('id')->toArray();

                                return \App\Models\Escola::whereHas('turmas', function ($q) use ($serieId) {
                                    $q->where('serie_id', $serieId)
                                        ->whereHas('professores');
                                })
                                    ->whereNotIn('id', $escolasQueJaReceberam)
                                    ->whereNotNull('classroom_course_id')
                                    ->exists();
                            }),

                        Forms\Components\Section::make('Atualizar Dados')
                            ->schema([
                                Forms\Components\TextInput::make('titulo')
                                    ->label('Título')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Este título será atualizado em todas as partes e escolas selecionadas'),

                                Forms\Components\Textarea::make('descricao')
                                    ->label('Descrição')
                                    ->rows(4)
                                    ->live()
                                    ->maxLength(65535),
                            ]),

                        Forms\Components\Section::make('Selecionar Professores')
                            ->description('Selecione quais professores de cada escola devem receber esta atividade')
                            ->schema(function (callable $get) use ($record) {
                                $escolasExistentes = $record->escolas->pluck('id')->toArray();
                                $novasEscolas = $get('novas_escolas') ?? [];
                                $todasEscolas = array_unique(array_merge($escolasExistentes, $novasEscolas));
                                $serieId = $record->serie_id;

                                if (empty($todasEscolas)) {
                                    return [
                                        Forms\Components\Placeholder::make('sem_escolas')
                                            ->label('')
                                            ->content('Nenhuma escola disponível')
                                    ];
                                }

                                $fieldsets = [];

                                $escolas = \App\Models\Escola::whereIn('id', $todasEscolas)
                                    ->orderBy('nome')
                                    ->get();

                                foreach ($escolas as $escola) {
                                    $professoresDaEscola = Professor::where('escola_id', $escola->id)
                                        ->whereHas('turmas', function ($q) use ($serieId) {
                                            $q->where('serie_id', $serieId);
                                        })
                                        ->whereNotNull('classroom_user_id')
                                        ->orderBy('nome')
                                        ->get();

                                    if ($professoresDaEscola->isEmpty()) {
                                        continue;
                                    }

                                    $isNovaEscola = in_array($escola->id, $novasEscolas);
                                    $badge = $isNovaEscola ? ' 🆕 NOVA' : '';
                                    $todosIds = $professoresDaEscola->pluck('id')->toArray();

                                    $fieldsets[] = Forms\Components\Fieldset::make("escola_{$escola->id}")
                                        ->label($escola->nome . $badge)
                                        ->schema([
                                            Forms\Components\Checkbox::make("todos_escola_{$escola->id}")
                                                ->label('Selecionar Todos')
                                                ->live()
                                                ->dehydrated(false)
                                                ->afterStateHydrated(function ($state, callable $get, callable $set) use ($escola, $todosIds) {
                                                    $selecionados = $get("professores_escola_{$escola->id}") ?? [];
                                                    $set("todos_escola_{$escola->id}", count($selecionados) === count($todosIds) && count($todosIds) > 0);
                                                })
                                                ->afterStateUpdated(function ($state, callable $set) use ($escola, $todosIds) {
                                                    $set("professores_escola_{$escola->id}", $state ? $todosIds : []);
                                                }),

                                            Forms\Components\CheckboxList::make("professores_escola_{$escola->id}")
                                                ->label('')
                                                ->options($professoresDaEscola->pluck('nome', 'id'))
                                                ->columns(2)
                                                ->columnSpanFull()
                                                ->live()
                                                ->afterStateUpdated(function ($state, callable $set) use ($escola, $todosIds) {
                                                    $selecionados = $state ?? [];
                                                    $set("todos_escola_{$escola->id}", count($selecionados) === count($todosIds));
                                                })
                                        ])
                                        ->columnSpanFull();
                                }

                                $fieldsets[] = Forms\Components\Hidden::make('professores_ids')
                                    ->dehydrateStateUsing(function (callable $get) use ($escolas) {
                                        $todosProfessores = [];

                                        foreach ($escolas as $escola) {
                                            $professoresDaEscola = $get("professores_escola_{$escola->id}") ?? [];
                                            $todosProfessores = array_merge($todosProfessores, $professoresDaEscola);
                                        }

                                        return array_unique($todosProfessores);
                                    });

                                return $fieldsets;
                            })
                            ->live(),
                    ])
                    ->action(function (Atividade $record, array $data) {
                        try {
                            $escolasExistentes = $record->escolas->pluck('id')->toArray();
                            $novasEscolas = $data['novas_escolas'] ?? [];
                            $todasEscolas = array_unique(array_merge($escolasExistentes, $novasEscolas));

                            $escolasSemProfessores = [];

                            foreach ($todasEscolas as $escolaId) {
                                $professoresDaEscola = $data["professores_escola_{$escolaId}"] ?? [];

                                if (empty($professoresDaEscola)) {
                                    $escola = \App\Models\Escola::find($escolaId);
                                    if ($escola) {
                                        $escolasSemProfessores[] = $escola->nome;
                                    }
                                }
                            }

                            if (!empty($escolasSemProfessores)) {
                                Notification::make()
                                    ->title('Erro de Validação')
                                    ->body('As seguintes escolas precisam ter pelo menos 1 professor selecionado: ' . implode(', ', $escolasSemProfessores))
                                    ->danger()
                                    ->persistent()
                                    ->send();
                                return;
                            }

                            $todosProfessores = [];
                            foreach ($todasEscolas as $escolaId) {
                                $professoresDaEscola = $data["professores_escola_{$escolaId}"] ?? [];
                                $todosProfessores = array_merge($todosProfessores, $professoresDaEscola);
                            }
                            $data['professores_ids'] = array_unique($todosProfessores);

                            $editService = new \App\Services\Atividade\AtividadeEditService(
                                app(\App\Services\GoogleMainService::class)
                            );

                            $todasAsPartes = $record->todasAsPartes();

                            $totalSucessos = 0;
                            $totalFalhas = 0;
                            $novasEscolasAdicionadas = 0;

                            if (!empty($data['novas_escolas'])) {
                                foreach ($todasAsPartes as $parte) {
                                    $resultadoNovas = $editService->adicionarNovasEscolas($parte, $data['novas_escolas']);
                                    $totalSucessos += $resultadoNovas['sucessos'];
                                    $totalFalhas += $resultadoNovas['falhas'];
                                    $novasEscolasAdicionadas += $resultadoNovas['sucessos'];
                                }
                            }

                            foreach ($todasAsPartes as $parte) {
                                $resultados = $editService->atualizarAtividade($parte, $data);
                                $totalSucessos += $resultados['sucessos'];
                                $totalFalhas += $resultados['falhas'];
                            }

                            $record->refresh();
                            $record->load(['escolas', 'professores']);

                            $mensagemSucesso = "Atividade atualizada em {$totalSucessos} escola(s) e {$todasAsPartes->count()} parte(s)";
                            if ($novasEscolasAdicionadas > 0) {
                                $mensagemSucesso .= ". {$novasEscolasAdicionadas} nova(s) escola(s) adicionada(s)";
                            }

                            if ($totalFalhas > 0) {
                                Notification::make()
                                    ->title('Atualização Concluída com Erros')
                                    ->body("Sucessos: {$totalSucessos}, Falhas: {$totalFalhas}")
                                    ->warning()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Sucesso!')
                                    ->body($mensagemSucesso)
                                    ->success()
                                    ->send();
                            }

                            redirect()->to(request()->header('Referer'));
                        } catch (\Exception $e) {

                            Notification::make()
                                ->title('Erro ao Atualizar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([

                Tables\Actions\DeleteBulkAction::make()
                    ->label('Excluir selecionados')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-trash'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAtividades::route('/'),
        ];
    }
}
