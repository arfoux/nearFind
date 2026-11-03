<?php

namespace App\Providers;

use App\Auth\SealGuard;
use App\Support\Seal\Sealer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Sealer::class, function (Application $app): Sealer {
            $config = $app['config']->get('seal', []);
            $audience = $config['audience'] ?? null;

            return new Sealer(
                issuer: (string) ($config['issuer'] ?? 'nearFind'),
                audience: is_string($audience) && $audience !== '' ? $audience : null,
                keys: is_array($config['keys'] ?? null) ? $config['keys'] : [],
                currentKeyId: (string) ($config['current_key_id'] ?? 'local'),
                clockToleranceMs: (int) ($config['clock_tolerance'] ?? 0) * 1000,
                maxTokenSize: (int) ($config['max_token_size'] ?? 16384),
            );
        });
    }

    public function boot(): void
    {
        Auth::extend('seal', function (Application $app, string $name, array $config): SealGuard {
            $guard = new SealGuard(
                $app['request'],
                $app->make(Sealer::class),
                (string) $app['config']->get('seal.session.cookie', 'nearfind_seal'),
            );

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }
}
