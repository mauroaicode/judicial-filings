<?php

declare(strict_types=1);

namespace Src\Application\Admin\Auth\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PragmaRX\Google2FALaravel\Google2FA;
use Src\Application\Admin\Auth\Data\Google2FAValidateData;
use Src\Application\Admin\Auth\Data\Google2FAVerifyData;
use Src\Domain\User\Models\User;

class Google2FAController
{
    /**
     * Generates a new secret key and QR code for the admin.
     */
    public function generateCode(Request $request, Google2FA $google2fa): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $secretKey = $google2fa->generateSecretKey();
        $issuer = (string) config('app.name');

        $user->update([
            'google_2fa_secret' => $secretKey,
            'google_2fa_enabled' => false,
        ]);

        return response()->json([
            'secret' => $secretKey,
            'qr_code_url' => $google2fa->getQRCodeUrl($issuer, $user->email, $secretKey),
            'qr_code_svg' => $google2fa->getQRCodeInline($issuer, $user->email, $secretKey),
        ]);
    }

    /**
     * Verifies the code provided by the admin and activates 2FA.
     */
    public function verifyGeneratedCode(Google2FAVerifyData $data, Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $user->tokens()->delete();

        $verifiedToken = $user->createToken(name: 'auth-token', abilities: ['2fa_verified']);

        $user->update([
            'google_2fa_enabled' => true,
        ]);

        return response()->json([
            'token' => $verifiedToken->plainTextToken,
        ]);
    }

    /**
     * Disables 2FA for the authenticated admin.
     */
    public function disable(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        if (! $user->google_2fa_enabled) {
            abort(400, __('auth.2fa_not_enabled'));
        }

        $user->tokens()->delete();

        $user->update([
            'google_2fa_enabled' => false,
            'google_2fa_secret' => null,
        ]);

        $token = $user->createToken(name: 'auth-token');

        return response()->json([
            'token' => $token->plainTextToken,
        ]);
    }

    /**
     * Validates a 2FA code during login and replaces the limited token.
     */
    public function validate2faAfterLogin(Google2FAValidateData $data, Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $user->tokens()->delete();

        $verifiedToken = $user->createToken(name: 'auth-token', abilities: ['2fa_verified']);

        return response()->json([
            'token' => $verifiedToken->plainTextToken,
        ]);
    }

    private function authenticatedUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
