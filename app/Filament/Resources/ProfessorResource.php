<?php

namespace App\Filament\Resources;

use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use App\Filament\Resources\ProfessorResource\Pages;
use App\Filament\Resources\ProfessorResource\RelationManagers;
use App\Models\Professor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;


class ProfessorResource extends Resource
{
    protected static ?string $model = Professor::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    public static ?string $modelLabel = 'Professor';
    protected static ?string $navigationGroup = "Rotinas";
    public static ?string $pluralModelLabel = 'Professores';
    public static ?string $slug = 'professores';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('google_account_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('classroom_user_id')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        $series = \App\Models\Serie::query()
            ->whereHas('turmas.professores')
            ->orderBy('nome')
            ->get();

        $dynamicColumns = [];

        foreach ($series as $serie) {

            $alias = 'serie_' . $serie->id;

            $dynamicColumns[] =
                Tables\Columns\IconColumn::make($alias)
                ->label($serie->nome)
                ->boolean()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true)
                ->alignCenter();
        }

        return $table
            ->description(function ($livewire) {

                $escolas = \App\Models\Escola::count();
                $totalProfessores = Professor::count();

                // quantidade após filtros/pesquisa
                $listagemAtual = $livewire->getFilteredTableQuery()->count();

                return "Escolas: {$escolas} | Professores: {$totalProfessores} | Listagem Atual: {$listagemAtual}";
            })
            ->modifyQueryUsing(function (Builder $query, $livewire) use ($series) {

                foreach ($series as $serie) {

                    $alias = 'serie_' . $serie->id;

                    // adiciona withExists para todas
                    $query->withExists([
                        "turmas as {$alias}" => function ($q) use ($serie) {
                            $q->where('serie_id', $serie->id);
                        }
                    ]);

                    // se a coluna estiver visível, filtra apenas TRUE
                    if (
                        isset($livewire->toggledTableColumns[$alias]) &&
                        $livewire->toggledTableColumns[$alias] === true
                    ) {
                        $query->whereHas('turmas', function ($q) use ($serie) {
                            $q->where('serie_id', $serie->id);
                        });
                    }
                }
            })
            ->columns(array_merge([
                Tables\Columns\TextColumn::make('escola.nome')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->alignCenter()
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Copiado!')
                    ->copyableState(fn($state) => $state)
                    ->searchable(),

                Tables\Columns\TextColumn::make('turmas.nome')
                    ->label('Turmas')
                    ->badge()
                    ->separator(',')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('classroom_user_id')
                    ->searchable()
                    ->alignCenter()
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Copiado!')
                    ->copyableState(fn($state) => $state)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ], $dynamicColumns))
            ->filters([
                Tables\Filters\SelectFilter::make('escola_id')
                    ->label('Escola')
                    ->relationship('escola', 'nome')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('serie_id')
                    ->label('Série')
                    ->options(function () {
                        return \App\Models\Serie::orderBy('nome')
                            ->pluck('nome', 'id')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data) {

                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->whereHas('turmas', function ($q) use ($data) {
                            $q->where('serie_id', $data['value']);
                        });
                    })
                    ->searchable()
                    ->preload(),
            ])

            ->actions([])
            ->headerActions([

                Tables\Actions\Action::make('removerTodosVinculos')
                    ->label('Remover Todos os Vínculos')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remover Todos os Vínculos')
                    ->modalDescription('⚠️ ATENÇÃO: Esta ação removerá TODOS os vínculos entre professores e turmas. Esta operação não pode ser desfeita.')
                    ->modalSubmitActionLabel('Sim, remover tudo')
                    ->action(function () {

                        $totalRemovidos = 0;

                        DB::transaction(function () use (&$totalRemovidos) {

                            $professores = Professor::withCount('turmas')->get();

                            foreach ($professores as $professor) {

                                if ($professor->turmas_count > 0) {
                                    $totalRemovidos += $professor->turmas_count;
                                    $professor->turmas()->detach();
                                }
                            }
                        });

                        \Filament\Notifications\Notification::make()
                            ->title('Vínculos Removidos')
                            ->body("{$totalRemovidos} vínculo(s) removido(s) com sucesso.")
                            ->success()
                            ->persistent()
                            ->send();
                    }),


                Tables\Actions\Action::make('vincularPlanilha')
                    ->label('Vincular por Planilha')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('success')
                    ->form([
                        Forms\Components\FileUpload::make('planilha')
                            ->label('Planilha de Professores')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel'
                            ])
                            ->required()
                            ->helperText('Upload da planilha XLSX com: Coluna A (Escola), Coluna B (Série), Coluna E (Componente), Coluna H (Email)')
                    ])
                    ->action(function (array $data) {

                        try {

                            $arquivo = storage_path('app/public/' . $data['planilha']);

                            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($arquivo);
                            $sheet = $spreadsheet->getActiveSheet();
                            $rows = $sheet->toArray();

                            $series = \App\Models\Serie::all();

                            $seriesMap = $series->mapWithKeys(function ($serie) {
                                return [
                                    mb_strtolower(trim($serie->nome)) => $serie->id
                                ];
                            });

                            $totalVinculos = 0;
                            $erros = [];
                            $emailsProcessados = [];

                            $linhasNaoEncontradas = [];
                            $cabecalho = $rows[0] ?? [];

                            foreach ($rows as $index => $row) {

                                if ($index === 0) {
                                    continue;
                                }

                                $linha = $index + 1;

                                $escolaNome = trim($row[0] ?? '');
                                $serieColunaB = trim($row[1] ?? '');
                                $componente = trim($row[4] ?? '');
                                $email = mb_strtolower(trim($row[7] ?? ''));

                                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    continue;
                                }

                                $chave = $email . '|' . $escolaNome . '|' . $serieColunaB . '|' . $componente;
                                if (isset($emailsProcessados[$chave])) {
                                    continue;
                                }
                                $emailsProcessados[$chave] = true;

                                $professor = Professor::whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

                                if (!$professor) {
                                    $linhasNaoEncontradas[] = $row; // guarda linha completa
                                    continue;
                                }

                                if (!$professor->escola_id) {
                                    $erros[] = "Linha {$linha}: Professor sem escola vinculada";
                                    continue;
                                }

                                $componenteKey = mb_strtolower($componente);
                                $serieKeyB = mb_strtolower($serieColunaB);

                                $serieId = null;

                                if (isset($seriesMap[$componenteKey])) {
                                    $serieId = $seriesMap[$componenteKey];
                                } elseif (isset($seriesMap[$serieKeyB])) {
                                    $serieId = $seriesMap[$serieKeyB];
                                }

                                if (!$serieId) {
                                    continue;
                                }

                                $turmas = \App\Models\Turma::query()
                                    ->where('escola_id', $professor->escola_id)
                                    ->where('serie_id', $serieId)
                                    ->get();

                                if ($turmas->isEmpty()) {
                                    $erros[] = "Linha {$linha}: Nenhuma turma encontrada para série";
                                    continue;
                                }

                                $ids = $turmas->pluck('id')->toArray();

                                $antes = $professor->turmas()->count();

                                $professor->turmas()->syncWithoutDetaching($ids);

                                $depois = $professor->turmas()->count();

                                $totalVinculos += max(0, $depois - $antes);
                            }

                            @unlink($arquivo);

                            // 🔥 Se houver professores não encontrados, gera planilha
                            $downloadUrl = null;

                            if (!empty($linhasNaoEncontradas)) {

                                $novoSpreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                                $novaSheet = $novoSpreadsheet->getActiveSheet();

                                // Cabeçalho
                                $novaSheet->fromArray($cabecalho, null, 'A1');

                                // Linhas filtradas
                                $novaSheet->fromArray($linhasNaoEncontradas, null, 'A2');

                                foreach (range('A', $novaSheet->getHighestColumn()) as $col) {
                                    $novaSheet->getColumnDimension($col)->setAutoSize(true);
                                }

                                $fileName = 'professores_nao_encontrados_' . date('Y-m-d_His') . '.xlsx';
                                $dir = storage_path('app/public/exports');

                                if (!file_exists($dir)) {
                                    mkdir($dir, 0755, true);
                                }

                                $filePath = $dir . '/' . $fileName;

                                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($novoSpreadsheet);
                                $writer->save($filePath);

                                $downloadUrl = asset('storage/exports/' . $fileName);
                            }

                            $notification = \Filament\Notifications\Notification::make()
                                ->title('Processamento concluído')
                                ->body("Vínculos realizados: {$totalVinculos}")
                                ->status($totalVinculos > 0 ? 'success' : 'warning')
                                ->persistent();

                            if ($downloadUrl) {
                                $notification->actions([
                                    \Filament\Notifications\Actions\Action::make('download')
                                        ->label('Baixar não encontrados')
                                        ->url($downloadUrl)
                                        ->openUrlInNewTab()
                                ]);
                            }

                            $notification->send();
                        } catch (\Exception $e) {

                            \Filament\Notifications\Notification::make()
                                ->title('Erro')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('vincularTodos')
                    ->label('Vincular Todos')
                    ->icon('heroicon-o-link')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Vincular Todos os Professores')
                    ->modalDescription('Vincular todos os professores às turmas de suas respectivas escolas.')
                    ->action(function () {

                        $escolas = \App\Models\Escola::with(['professores', 'turmas'])->get();

                        $total = 0;

                        foreach ($escolas as $escola) {

                            if ($escola->turmas->isEmpty() || $escola->professores->isEmpty()) {
                                continue;
                            }

                            $turmaIds = $escola->turmas->pluck('id');

                            foreach ($escola->professores as $professor) {
                                $professor->turmas()->syncWithoutDetaching($turmaIds);
                                $total++;
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Vinculação concluída')
                            ->body("{$total} professor(es) processado(s)")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('exportarTodos')
                    ->label('Exportar Todos')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function () {
                        $professores = Professor::with('escola')->orderBy('escola_id')->orderBy('nome')->get();

                        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                        $sheet = $spreadsheet->getActiveSheet();

                        // Cabeçalhos
                        $sheet->setCellValue('A1', 'Escola');
                        $sheet->setCellValue('B1', 'Nome');
                        $sheet->setCellValue('C1', 'Email');
                        $sheet->setCellValue('D1', 'Classroom User ID');

                        // Estilizar cabeçalho
                        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
                        $sheet->getStyle('A1:D1')->getFill()
                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFE0E0E0');

                        // Dados
                        $linha = 2;
                        foreach ($professores as $professor) {
                            $sheet->setCellValue('A' . $linha, $professor->escola->nome ?? 'Sem escola');
                            $sheet->setCellValue('B' . $linha, $professor->nome);
                            $sheet->setCellValue('C' . $linha, $professor->email);
                            $sheet->setCellValue('D' . $linha, $professor->classroom_user_id ?? '');
                            $linha++;
                        }

                        // Ajustar largura das colunas
                        foreach (range('A', 'D') as $col) {
                            $sheet->getColumnDimension($col)->setAutoSize(true);
                        }

                        // Salvar em diretório público
                        $fileName = 'professores_' . date('Y-m-d_His') . '.xlsx';
                        $filePath = storage_path('app/public/exports/' . $fileName);

                        // Criar diretório se não existir
                        if (!file_exists(storage_path('app/public/exports'))) {
                            mkdir(storage_path('app/public/exports'), 0755, true);
                        }

                        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                        $writer->save($filePath);

                        \Filament\Notifications\Notification::make()
                            ->title('Exportação Concluída')
                            ->body("{$professores->count()} professores exportados")
                            ->success()
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('download')
                                    ->label('Baixar Arquivo')
                                    ->url(asset('storage/exports/' . $fileName))
                                    ->openUrlInNewTab()
                            ])
                            ->persistent()
                            ->send();
                    }),
            ])
            ->bulkActions([

                Tables\Actions\DeleteBulkAction::make()
                    ->label('Excluir selecionados')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-trash'),

                Tables\Actions\BulkAction::make('vincularTurmas')
                    ->label('Vincular')
                    ->icon('heroicon-o-link')
                    ->color('success')
                    ->form(function ($livewire) {

                        $records = $livewire->getSelectedTableRecords();

                        $escolas = $records
                            ->map(fn($prof) => $prof->escola_id)
                            ->filter()
                            ->unique();

                        $escolaId = $escolas->count() === 1
                            ? $escolas->first()
                            : null;

                        return [
                            Forms\Components\Select::make('turmas')
                                ->label('Turmas')
                                ->multiple()
                                ->required()
                                ->options(function () use ($escolaId) {

                                    if (! $escolaId) {
                                        return [];
                                    }

                                    return \App\Models\Turma::query()
                                        ->where('escola_id', $escolaId)
                                        ->orderBy('nome')
                                        ->pluck('nome', 'id')
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->helperText(
                                    $escolaId
                                        ? 'Serão exibidas apenas turmas da escola comum dos professores selecionados.'
                                        : 'Selecione apenas professores da mesma escola.'
                                ),
                        ];
                    })
                    ->action(function (array $data, \Illuminate\Support\Collection $records) {

                        if ($records->isEmpty()) {
                            return;
                        }

                        $escolas = $records
                            ->map(fn($prof) => $prof->escola_id)
                            ->filter()
                            ->unique();

                        if ($escolas->count() !== 1) {
                            \Filament\Notifications\Notification::make()
                                ->title('Seleção inválida')
                                ->body('Todos os professores devem ser da mesma escola.')
                                ->danger()
                                ->send();
                            return;
                        }

                        foreach ($records as $professor) {
                            $professor
                                ->turmas()
                                ->syncWithoutDetaching($data['turmas']);
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Vínculo realizado')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                FilamentExportBulkAction::make('exportar')
                    ->label('Exportar Selecionados')
                    ->fileName('professores_selecionados_' . date('Y-m-d_His'))
                    ->defaultFormat('xlsx')
                    ->directDownload()
                    ->defaultPageOrientation('landscape'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageProfessors::route('/'),
        ];
    }
}
