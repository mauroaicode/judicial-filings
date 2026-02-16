<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Domain\Organization\Enums\OrganizationType;

readonly class OrganizationTypeController
{
    /**
     * List organization types (natural, juridical) for dropdowns/forms.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => OrganizationType::toArray(),
        ]);
    }
}
