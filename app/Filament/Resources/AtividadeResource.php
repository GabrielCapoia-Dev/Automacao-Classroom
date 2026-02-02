<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AtividadeResource\Pages;
use App\Filament\Resources\AtividadeResource\RelationManagers;
use App\Models\Atividade;
use App\Models\GoogleAccount;
use App\Models\Professor;
use App\Models\Serie;
use Filament\Forms;
use App\Services\GoogleDriveService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;

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
                Fieldset::make()
                    ->schema([
                        TextInput::make('titulo')
                            ->required()
                            ->live(),

                        Select::make('serie_id')
                            ->label('Série')
                            ->live()
                            ->required()
                            ->options(
                                Serie::fromMainAccount()->orderBy('nome')->pluck('nome', 'id')
                            ),

                        Grid::make(12)
                            ->schema([
                                Grid::make(6)
                                    ->columnSpan(6)
                                    ->schema([

                                        Textarea::make('descricao')
                                            ->rows(6)
                                            ->columnSpan(6),

                                        TextInput::make('url')
                                            ->url()
                                            ->columnSpan(4)
                                            ->required()
                                            ->live(),

                                        Actions::make([
                                            Action::make('carregarArquivos')
                                                ->label('Carregar')
                                                ->extraAttributes([
                                                    'style' => 'margin-top: 2rem;'
                                                ])
                                                ->icon('heroicon-o-arrow-down-tray')
                                                ->color('success')
                                                ->disabled(function () {
                                                    return !GoogleDriveService::temContaConectada();
                                                })
                                                ->action(function (callable $get, callable $set) {
                                                    $url = $get('url');

                                                    if (!$url) {
                                                        Notification::make()
                                                            ->title('Erro')
                                                            ->body('Por favor, insira uma URL válida do Google Drive')
                                                            ->danger()
                                                            ->send();
                                                        return;
                                                    }

                                                    try {
                                                        // Extrai o ID da pasta do Google Drive
                                                        preg_match('/folders\/([a-zA-Z0-9_-]+)/', $url, $matches);

                                                        if (empty($matches[1])) {
                                                            throw new \Exception('URL inválida do Google Drive. Use uma URL de pasta compartilhada.');
                                                        }

                                                        $folderId = $matches[1];

                                                        $set('drive_folder_id', $folderId);
                                                        $set('drive_folder_url', $url);

                                                        // Cria o serviço e busca os arquivos
                                                        $driveService = new GoogleDriveService();
                                                        $arquivos = $driveService->listarArquivos($folderId);

                                                        $set('arquivos_drive', $arquivos);

                                                        if (!$driveService->validarFolderId($folderId)) {
                                                            throw new \Exception('Pasta não encontrada ou sem permissão de acesso.');
                                                        }

                                                        // Busca os arquivos
                                                        $arquivos = $driveService->listarArquivos($folderId);

                                                        if (empty($arquivos)) {
                                                            Notification::make()
                                                                ->title('Pasta vazia')
                                                                ->body('A pasta do Google Drive não contém arquivos.')
                                                                ->warning()
                                                                ->send();

                                                            $set('arquivos_drive', []);
                                                            return;
                                                        }

                                                        $set('arquivos_drive', $arquivos);

                                                        Notification::make()
                                                            ->title('Sucesso!')
                                                            ->body(count($arquivos) . ' arquivo(s) carregado(s)')
                                                            ->success()
                                                            ->send();
                                                    } catch (\Exception $e) {
                                                        Notification::make()
                                                            ->title('Erro ao carregar arquivos')
                                                            ->body($e->getMessage())
                                                            ->danger()
                                                            ->send();
                                                    }
                                                })
                                        ])
                                            ->columnSpan(2),
                                    ]),
                                Grid::make(6)
                                    ->columnSpan(6)
                                    ->schema([
                                        CheckboxList::make('escolas')
                                            ->label('Escolas')
                                            ->columnSpan(6)
                                            ->reactive()
                                            ->helperText(fn(callable $get) => $get('serie_id') ? null : 'Selecione uma Série')
                                            ->options(function (callable $get) {
                                                $serieId = $get('serie_id');

                                                if (! $serieId) {
                                                    return [];
                                                }

                                                $escolas = \App\Models\Escola::whereHas('turmas', function ($q) use ($serieId) {
                                                    $q->where('serie_id', $serieId)
                                                        ->whereHas('professores'); // 🔴 filtro chave
                                                })
                                                    ->orderBy('nome')
                                                    ->pluck('nome', 'id')
                                                    ->toArray();

                                                return empty($escolas)
                                                    ? []
                                                    : ['all' => 'Todas'] + $escolas;
                                            })
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                if (! $state || ! in_array('all', $state, true)) {
                                                    return;
                                                }

                                                $serieId = $get('serie_id');

                                                if (! $serieId) {
                                                    return;
                                                }

                                                $ids = \App\Models\Escola::whereHas('turmas', function ($q) use ($serieId) {
                                                    $q->where('serie_id', $serieId)
                                                        ->whereHas('professores'); // 🔴 mesmo critério
                                                })
                                                    ->pluck('id')
                                                    ->toArray();

                                                $set('escolas', $ids);
                                            })
                                            ->extraFieldWrapperAttributes([
                                                'style' => '
                                                    min-height: 280px;
                                                    max-height: 280px;
                                                    overflow-y: auto;
                                                    display: block;
                                                    padding: 10px;
                                                ',
                                            ])
                                    ])
                            ]),
                    ]),

                Fieldset::make('Arquivos do Drive')
                    ->schema([
                        ViewField::make('arquivos_drive')
                            ->view('filament.forms.components.drive-files')
                            ->columnSpanFull()
                    ])
                    ->hidden(fn(callable $get) => empty($get('arquivos_drive'))),

                // Botão de Enviar
                Fieldset::make('Envio')
                    ->schema([
                        Actions::make([
                            Action::make('enviarAtividade')
                                ->label('Enviar Atividade para o Classroom')
                                ->icon('heroicon-o-paper-airplane')
                                ->color('primary')
                                ->size('lg')
                                ->requiresConfirmation()
                                ->modalHeading('Confirmar Envio')
                                ->modalDescription(function (callable $get) {
                                    $arquivos = $get('arquivos_drive') ?? [];
                                    $escolas = array_filter($get('escolas') ?? [], fn($id) => $id !== 'all');
                                    $totalArquivos = count($arquivos);
                                    $totalEscolas = count($escolas);
                                    $partes = (int) ceil($totalArquivos / 5);

                                    return "Você está prestes a enviar esta atividade para {$totalEscolas} escola(s). " .
                                        "Serão criadas {$partes} parte(s) com total de " . ($partes * $totalEscolas) . " envios. " .
                                        "Este processo pode levar alguns minutos.";
                                })
                                ->modalSubmitActionLabel('Sim, enviar!')
                                ->disabled(function (callable $get) {
                                    $titulo = $get('titulo');
                                    $serie = $get('serie_id');
                                    $escolas = array_filter($get('escolas') ?? [], fn($id) => $id !== 'all');
                                    $arquivos = $get('arquivos_drive') ?? [];

                                    return empty($titulo) || empty($serie) || empty($escolas) || empty($arquivos);
                                })
                                ->action(function (callable $get, callable $set) {
                                    try {
                                        $envioService = new \App\Services\Atividade\AtividadeEnvioService(
                                            app(\App\Services\GoogleMainService::class)
                                        );

                                        $resultados = $envioService->enviarAtividade(
                                            $get(),
                                            function ($progresso) {
                                                Log::info('Progresso', $progresso);
                                            }
                                        );

                                        if ($resultados['cancelado']) {
                                            Notification::make()
                                                ->title('Envio Cancelado')
                                                ->warning()
                                                ->send();
                                            return;
                                        }

                                        $mensagem = "Envio concluído! " .
                                            "Sucessos: {$resultados['sucessos']}, " .
                                            "Falhas: {$resultados['falhas']}";

                                        if ($resultados['falhas'] > 0) {
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

                                        // Limpa os campos do formulário
                                        $set('titulo', '');
                                        $set('descricao', '');
                                        $set('url', '');
                                        $set('arquivos_drive', []);
                                        $set('escolas', []);
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
                        $arquivos = $get('arquivos_drive') ?? [];
                        $escolas = array_filter($get('escolas') ?? [], fn($id) => $id !== 'all');
                        return empty($arquivos) || empty($escolas);
                    }),
            ]);
    }

    private static function buscarArquivosDrive($folderId): array
    {
        $driveService = new GoogleDriveService();
        return $driveService->listarArquivos($folderId);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->apenasAtividadesPrincipais()) // ✅ Filtra apenas parte 1
            ->columns([
                Tables\Columns\TextColumn::make('titulo_original')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->default(fn($record) => $record->titulo), // Fallback para atividades antigas

                Tables\Columns\TextColumn::make('total_partes')
                    ->label('Partes')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn($state) => $state > 1 ? "{$state} partes" : '1 parte')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('serie.nome')
                    ->label('Série')
                    ->sortable()
                    ->badge()
                    ->color('info')
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
                        // ✅ Preenche os professores agrupados por escola
                        $formData = [
                            'titulo' => $record->titulo_original ?? $record->titulo,
                            'descricao' => $record->descricao,
                            'editar_todas_escolas_existentes' => true,
                            'escolas_existentes_selecionadas' => [],
                            'novas_escolas' => [],
                        ];

                        // ✅ Para cada escola, preenche seus professores
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
                                        // ✅ Auto-seleciona professores das novas escolas
                                        $novasEscolas = $state ?? [];
                                        $serieId = $record->serie_id;

                                        foreach ($novasEscolas as $escolaId) {
                                            // Busca professores da escola
                                            $professoresDaEscola = Professor::where('escola_id', $escolaId)
                                                ->whereHas('turmas', function ($q) use ($serieId) {
                                                    $q->where('serie_id', $serieId);
                                                })
                                                ->whereNotNull('classroom_user_id')
                                                ->pluck('id')
                                                ->toArray();

                                            // Auto-seleciona todos os professores da escola
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

                                // ✅ Cria um Fieldset para cada escola
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

                                    $fieldsets[] = Forms\Components\Fieldset::make("escola_{$escola->id}")
                                        ->label($escola->nome . $badge)
                                        ->schema([
                                            Forms\Components\CheckboxList::make("professores_escola_{$escola->id}")
                                                ->label('')
                                                ->options($professoresDaEscola->pluck('nome', 'id'))
                                                ->columns(2)
                                                ->columnSpanFull()
                                                ->live()
                                        ])
                                        ->columnSpanFull();
                                }

                                // ✅ Campo oculto que coleta todos os professores selecionados
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
                            // ✅ VALIDAÇÃO: Verifica se cada escola tem pelo menos 1 professor
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

                            // ✅ Coleta todos os professores selecionados
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

                            // Adiciona novas escolas
                            if (!empty($data['novas_escolas'])) {
                                foreach ($todasAsPartes as $parte) {
                                    $resultadoNovas = $editService->adicionarNovasEscolas($parte, $data['novas_escolas']);
                                    $totalSucessos += $resultadoNovas['sucessos'];
                                    $totalFalhas += $resultadoNovas['falhas'];
                                    $novasEscolasAdicionadas += $resultadoNovas['sucessos'];
                                }
                            }

                            // Atualiza escolas existentes
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
                            Log::error("Erro na action de edição", [
                                'erro' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);

                            Notification::make()
                                ->title('Erro ao Atualizar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAtividades::route('/'),
        ];
    }
}
