<?php

namespace App\Exceptions;

use Exception;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

class AcessoNegadoLogin extends Exception
{
    /**
     * Render the exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @return RedirectResponse
     */
    public function render($request): RedirectResponse
    {
        Filament::auth()->logout();

        session()->invalidate();
        session()->regenerateToken();
        cache()->flush();

        \Filament\Notifications\Notification::make()
            ->title('Acesso Negado')
            ->body($this->getMessage())
            ->danger()
            ->send();

        return redirect()->route('filament.admin.auth.login')
            ->with('clear_browser', true);
    }
}