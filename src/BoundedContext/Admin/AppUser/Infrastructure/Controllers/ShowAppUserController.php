<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\AppUser\Infrastructure\Controllers;

use Illuminate\Http\JsonResponse;
use Core\BoundedContext\Admin\AppUser\Application\Resources\AppUserResource;
use Core\BoundedContext\Admin\AppUser\Application\Actions\FindAppUserUseCase;


readonly class ShowAppUserController
{
    public function __construct(
        private FindAppUserUseCase $findAppUserUseCase
    ) {}

    /**
     * Display the specified resource.
     */
    public function __invoke(string $id): JsonResponse
    {
        $appCustomer = ($this->findAppUserUseCase)($id);

        return response()->json(AppUserResource::fromModel($appCustomer));
    }
}
