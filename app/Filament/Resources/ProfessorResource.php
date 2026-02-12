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
        return $table
            ->description(function () {
                $escolas = \App\Models\Escola::count();
                $professores = Professor::count();

                return "Escolas: {$escolas} | Professores: {$professores}";
            })
            ->columns([
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
            ])
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

                Tables\Actions\Action::make('reprocessarVinculos')
                    ->label('Reprocessar Vínculos')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reprocessar Vínculos por Planilha')
                    ->modalDescription('⚠️ ATENÇÃO: Esta ação vai REMOVER todos os vínculos existentes e criar apenas os vínculos da planilha. Esta operação não pode ser desfeita.')
                    ->modalSubmitActionLabel('Sim, Reprocessar')
                    ->form([
                        Forms\Components\FileUpload::make('planilha')
                            ->label('Planilha de Vínculos')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel'
                            ])
                            ->required()
                            ->helperText('Estrutura: Coluna A (Escola), Coluna B (Nome Professor), Coluna C (Email), Coluna D (Turma)')
                    ])
                    ->action(function (array $data) {
                        try {
                            $arquivo = storage_path('app/public/' . $data['planilha']);
                            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($arquivo);
                            $sheet = $spreadsheet->getActiveSheet();
                            $rows = $sheet->toArray();

                            $vinculosNovos = [];
                            $erros = [];
                            $professoresProcessados = [];

                            // Processar planilha (pular header)
                            foreach (array_slice($rows, 1) as $index => $row) {
                                $linha = $index + 2;

                                $escolaNome = isset($row[0]) ? trim($row[0]) : '';
                                $professorNome = isset($row[1]) ? trim($row[1]) : '';
                                $email = isset($row[2]) ? strtolower(trim($row[2])) : '';
                                $turmaNome = isset($row[3]) ? trim($row[3]) : '';

                                // Validar dados
                                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    continue;
                                }

                                if (empty($escolaNome) || empty($turmaNome)) {
                                    $erros[] = "Linha {$linha}: Dados incompletos";
                                    continue;
                                }

                                // Buscar professor
                                $professor = Professor::whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

                                if (!$professor) {
                                    $erros[] = "Linha {$linha}: Professor '{$email}' não encontrado";
                                    continue;
                                }

                                // Validar escola do professor
                                if (!$professor->escola) {
                                    $erros[] = "Linha {$linha}: Professor '{$email}' sem escola vinculada";
                                    continue;
                                }

                                // Verificar se escola corresponde (com busca flexível)
                                $escolaMatch = false;
                                if (
                                    stripos($professor->escola->nome, $escolaNome) !== false ||
                                    stripos($escolaNome, $professor->escola->nome) !== false
                                ) {
                                    $escolaMatch = true;
                                }

                                if (!$escolaMatch) {
                                    $erros[] = "Linha {$linha}: Escola não corresponde - Sistema: '{$professor->escola->nome}', Planilha: '{$escolaNome}'";
                                    continue;
                                }

                                // Buscar turma na escola do professor
                                $turmas = \App\Models\Turma::query()
                                    ->where('escola_id', $professor->escola_id)
                                    ->where(function ($q) use ($turmaNome) {
                                        $q->whereRaw('LOWER(nome) LIKE ?', ['%' . strtolower($turmaNome) . '%'])
                                            ->orWhereHas('serie', function ($sq) use ($turmaNome) {
                                                $sq->whereRaw('LOWER(nome) LIKE ?', ['%' . strtolower($turmaNome) . '%']);
                                            });
                                    })
                                    ->get();

                                if ($turmas->isEmpty()) {
                                    $erros[] = "Linha {$linha}: Turma '{$turmaNome}' não encontrada na escola '{$professor->escola->nome}'";
                                    continue;
                                }

                                // Guardar vínculos para este professor
                                if (!isset($vinculosNovos[$professor->id])) {
                                    $vinculosNovos[$professor->id] = [
                                        'professor' => $professor,
                                        'turmas' => []
                                    ];
                                }

                                foreach ($turmas as $turma) {
                                    if (!in_array($turma->id, $vinculosNovos[$professor->id]['turmas'])) {
                                        $vinculosNovos[$professor->id]['turmas'][] = $turma->id;
                                    }
                                }

                                $professoresProcessados[$email] = true;
                            }

                            // REMOVER TODOS OS VÍNCULOS EXISTENTES E CRIAR OS NOVOS
                            $totalVinculosRemovidos = 0;
                            $totalVinculosAdicionados = 0;

                            DB::transaction(function () use ($vinculosNovos, &$totalVinculosRemovidos, &$totalVinculosAdicionados) {
                                // 1. Remover TODOS os vínculos de professores que estão na planilha
                                foreach ($vinculosNovos as $professorId => $dados) {
                                    $professor = $dados['professor'];
                                    $vinculosAntigos = $professor->turmas()->count();
                                    $professor->turmas()->detach();
                                    $totalVinculosRemovidos += $vinculosAntigos;
                                }

                                // 2. Criar novos vínculos
                                foreach ($vinculosNovos as $professorId => $dados) {
                                    $professor = $dados['professor'];
                                    $turmasIds = $dados['turmas'];

                                    if (!empty($turmasIds)) {
                                        $professor->turmas()->attach($turmasIds);
                                        $totalVinculosAdicionados += count($turmasIds);
                                    }
                                }
                            });

                            @unlink($arquivo);

                            $mensagem = "🔄 REPROCESSAMENTO CONCLUÍDO\n\n";
                            $mensagem .= "Professores processados: " . count($vinculosNovos) . "\n";
                            $mensagem .= "❌ Vínculos removidos: {$totalVinculosRemovidos}\n";
                            $mensagem .= "✅ Vínculos criados: {$totalVinculosAdicionados}\n";

                            if (!empty($erros)) {
                                $mensagem .= "\n⚠️ ERROS/AVISOS (" . count($erros) . "):\n";
                                $mensagem .= implode("\n", array_slice($erros, 0, 15));
                                if (count($erros) > 15) {
                                    $mensagem .= "\n... +" . (count($erros) - 15) . " mais";
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Vínculos Reprocessados')
                                ->body($mensagem)
                                ->success()
                                ->persistent()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Erro ao Reprocessar')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
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

                            // Componentes especiais que devem ser tratados pela série
                            $componentesEspeciais = [
                                'tracos sons cores e formas',
                                'tracossonscoreseformas',
                                'corpo gesto e movimentos',
                                'corpo gestos e movimentos',
                                'corpogestosemovimentos',
                                'educacao fisica',
                                'educacaofisica',
                                'historia',
                                'arte e ensino religioso',
                                'arteeensinoreligioso'
                            ];

                            $totalVinculos = 0;
                            $erros = [];
                            $logs = [];
                            $professoresNaoEncontrados = [];
                            $emailsProcessados = [];

                            foreach ($rows as $index => $row) {
                                $linha = $index + 1;

                                // Extrair dados
                                $escolaNome = isset($row[0]) ? trim($row[0]) : '';
                                $serieNome = isset($row[1]) ? trim($row[1]) : '';
                                $componente = isset($row[4]) ? trim($row[4]) : '';
                                $emailBruto = isset($row[7]) ? trim($row[7]) : '';

                                // Normalizar email
                                $email = strtolower($emailBruto);

                                // Validar email
                                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    continue;
                                }

                                // Evitar processar mesmo email múltiplas vezes na mesma execução
                                $chaveProcessamento = $email . '|' . $escolaNome . '|' . $serieNome . '|' . $componente;
                                if (isset($emailsProcessados[$chaveProcessamento])) {
                                    continue;
                                }
                                $emailsProcessados[$chaveProcessamento] = true;

                                // Buscar professor (com normalização)
                                $professor = Professor::whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

                                if (!$professor) {
                                    if (!in_array($email, $professoresNaoEncontrados)) {
                                        $professoresNaoEncontrados[] = $email;
                                    }
                                    continue;
                                }

                                // Verificar escola do professor
                                if (!$professor->escola) {
                                    $erros[] = "Linha {$linha}: Professor '{$email}' sem escola vinculada";
                                    continue;
                                }

                                // Verificar se escola da planilha corresponde
                                if (!empty($escolaNome) && stripos($professor->escola->nome, $escolaNome) === false) {
                                    $erros[] = "Linha {$linha}: Escola não corresponde - Professor em '{$professor->escola->nome}', planilha '{$escolaNome}'";
                                    continue;
                                }

                                // Normalizar componente
                                $componenteNormalizado = strtolower(preg_replace('/[^a-z0-9\s]/ui', '', $componente));

                                // Verificar se é componente especial
                                $isComponenteEspecial = false;
                                foreach ($componentesEspeciais as $especial) {
                                    $especial = str_replace(' ', '', $especial);
                                    $componenteTest = str_replace(' ', '', $componenteNormalizado);
                                    if (str_contains($componenteTest, $especial) || str_contains($especial, $componenteTest)) {
                                        $isComponenteEspecial = true;
                                        break;
                                    }
                                }

                                // Buscar turma
                                if ($isComponenteEspecial) {
                                    // Para componentes especiais: buscar pela série (relacionamento)
                                    $turmas = \App\Models\Turma::query()
                                        ->where('escola_id', $professor->escola_id)
                                        ->whereHas('serie', function ($q) use ($serieNome) {
                                            $q->whereRaw('LOWER(nome) LIKE ?', ['%' . strtolower($serieNome) . '%']);
                                        })
                                        ->get();
                                } else {
                                    // Para outros: buscar pelo nome da turma que contenha a série
                                    $turmas = \App\Models\Turma::query()
                                        ->where('escola_id', $professor->escola_id)
                                        ->whereRaw('LOWER(nome) LIKE ?', ['%' . strtolower($serieNome) . '%'])
                                        ->get();
                                }

                                if ($turmas->isEmpty()) {
                                    $tipo = $isComponenteEspecial ? 'especial' : 'normal';
                                    $erros[] = "Linha {$linha}: Turma não encontrada [{$tipo}] (Escola: {$professor->escola->nome}, Série: {$serieNome})";
                                    continue;
                                }

                                // Vincular
                                $vinculosRealizados = 0;
                                foreach ($turmas as $turma) {
                                    if (!$professor->turmas->contains($turma->id)) {
                                        $professor->turmas()->attach($turma->id);
                                        $vinculosRealizados++;
                                    }
                                }

                                if ($vinculosRealizados > 0) {
                                    $totalVinculos += $vinculosRealizados;
                                    $logs[] = "✓ {$email} -> {$turmas->pluck('nome')->implode(', ')}";
                                }
                            }

                            @unlink($arquivo);

                            $mensagem = "🎯 {$totalVinculos} vínculo(s) realizado(s)";

                            if (!empty($professoresNaoEncontrados)) {
                                $mensagem .= "\n\n⚠️ PROFESSORES NÃO ENCONTRADOS NO SISTEMA ({count($professoresNaoEncontrados)}):\n";
                                $mensagem .= implode("\n", array_slice($professoresNaoEncontrados, 0, 15));
                                if (count($professoresNaoEncontrados) > 15) {
                                    $mensagem .= "\n... +" . (count($professoresNaoEncontrados) - 15) . " mais";
                                }
                            }

                            if (!empty($logs)) {
                                $mensagem .= "\n\n✅ VÍNCULOS REALIZADOS:\n" . implode("\n", array_slice($logs, 0, 10));
                                if (count($logs) > 10) $mensagem .= "\n... +" . (count($logs) - 10) . " mais";
                            }

                            if (!empty($erros)) {
                                $mensagem .= "\n\n❌ OUTROS ERROS:\n" . implode("\n", array_slice($erros, 0, 5));
                                if (count($erros) > 5) $mensagem .= "\n... +" . (count($erros) - 5) . " mais";
                            }

                            \Filament\Notifications\Notification::make()
                                ->title($totalVinculos > 0 ? 'Concluído com Sucesso' : 'Nenhum Vínculo Realizado')
                                ->body($mensagem)
                                ->status($totalVinculos > 0 ? 'success' : 'warning')
                                ->persistent()
                                ->send();
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
