<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Src\Application\Admin\Organization\Data\StoreOrganizationData;
use Src\Application\Shared\Notifications\AccountCreatedNotification;
use Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Throwable;

class OrganizationCreatorService
{
    private const PHONE_PREFIX = '+57';

    public function __construct(
        private readonly OrganizationProcessQuotaService $organizationProcessQuotaService,
    ) {}

    /**
     * Create a new organization and its first owner (AppUser), then send account email.
     *
     * @throws Throwable
     */
    public function handle(StoreOrganizationData $data): Organization
    {
        return DB::transaction(function () use ($data): Organization {
            $slug = $this->generateUniqueSlug($data->name);
            $phoneFormatted = $this->formatPhone($data->phone);
            $password = $this->generateTemporaryPassword();

            $organization = Organization::query()->create([
                'name' => $data->name,
                'slug' => $slug,
                'type' => $data->type,
                'identification' => $data->identification,
                'address' => $data->address,
                'phone' => $phoneFormatted,
                'email' => $data->email,
                'contact_person' => $data->contact_person,
                'is_active' => true,
            ]);

            [$ownerName, $ownerLastName] = $this->resolveOwnerNameAndLastName($data);
            $appUserSlug = $this->generateUniqueAppUserSlug($ownerName, $ownerLastName);

            $appUser = AppUser::query()->create([
                'name' => $ownerName,
                'last_name' => $ownerLastName,
                'slug' => $appUserSlug,
                'email' => $data->email,
                'identification' => $data->identification,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);

            $organization->appUsers()->attach($appUser->id, ['is_owner' => true]);

            $this->organizationProcessQuotaService->ensureSettings($organization);

            $this->createDefaultNotificationChannel($organization, $appUser);

            if ($data->generate_password) {
                // @phpstan-ignore-next-line
                $organization->createdPassword = $password;
            } else {
                DB::afterCommit(function () use ($appUser, $password): void {
                    $appUser->notify(new AccountCreatedNotification($password));
                });
            }

            return $organization->load('appUsers');
        });
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function formatPhone(string $phoneDigits): string
    {
        $digits = preg_replace('/\D/', '', $phoneDigits);
        if (strlen((string) $digits) !== 10) {
            return self::PHONE_PREFIX.' '.$phoneDigits;
        }

        // Format: +56 9 8765 4321 (first digit, then 4, then 4)
        return self::PHONE_PREFIX.' '.substr((string) $digits, 0, 1).' '.substr((string) $digits, 1, 4).' '.substr((string) $digits, 5, 4);
    }

    private function generateTemporaryPassword(): string
    {
        return Str::password(12);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveOwnerNameAndLastName(StoreOrganizationData $data): array
    {
        if ($data->contact_person !== null && $data->contact_person !== '') {
            $parts = explode(' ', trim($data->contact_person), 2);

            return [
                $parts[0],
                $parts[1] ?? '',
            ];
        }

        $parts = explode(' ', trim($data->name), 2);

        return [
            $parts[0],
            $parts[1] ?? '',
        ];
    }

    private function generateUniqueAppUserSlug(string $name, string $lastName): string
    {
        $base = Str::slug(trim($name.' '.$lastName)) ?: Str::slug('owner');
        $slug = $base;
        $counter = 1;

        while (AppUser::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function createDefaultNotificationChannel(Organization $organization, AppUser $appUser): void
    {
        $organization->notificationChannels()->createMany([
            [
                'channel_type' => 'email',
                'channel_value' => $appUser->email,
                'is_active' => true,
                'priority' => 1,
            ],
            [
                'channel_type' => 'internal',
                'channel_value' => 'internal',
                'is_active' => true,
                'priority' => 1,
            ],
        ]);
    }
}
