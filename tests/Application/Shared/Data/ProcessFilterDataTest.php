<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Src\Application\Shared\Data\ProcessFilterData;

it('accepts missing privacy as null', function (): void {
    $data = ProcessFilterData::validateAndCreate([]);

    expect($data->privacy)->toBeNull();
});

it('accepts privacy private and public', function (): void {
    $private = ProcessFilterData::validateAndCreate(['privacy' => 'private']);
    expect($private->privacy)->toBe('private');

    $public = ProcessFilterData::validateAndCreate(['privacy' => 'public']);
    expect($public->privacy)->toBe('public');
});

it('rejects invalid privacy value on validateAndCreate', function (): void {
    ProcessFilterData::validateAndCreate(['privacy' => 'all']);
})->throws(ValidationException::class);

it('accepts status suspended', function (): void {
    $data = ProcessFilterData::validateAndCreate(['status' => 'suspended']);

    expect($data->status)->toBe('suspended');
});

it('accepts severity_color suspended', function (): void {
    $data = ProcessFilterData::validateAndCreate(['severity_color' => 'suspended']);

    expect($data->severity_color)->toBe('suspended');
});

it('rejects invalid status value on validateAndCreate', function (): void {
    ProcessFilterData::validateAndCreate(['status' => 'archived']);
})->throws(ValidationException::class);
