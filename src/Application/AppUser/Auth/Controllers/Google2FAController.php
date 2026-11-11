<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Auth\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PragmaRX\Google2FALaravel\Google2FA;
use Src\Application\AppUser\Auth\Data\Google2FAValidateData;
use Src\Application\AppUser\Auth\Data\Google2FAVerifyData;
use Src\Domain\AppUser\Enums\SessionUnlockMethod;
use Src\Domain\AppUser\Models\AppUser;

class Google2FAController
{
    /**
     * Generates a new secret key and QR code for the user.
     */
    public function generateCode(Request $request, Google2FA $google2fa): JsonResponse
    {
        $appUser = $this->authenticatedUser($request);
        $secretKey = $google2fa->generateSecretKey();
        $issuer = (string) config('app.name');

        $appUser->update([
            'google_2fa_secret' => $secretKey,
            'google_2fa_enabled' => false,
            'session_unlock_method' => SessionUnlockMethod::PASSWORD,
        ]);

        return response()->json([
            'secret' => $secretKey,
            'qr_code_url' => $google2fa->getQRCodeUrl($issuer, $appUser->email, $secretKey),
            'qr_code_svg' => $google2fa->getQRCodeInline($issuer, $appUser->email, $secretKey),
        ]);
    }

    /**
     * Verifies the code provided by the user and activates 2FA.
     */
    public function verifyGeneratedCode(Google2FAVerifyData $data, Request $request): JsonResponse
    {
        $appUser = $this->authenticatedUser($request);

        $appUser->tokens()->delete();

        $verifiedToken = $appUser->createToken(name: 'auth-token', abilities: ['2fa_verified']);

        $appUser->update([
            'google_2fa_enabled' => true,
        ]);

        return response()->json([
            'token' => $verifiedToken->plainTextToken,
        ]);
    }

    /**
     * Disables 2FA for the authenticated user.
     */
    public function disable(Request $request): JsonResponse
    {
        $appUser = $this->authenticatedUser($request);

        if (! $appUser->google_2fa_enabled) {
            abort(400, __('auth.2fa_not_enabled'));
        }

        $appUser->tokens()->delete();

        $appUser->update([
            'google_2fa_enabled' => false,
            'google_2fa_secret' => null,
            'session_unlock_method' => SessionUnlockMethod::PASSWORD,
        ]);

        $token = $appUser->createToken(name: 'auth-token');

        return response()->json([
            'token' => $token->plainTextToken,
        ]);
    }

    /**
     * Validates a 2FA code during login and replaces the limited token.
     */
    public function validate2faAfterLogin(Google2FAValidateData $data, Request $request): JsonResponse
    {
        $appUser = $this->authenticatedUser($request);

        $appUser->tokens()->delete();

        $verifiedToken = $appUser->createToken(name: 'auth-token', abilities: ['2fa_verified']);

        return response()->json([
            'token' => $verifiedToken->plainTextToken,
        ]);
    }

    private function authenticatedUser(Request $request): AppUser
    {
        /** @var AppUser $user */
        $user = $request->user();

        return $user;
    }
}
