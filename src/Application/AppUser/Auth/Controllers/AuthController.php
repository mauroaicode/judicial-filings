<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Auth\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Src\Application\AppUser\Auth\Data\ForgotPasswordData;
use Src\Application\AppUser\Auth\Data\LoginData;
use Src\Application\AppUser\Auth\Data\ResetPasswordData;
use Src\Application\AppUser\Auth\Data\VerifyPasswordData;
use Src\Application\AppUser\Auth\Resources\AuthResource;
use Src\Application\AppUser\Auth\Services\ForgotPasswordService;
use Src\Application\AppUser\Auth\Services\ResetPasswordService;
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

    public function forgotPassword(ForgotPasswordData $data, ForgotPasswordService $service): JsonResponse
    {
        $service->handle($data);

        return response()->json([
            'message' => __('auth.forgot_password_sent'),
        ]);
    }

    public function resetPassword(ResetPasswordData $data, ResetPasswordService $service): JsonResponse
    {
        $service->handle($data);

        return response()->json([
            'message' => __('auth.password_reset_successful'),
        ]);
    }

    /**
     * Verify the current user's password to unlock the session.
     * Does NOT issue a new token — only validates the credential.
     * Requires a valid Bearer token (auth:sanctum middleware).
     *
     * @throws ValidationException
     */
    public function verifyPassword(VerifyPasswordData $data, Request $request): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = $request->user('sanctum');

        if (! Hash::check($data->password, $appUser->password)) {
            throw ValidationException::withMessages([
                'password' => [__('auth.password_incorrect')],
            ]);
        }

        return response()->json([
            'message' => __('auth.session_unlocked'),
        ]);
    }
}
