<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\AppUser\Infrastructure\Controllers;

use Core\BoundedContext\Admin\AppUser\Application\Actions\ListAppUserUseCase;
use Core\BoundedContext\Admin\AppUser\Application\Resources\AppUserResource;
use Core\Shared\Infrastructure\Persistence\Eloquent\AppUser;
use Illuminate\Http\JsonResponse;

readonly class ListAppUsersController
{
    public function __construct(
        private ListAppUserUseCase $listAppUserUseCase
    ){
    }

    /**
     * Display a listing of the resource.
     */
    public function __invoke(): JsonResponse
    {
        $appUsers = ($this->listAppUserUseCase)();

        $resources = $appUsers->map(fn(AppUser $appUser) => AppUserResource::fromModel($appUser)
        );

        return response()->json($resources);
    }
}
