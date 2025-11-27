<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Filament\Resources\RoleResource\RelationManagers;
use App\Models\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use App\Services\RoleService;
use App\Services\UserService;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class RoleResource extends Resource
{
    public static function getNavigationBadge(): ?string
    {
        $value = (string) static::getModel()::count();

        if ($value > 0) {
            return $value;
        }
        return null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Quantidade de niveis de acesso cadastrados';
    }

    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    public static ?string $modelLabel = 'Nivel de acesso';

    public static ?string $pluralModelLabel = 'Niveis de acesso';

    public static ?string $slug = 'niveis-de-acesso';

    public static ?string $navigationGroup = 'Administrativo';

    public static ?int $navigationSort = 3;



    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nivel de acesso')
                    ->required()
                    ->disabled(fn($record, $context) => app(RoleService::class)->bloquearCampo($record, $context))
                    ->unique(ignoreRecord: true),
                Forms\Components\Select::make('permissions')
                    ->label('Permissão de execução')
                    ->multiple()
                    ->relationship('permissions', 'name')
                    ->preload()
                    ->required()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([10, 25, 50, 100])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nivel de acesso')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->disabled(fn($record) => app(RoleService::class)->adminRole($record)),

                Tables\Actions\DeleteAction::make()
                    ->disabled(fn($record) => app(RoleService::class)->bloquearExclusao($record)),
            ])
            ->bulkActions([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(function(){
                            $user = Auth::user();
                            return app(UserService::class)->ehAdmin($user);
                        })
            ])
            ->checkIfRecordIsSelectableUsing(fn($record) => app(RoleService::class)->bloquearSelecaoBulkActions($record));
        }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRoles::route('/'),
        ];
    }
}
