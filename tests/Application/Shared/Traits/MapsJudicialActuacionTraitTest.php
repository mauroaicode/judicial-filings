<?php

declare(strict_types=1);

use Src\Application\Shared\Traits\MapsJudicialActuacionTrait;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

final class MapsJudicialActuacionTraitTester
{
    use MapsJudicialActuacionTrait;

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function map(array $row): array
    {
        return $this->mapApiActuacionToAttributes($row);
    }
}

it('falls back action_date to fechaRegistro when fechaActuacion is absent', function (): void {
    $mapper = new MapsJudicialActuacionTraitTester;

    $attrs = $mapper->map([
        'idRegActuacion' => 1100067064,
        'consActuacion' => 2,
        'actuacion' => 'Auto inadmite demanda',
        'fechaRegistro' => '2023-04-03',
    ]);

    expect($attrs['action_date'])->toBe('2023-04-03');
    expect($attrs['registration_date'])->toBe('2023-04-03');
});

it('uses PascalCase FechaRegistro when camelCase fechaRegistro is missing', function (): void {
    $mapper = new MapsJudicialActuacionTraitTester;

    $attrs = $mapper->map([
        'idRegActuacion' => 1,
        'consActuacion' => 1,
        'actuacion' => 'Act',
        'FechaRegistro' => '2023-04-03',
    ]);

    expect($attrs['registration_date'])->toBe('2023-04-03');
    expect($attrs['action_date'])->toBe('2023-04-03');
});

it('fills missing action_date from registration_date on ProcessAction creating event', function (): void {
    $process = Process::factory()->create();

    $action = ProcessAction::query()->create([
        'process_id' => $process->id,
        'action_registration_id' => random_int(9000000000, 9999999999),
        'cons_action' => 1,
        'action' => 'Act',
        'annotation' => null,
        'start_date' => null,
        'end_date' => null,
        'registration_date' => '2023-04-03',
        'action_date' => null,
    ]);

    $action->refresh();
    expect($action->action_date->format('Y-m-d'))->toBe('2023-04-03');
});
