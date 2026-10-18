<?php

namespace App\Support\Modules;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base provider for a domain module under app/Modules/<Name>.
 *
 * Convention: each module may ship
 *   - routes.php                 → mounted under /api/v1 with the "api" middleware group
 *   - Database/migrations/*.php  → loaded with the app's migrations
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /** Absolute path of the module directory. */
    abstract protected function modulePath(): string;

    public function boot(): void
    {
        $migrations = $this->modulePath().'/Database/migrations';
        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        $routes = $this->modulePath().'/routes.php';
        if (file_exists($routes) && ! $this->app->routesAreCached()) {
            Route::middleware('api')->prefix('api/v1')->group($routes);
        }
    }
}
