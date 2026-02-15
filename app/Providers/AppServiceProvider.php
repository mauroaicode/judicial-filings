<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Application\Shared\Contracts\Alert\AnnotationAlertDetectionInterface;
use Src\Application\Shared\Services\Alert\OllamaAnnotationAlertDetectionProvider;
use Src\Application\Shared\Services\Alert\OpenAIAnnotationAlertDetectionProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AnnotationAlertDetectionInterface::class, function (): AnnotationAlertDetectionInterface {
            $provider = config('alert-ai.provider', 'openai');

            return match ($provider) {
                'ollama' => $this->app->make(OllamaAnnotationAlertDetectionProvider::class),
                default => $this->app->make(OpenAIAnnotationAlertDetectionProvider::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
