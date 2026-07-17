<?php

namespace App\Providers;

use App\Http\Middleware\ForwardRequestToHostOnlyWhenRemote;
use App\Support\InternalToolAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;
use Opcodes\LogViewer\Http\Middleware\ForwardRequestToHostMiddleware;
use Opcodes\LogViewer\LogFile;
use Src\Application\Shared\Contracts\Alert\AnnotationAlertDetectionInterface;
use Src\Application\Shared\Process\Timeline\Contracts\ProcessTimelineRecorder;
use Src\Application\Shared\Process\Timeline\Services\RecordProcessTimelineEventService;
use Src\Application\Shared\Services\Alert\OllamaAnnotationAlertDetectionProvider;
use Src\Application\Shared\Services\Alert\OpenAIAnnotationAlertDetectionProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            ForwardRequestToHostMiddleware::class,
            ForwardRequestToHostOnlyWhenRemote::class
        );

        $this->app->bind(ProcessTimelineRecorder::class, RecordProcessTimelineEventService::class);

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
        LogViewer::auth(fn (Request $request): bool => InternalToolAuth::authorize($request));

        Gate::define('viewLogViewer', fn ($user = null) => InternalToolAuth::authorize(request()));

        Gate::define('downloadLogFile', fn ($user = null, ?LogFile $file = null) => ! app()->environment('production')
            || InternalToolAuth::authorize(request()));

        Gate::define('deleteLogFile', fn () => app()->environment('local'));
    }
}
