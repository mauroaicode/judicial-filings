<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Profile\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Src\Application\AppUser\Profile\Data\UpdateProfileData;
use Src\Domain\AppUser\Models\AppUser;

class UpdateProfileService
{
    public function handle(AppUser $appUser, UpdateProfileData $data): AppUser
    {
        $payload = [
            'name' => $data->name,
            'last_name' => $data->last_name,
            'email' => $data->email,
            'identification' => $data->identification,
            'slug' => Str::slug($data->name . ' ' . $data->last_name),
        ];

        if (! empty($data->password)) {
            $payload['password'] = Hash::make($data->password);
            $payload['must_change_password'] = false;
        }

        $appUser->update($payload);

        return $appUser;
    }
}
