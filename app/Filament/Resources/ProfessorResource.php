<?php

namespace App\Filament\Resources;

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
                Tables\Columns\TextColumn::make('classroom_user_id')
                    ->searchable()
                    ->alignCenter()
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Copiado!')
                    ->copyableState(fn($state) => $state)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
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
            ])
            ->actions([])
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

            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageProfessors::route('/'),
        ];
    }
}
