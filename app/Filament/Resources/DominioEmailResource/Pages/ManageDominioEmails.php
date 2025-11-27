<?php

namespace App\Filament\Resources\DominioEmailResource\Pages;

use App\Filament\Resources\DominioEmailResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDominioEmails extends ManageRecords
{
    protected static string $resource = DominioEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
