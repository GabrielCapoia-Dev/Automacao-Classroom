<?php

namespace App\Filament\Resources\TurmaResource\Pages;

use App\Filament\Resources\TurmaResource;
use App\Services\Escola\EscolaSyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageTurmas extends ManageRecords
{
    protected static string $resource = TurmaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importar')
                ->label('Importar')
                ->action(function () {
                    app(EscolaSyncService::class)->syncEscolasComTurmas();

                    Notification::make()
                        ->title('Sincronização concluída')
                        ->success()
                        ->send();
                }),
        ];
    }
}
