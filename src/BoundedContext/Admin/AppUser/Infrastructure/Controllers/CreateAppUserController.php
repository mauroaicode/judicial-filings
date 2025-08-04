<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\AppUser\Infrastructure\Controllers;

use Core\BoundedContext\Admin\AppUser\Application\{
    Data\CreateAppUserData,
    Resources\AppUserResource,
    Actions\CreateAppUserUseCase,
};
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

readonly class CreateAppUserController
{
    public function __construct(
        private CreateAppUserUseCase $createAppUserUseCase
    ) {}

    /**
     * Store a newly created resource in storage.
     */
    public function __invoke(CreateAppUserData $data): JsonResponse
    {
        $appUser = ($this->createAppUserUseCase)($data);

        return response()->json(
            AppUserResource::fromModel($appUser),
            ResponseAlias::HTTP_CREATED
        );
    }
}
