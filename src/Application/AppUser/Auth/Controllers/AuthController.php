<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Auth\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Src\Application\AppUser\Auth\Data\LoginData;
use Src\Application\AppUser\Auth\Resources\AuthResource;
use Src\Domain\AppUser\Models\AppUser;

class AuthController
{
    /**
     * Handle an incoming authentication request.
     *
     * @throws ValidationException
     */
    public function login(LoginData $data): JsonResponse
    {
        $appUser = AppUser::query()->where('identification', $data->identification)->first();

        $token = $appUser->createToken($appUser->identification)->plainTextToken;

        $authResource = AuthResource::fromModel($appUser, $token, false, $appUser->must_change_password);

        return response()->json($authResource->toArray());
    }
}
