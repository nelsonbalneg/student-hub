<?php

namespace StudentHub\EvaluationBuilder;

use Illuminate\Support\ServiceProvider;
use StudentHub\EvaluationBuilder\Services\TemplatePayloadService;

class EvaluationBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/evaluation-builder.php', 'evaluation-builder');
        $this->app->singleton(TemplatePayloadService::class);
    }

    public function boot(): void
    {
        if (config('evaluation-builder.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->publishes([
            __DIR__.'/../config/evaluation-builder.php' => config_path('evaluation-builder.php'),
        ], 'evaluation-builder-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'evaluation-builder-migrations');
    }
}
