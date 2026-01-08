<?php

namespace App\Filament\Resources\EscolaResource\Pages;

use App\Filament\Resources\EscolaResource;
use App\Models\Escola;
use App\Services\Escola\EscolaService;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageEscolas extends ManageRecords
{
    protected static string $resource = EscolaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->using(function (array $data) {
                    return app(EscolaService::class)->create($data);
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function handleRecordUpdate(Escola $record, array $data): Escola
    {
        return app(EscolaService::class)->update($record, $data);
    }

    protected function handleRecordDeletion(Escola $record): void
    {
        app(EscolaService::class)->delete($record);
    }
}
