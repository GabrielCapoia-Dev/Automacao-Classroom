<?php

namespace App\Filament\Pages;

use App\Models\GoogleAccount;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class Settings extends Page
{
    protected static ?string $navigationGroup = "Acesso";
    protected static ?string $slug = 'configuracoes';
    public static ?string $title = 'Configurações';
    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';
    protected static string $view = 'filament.pages.settings';
    protected static bool $isDiscovered = true;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        /** @var \App\Models\User $user */
        if ($user && $user->hasRole('Admin')) {
            return true;
        }

        return false;
    }

    public static function getMiddleware(): array
    {
        return [];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    protected function getViewData(): array
    {
        return [
            'googleAccount' => GoogleAccount::main(),
        ];
    }
}
