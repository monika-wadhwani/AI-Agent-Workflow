<?php

namespace App\Providers;

use App\Agents\ToolRegistry;
use App\Support\CurrentWorkspace;
use Illuminate\Support\ServiceProvider;
use App\Ai\Tools\HttpGetTool;
use App\Ai\Tools\SendEmailTool;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class, fn () => (new ToolRegistry)
        ->register(new HttpGetTool)
        ->register(new SendEmailTool));
        $this->app->scoped(CurrentWorkspace::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
