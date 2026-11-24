<?php

namespace App\Providers;

use App\Agents\ToolRegistry;
use App\Support\CurrentWorkspace;
use Illuminate\Support\ServiceProvider;
use App\Ai\Tools\HttpGetTool;
use App\Ai\Tools\SendEmailTool;
use App\Ai\AnthropicAiProvider;
use App\Contracts\AiProvider;

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
        $this->app->singleton(CurrentWorkspace::class);
        $this->app->bind(AiProvider::class, fn () => new AnthropicAiProvider(
        apiKey: (string) config('services.anthropic.key'),
        model: (string) config('services.anthropic.model'),
        maxTokens: (int) config('services.anthropic.max_tokens'),
));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
