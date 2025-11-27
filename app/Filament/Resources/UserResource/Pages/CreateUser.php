<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        /** @var \App\Models\User|null $auth */
        $auth = Auth::user();

        if ($auth && !$auth->hasRole('Admin') && !empty($auth->id_escola)) {
            $data['id_escola'] = $auth->id_escola;
        }


        if (!empty($data['email_approved']) && $data['email_approved']) {
            $data['email_verified_at'] = now();
        }

        return $data;
    }


    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
