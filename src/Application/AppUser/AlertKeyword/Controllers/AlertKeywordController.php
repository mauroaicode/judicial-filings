<?php

declare(strict_types=1);

namespace Src\Application\AppUser\AlertKeyword\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Domain\Process\Models\AlertActionKeyword;

readonly class AlertKeywordController
{
    /**
     * List alert action keyword types (id, name, slug) for filtering actuacion_alerta notifications.
     */
    public function index(): JsonResponse
    {
        $keywords = AlertActionKeyword::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json(['data' => $keywords]);
    }
}
