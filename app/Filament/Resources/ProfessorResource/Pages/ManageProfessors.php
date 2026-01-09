<?php

namespace App\Filament\Resources\ProfessorResource\Pages;

use App\Filament\Resources\ProfessorResource;
use App\Services\Professor\ProfessorSyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageProfessors extends ManageRecords
{
    protected static string $resource = ProfessorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importar')
                ->label('Importar')
                ->action(function () {
                    app(ProfessorSyncService::class)->syncProfessores();

                    Notification::make()
                        ->title('Sincronização concluída')
                        ->success()
                        ->send();
                }),
        ];
    }
}
