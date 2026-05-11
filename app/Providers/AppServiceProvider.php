<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Constants\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('upload-bank-file', function (User $user) {
            return in_array($user->role, [Role::ADMIN, Role::UPLOADER]);
        });

        Gate::define('manage-users', function (User $user) {
            return $user->role === Role::ADMIN;
        });

        Gate::define('config-system', function (User $user) {
            return $user->role === Role::ADMIN;
        });

        Gate::define('reprocess-file', function (User $user) {
            return $user->role === Role::ADMIN;
        });
        
        Gate::define('view-dashboard', function (User $user) {
             return in_array($user->role, [Role::ADMIN, Role::UPLOADER, Role::VIEWER]);
        });
    }
}
