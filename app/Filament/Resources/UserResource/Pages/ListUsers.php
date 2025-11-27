<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\IgnoredUser;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function mount(): void
    {
        parent::mount();

        $admin = Auth::user();

        $pendingUsers = static::getResource()::getModel()::where('email_approved', false)->pluck('id');

        foreach ($pendingUsers as $userId) {
            IgnoredUser::firstOrCreate([
                'admin_id' => $admin->id,
                'user_id'  => $userId,
            ]);
        }

        $this->dispatch('refresh-navigation');
    }

    protected function getTableQuery(): Builder
    {
        $query = static::getResource()::getEloquentQuery();

        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if ($user && (!$user->hasRole('Admin')) && !empty($user->id_escola)) {
            $query->where($query->getModel()->getTable() . '.id_escola', $user->id_escola);
        }

        return $query;
    }
}
