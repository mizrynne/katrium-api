<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // B9: Telescope is a require-dev package. Register it only when it is installed AND we run locally,
        // so a --no-dev production image never tries to load a missing class.
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn (?User $user, string $ability) => $user?->hasRole('Superadmin') ? true : null);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // General API traffic. Keyed by token (a throttle runs before auth resolves the user), so staff
        // behind one office IP do not share a bucket. The Gantt pages through bookings, so keep it roomy.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)->by(
            $request->bearerToken() ? 'token:'.sha1($request->bearerToken()) : 'ip:'.$request->ip()
        ));

        // Google redirect/callback: unauthenticated and abusable.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by('ip:'.$request->ip()));

        // Beds24 webhook: generous for bulk updates, but caps secret-guessing.
        RateLimiter::for('webhook', fn (Request $request) => Limit::perMinute(120)->by('ip:'.$request->ip()));
    }
}