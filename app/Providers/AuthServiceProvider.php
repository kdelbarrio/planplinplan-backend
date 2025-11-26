<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Gate de acceso al panel admin
        Gate::define('access-admin', fn(User $user) => (bool) $user->is_admin);
    }

}
