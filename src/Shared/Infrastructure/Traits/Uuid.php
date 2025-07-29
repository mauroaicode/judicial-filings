<?php
namespace Core\Shared\Infrastructure\Traits;

use Illuminate\Support\Str;

trait Uuid
{
    /**
     * Boot function from Laravel
     */
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            $model->incrementing = false;
            $model->keyType = 'string';
            $model->{$model->getKeyName()} = Str::uuid()->toString();
        });
    }
}
