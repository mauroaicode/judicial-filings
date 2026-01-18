<?php

declare(strict_types=1);

it('ensures no files outside the config directory use env()', function (): void {
    expect('Src')->not->toUse(['env']);
});
