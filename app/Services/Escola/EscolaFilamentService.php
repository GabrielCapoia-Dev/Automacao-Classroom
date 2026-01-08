<?php

namespace App\Services\Escola;

use App\Services\UserService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class EscolaFilamentService
{
    public function configurarFormulario(Form $form): Form
    {
        return $form->schema($this->shemaFormulario());
    }

    private function shemaFormulario(): array
    {
        return [
            TextInput::make('nome')
                ->required()
                ->maxLength(255),
        ];
    }

    public function configurarTabela(Table $table): Table
    {
        return $table
            ->columns($this->colunasTabela())
            ->filters($this->filtrosTabela())
            ->actions($this->acoesTabela())
            ->bulkActions($this->acoesEmMassaTabela());
    }

    private function acoesEmMassaTabela(): array
    {
        return [
            DeleteBulkAction::make()
                ->visible(fn() => app(UserService::class)->ehAdmin(Auth::user()))
                ->requiresConfirmation()
                ->label('Excluir Selecionados'),
        ];
    }

    private function acoesTabela(): array
    {
        return [
            EditAction::make(),
        ];
    }
    private function filtrosTabela(): array
    {
        return [
            //
        ];
    }

    private function colunasTabela(): array
    {
        return [
            TextColumn::make('nome')
                ->searchable(),
            TextColumn::make('classroom_course_id')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }
}
