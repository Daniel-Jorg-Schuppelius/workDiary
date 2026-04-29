<?php

namespace App\Providers;

use App\Auth\LegacyUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
    public function register(): void {
    }

    public function boot(): void {
        Auth::provider('legacy', function ($app) {
            return new LegacyUserProvider($app['hash']);
        });
    }
}
