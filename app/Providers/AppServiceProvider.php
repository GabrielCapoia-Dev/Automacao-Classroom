<?php

namespace App\Providers;

use App\Models\DominioEmail;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\DominioEmailPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(DominioEmail::class, DominioEmailPolicy::class);
        Gate::define('admin-only', function ($user) {
            return $user->hasRole('Admin');
        });

        FilamentAsset::register([
            Css::make('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'),
            Js::make('leaflet-js',  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'),
        ]);
    }
}
