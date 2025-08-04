<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\AppUser\Infrastructure\Controllers;

use Core\BoundedContext\Admin\AppUser\Application\Actions\UpdateAppUserUseCase;
use Core\BoundedContext\Admin\AppUser\Application\Data\UpdateAppUserData;
use Core\BoundedContext\Admin\AppUser\Application\Resources\AppUserResource;
use Illuminate\Http\JsonResponse;

readonly class UpdateAppUserController
{
    public function __construct(
        private UpdateAppUserUseCase $updateAppCustomerUseCase
    ) {}

    /**
     * Update the specified resource in storage.
     */
    public function __invoke(UpdateAppUserData $data, string $id): JsonResponse
    {
        $appCustomer = ($this->updateAppCustomerUseCase)($id, $data);

        return response()->json(AppUserResource::fromModel($appCustomer));
    }
}
