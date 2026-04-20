<?php

declare(strict_types=1);

namespace Src\Domain\AiChat\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;

class AiChatQueryBuilder extends Builder
{
    public function whereOrganization(string $organizationId): self
    {
        return $this->where('organization_id', $organizationId);
    }

    public function whereProcess(string $processId): self
    {
        return $this->where('process_id', $processId);
    }

    public function whereAppUser(string $appUserId): self
    {
        return $this->where('app_user_id', $appUserId);
    }

    public function whereActive(): self
    {
        return $this->where('is_active', true);
    }

    public function wherePublicOrTransitive(string $appUserId): self
    {
        return $this->where(function (\Illuminate\Contracts\Database\Query\Builder $query) use ($appUserId): void {
            $query->where('is_private', false)
                ->orWhere('app_user_id', $appUserId);
        });
    }

    public function orderedByRecent(): self
    {
        return $this->latest();
    }
}
