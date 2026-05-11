<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Shared\Process\Resources\ProcessDataSourceResource;
use Src\Application\Shared\Process\Services\ListProcessDataSourcesService;
use Src\Domain\Process\Models\ProcessDataSource;

readonly class ProcessDataSourceController
{
    /**
     * List active process data sources for forms (e.g. private import slug).
     */
    public function index(ListProcessDataSourcesService $service): JsonResponse
    {
        $sources = $service->handle();

        return response()->json(
            $sources
                ->map(fn (ProcessDataSource $source): array => ProcessDataSourceResource::fromModel($source)->toArray())
                ->values()
                ->all()
        );
    }
}
