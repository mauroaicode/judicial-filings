<?php

declare(strict_types=1);

arch()->preset()->php();
arch()->preset()->security()->ignoring(['sha', 'md5']);

arch('No debugging functions in Src')
    ->expect('Src')
    ->toUseStrictTypes()
    ->not->toUse(['die', 'dd', 'dump']);

arch('No debugging functions in Test')
    ->expect('Test')
    ->toUseStrictTypes()
    ->not->toUse(['die', 'dd', 'dump']);
