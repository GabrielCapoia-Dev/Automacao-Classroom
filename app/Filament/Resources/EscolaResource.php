<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EscolaResource\Pages;
use App\Models\Escola;
use App\Services\Escola\EscolaFilamentService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class EscolaResource extends Resource
{
    protected static ?string $model = Escola::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    public static ?string $modelLabel = 'Escola';
    protected static ?string $navigationGroup = "Rotinas";
    public static ?string $pluralModelLabel = 'Escolas';
    public static ?string $slug = 'escolas';

    public static function form(Form $form): Form
    {
        return app(EscolaFilamentService::class)->configurarFormulario($form);
    }

    public static function table(Table $table): Table
    {
        return app(EscolaFilamentService::class)->configurarTabela($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageEscolas::route('/'),
        ];
    }
}
