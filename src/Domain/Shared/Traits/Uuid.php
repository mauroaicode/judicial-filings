<?php

declare(strict_types=1);

namespace Src\Domain\Shared\Traits;

use Illuminate\Support\Str;

trait Uuid
{
    /**
     * Boot function from Laravel
     */
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model): void {
            $model->incrementing = false;
            $model->keyType = 'string';
            $model->{$model->getKeyName()} = Str::uuid()->toString();
        });
    }
}
