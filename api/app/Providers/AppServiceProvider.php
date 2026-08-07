<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void {
        Gate::define('admin', fn (User $user): bool => $user->active && $user->is_admin);
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
