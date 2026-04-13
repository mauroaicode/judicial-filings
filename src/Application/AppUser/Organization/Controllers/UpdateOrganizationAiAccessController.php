<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Organization\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\AppUser\Organization\Data\UpdateOrganizationAiAccessData;
use Src\Application\AppUser\Organization\Services\UpdateOrganizationAiAccessService;

class UpdateOrganizationAiAccessController
{
    public function __invoke(
        string $organizationId,
        UpdateOrganizationAiAccessData $data,
        UpdateOrganizationAiAccessService $service
    ): JsonResponse {

        $service->handle($organizationId, $data);

        return response()->json([
            'message' => 'El acceso a IA de la organización ha sido actualizado exitosamente.',
            'is_ai_enabled' => $data->is_ai_enabled,
        ]);
    }
}
