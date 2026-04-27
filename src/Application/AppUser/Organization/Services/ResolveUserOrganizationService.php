<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Organization\Services;

use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;

class ResolveUserOrganizationService
{
    /**
     * Resolve the organization for the authenticated user if it's an AppUser.
     */
    public function handle(): ?Organization
    {
        /** @var AppUser|null $user */
        $user = auth()->user();

        if ($user instanceof AppUser) {
            $organization = $user->organizations()->first();

            if (! $organization) {
                abort(422, __('process.user_has_no_organization'));
            }

            return $organization;
        }

        return null;
    }
}
