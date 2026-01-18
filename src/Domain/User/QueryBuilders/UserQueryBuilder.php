<?php

declare(strict_types=1);

namespace Src\Domain\User\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<\Src\Domain\User\Models\User>
 */
class UserQueryBuilder extends Builder
{
    /**
     * Filter users by active status.
     *
     * @return $this
     */
    public function active(): self
    {
        return $this->where('state', 'active');
    }

    /**
     * Filter users by inactive status.
     *
     * @return $this
     */
    public function inactive(): self
    {
        return $this->where('state', 'inactive');
    }

    /**
     * Filter users by email.
     *
     * @return $this
     */
    public function whereEmail(string $email): self
    {
        return $this->where('email', $email);
    }

    /**
     * Filter users by slug.
     *
     * @return $this
     */
    public function whereSlug(string $slug): self
    {
        return $this->where('slug', $slug);
    }
}
