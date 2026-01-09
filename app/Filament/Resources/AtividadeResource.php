<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AtividadeResource\Pages;
use App\Filament\Resources\AtividadeResource\RelationManagers;
use App\Models\Atividade;
use App\Models\GoogleAccount;
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

                                                        // Cria o serviço e busca os arquivos
                                                        $driveService = new GoogleDriveService();

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
            ->columns([
                Tables\Columns\TextColumn::make('titulo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('serie.nome')
                    ->label('Série')
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('escolas')
                    ->label('Escolas')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(
                        fn($record) =>
                        $record->escolas->pluck('nome')->join(', ')
                    )
                    ->color('success')
                    ->wrap(),

                Tables\Columns\TextColumn::make('professores')
                    ->label('Professores')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(
                        fn($record) =>
                        $record->professores->pluck('nome')->join(', ')
                    )
                    ->color('warning')
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('escolas_count')
                    ->label('Escolas')
                    ->counts('escolas')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('professores_count')
                    ->label('Professores')
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
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->actions([])
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
