<?php

declare(strict_types=1);

namespace Tests\Domain\Keyword\QueryBuilders;

use Src\Domain\Keyword\Models\Keyword;
use Src\Domain\Keyword\QueryBuilders\KeywordQueryBuilder;
use Tests\TestCase;

class KeywordQueryBuilderTest extends TestCase
{
    public function test_it_returns_keyword_query_builder(): void
    {
        $builder = Keyword::query();
        $this->assertInstanceOf(KeywordQueryBuilder::class, $builder);
    }
}
