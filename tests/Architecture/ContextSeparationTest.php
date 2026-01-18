<?php

declare(strict_types=1);

arch('Admin context does not use AppUser context')
    ->expect('Src\Application\Admin')
    ->not->toUse('Src\Application\AppUser');

arch('AppUser context does not use Admin context')
    ->expect('Src\Application\AppUser')
    ->not->toUse('Src\Application\Admin');
