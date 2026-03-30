<?php

declare(strict_types=1);

namespace Src\Domain\AppUser\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModelClass of \Src\Domain\AppUser\Models\AppUser
 *
 * @extends Builder<TModelClass>
 */
class AppUserQueryBuilder extends Builder {}
