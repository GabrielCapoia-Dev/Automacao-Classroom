<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['email_approved']) && $data['email_approved'] && empty($data['email_verified_at'])) {
            $data['email_verified_at'] = now();
        }
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(function ($record) {
                    if($record->hasRole('Admin')) {
                        return false;
                    }
                })
                ->before(function ($record, $action) {
                    if ($record->hasRole('Admin')) {
                        $action->halt();
                        $this->notify('danger', '❌ Não é permitido excluir o usuário Admin.');
                    }
                }),
        ];
    }


    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}