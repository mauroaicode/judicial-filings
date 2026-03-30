<?php

declare(strict_types=1);

namespace Tests\Domain\AppUser\QueryBuilders;

use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\AppUser\QueryBuilders\AppUserQueryBuilder;
use Tests\TestCase;

class AppUserQueryBuilderTest extends TestCase
{
    public function test_it_returns_app_user_query_builder(): void
    {
        $builder = AppUser::query();
        $this->assertInstanceOf(AppUserQueryBuilder::class, $builder);
    }
}
