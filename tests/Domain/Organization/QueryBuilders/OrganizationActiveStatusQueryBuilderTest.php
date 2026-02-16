<?php

declare(strict_types=1);

use Src\Domain\Organization\QueryBuilders\OrganizationActiveStatusQueryBuilder;

it('returns list of active and inactive statuses with value and label', function (): void {
    $queryBuilder = new OrganizationActiveStatusQueryBuilder;
    $list = $queryBuilder->getList();

    expect($list)->toHaveCount(2);
    $values = array_column($list, 'value');
    expect($values)->toContain('active', 'inactive');
    expect(array_keys($list[0] ?? []))->toContain('value', 'label');
});
