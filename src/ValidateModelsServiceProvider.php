<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels;

use Illuminate\Support\ServiceProvider;
use Indy2kro\ValidateModels\Console\ValidateModelsCommand;
use Indy2kro\ValidateModels\Contracts\ModelLocator;
use Indy2kro\ValidateModels\Services\FilesystemModelLocator;

class ValidateModelsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/validate-models.php', 'validate-models');

        $this->app->bind(ModelLocator::class, FilesystemModelLocator::class);
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/validate-models.php' => config_path('validate-models.php'),
        ], 'validate-models-config');

        // Commands are only needed in console
        if ($this->app->runningInConsole()) {
            $this->commands([ValidateModelsCommand::class]);
        }
    }
}
