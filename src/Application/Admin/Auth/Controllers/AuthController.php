<?php

declare(strict_types=1);

namespace Src\Application\Admin\Auth\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Src\Application\Admin\Auth\Data\LoginData;
use Src\Application\Admin\Auth\Resources\AuthResource;
use Src\Domain\User\Models\User;

class AuthController
{
    /**
     * Handle an incoming authentication request.
     *
     * @throws ValidationException
     */
    public function login(LoginData $data): JsonResponse
    {
        $user = User::query()->whereEmail($data->email)->first();

        $token = $user->createToken($user->email)->plainTextToken;

        $authResource = AuthResource::fromModel($user, $token, false);

        return response()->json($authResource->toArray());
    }
}
