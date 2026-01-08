<?php

namespace App\Services\Classroom;

use App\Models\Escola;
use Filament\Forms\Form;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;
use Google\Service\Classroom\Course;

class EscolaService
{
    public function configurarFormulario(Form $form): Form
    {
        return $form->schema($this->shemaFormulario());
    }

    private function shemaFormulario(): array
    {
        return [
            //
        ];
    }

    public function configurarTabela(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
