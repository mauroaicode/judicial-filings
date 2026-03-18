<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Auth\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Src\Application\AppUser\Auth\Data\ForgotPasswordData;
use Src\Application\AppUser\Auth\Notifications\ForgotPasswordNotification;
use Src\Domain\AppUser\Models\AppUser;

class ForgotPasswordService
{
    public function handle(ForgotPasswordData $data): void
    {
        $appUser = $this->findUser($data->identification);

        if (is_null($appUser)) {
            throw ValidationException::withMessages([
                'identification' => [__('auth.user_not_found')],
            ]);
        }

        $token = $this->createToken($appUser);

        $appUser->notify(new ForgotPasswordNotification($token, $appUser->identification));
    }

    private function findUser(string $identification): ?AppUser
    {
        return AppUser::query()->where('identification', $identification)->first();
    }

    private function createToken(AppUser $appUser): string
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $appUser->email],
            [
                'email' => $appUser->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        return $token;
    }
}
