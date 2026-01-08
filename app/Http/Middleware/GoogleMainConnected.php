<?php

namespace App\Http\Middleware;

use App\Models\GoogleAccount;
use Filament\Facades\Filament;
use Closure;
use Illuminate\Http\Request;

class GoogleMainConnected
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->routeIs([
            'filament.admin.pages.configuracoes*',
            'filament.admin.auth.*',
        ])) {
            return $next($request);
        }

        if (! GoogleAccount::hasMain()) {
            return redirect()->route('filament.admin.pages.configuracoes');
        }
        return $next($request);
    }
}
