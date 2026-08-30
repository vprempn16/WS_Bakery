<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('api', function (Request $request) {
            $userKey = optional($request->user())->id ?: $request->ip();

            return [
                Limit::perMinute(120)->by('api-user:' . $userKey),
                Limit::perMinute(300)->by('api-ip:' . $request->ip()),
            ];
        });

        RateLimiter::for('auth-login', function (Request $request) {
            return Limit::perMinute(5)->by('login:' . $request->ip());
        });

        RateLimiter::for('auth-register', function (Request $request) {
            return Limit::perHour(5)->by('register:' . $request->ip());
        });

        RateLimiter::for('writes', function (Request $request) {
            $userKey = optional($request->user())->id ?: $request->ip();

            return Limit::perMinute(40)->by('write:' . $userKey);
        });

        RateLimiter::for('expensive', function (Request $request) {
            $userKey = optional($request->user())->id ?: $request->ip();

            return Limit::perMinute(20)->by('expensive:' . $userKey);
        });

        Event::listen(MigrationsEnded::class, function () {
            if (app()->runningUnitTests()) {
                return;
            }

            if (! Schema::hasTable('crm_fields')) {
                return;
            }

            Artisan::call('migrate:module-fields');
        });
    }
}
