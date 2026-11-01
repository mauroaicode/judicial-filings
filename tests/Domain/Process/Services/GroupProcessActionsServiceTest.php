<?php

declare(strict_types=1);

use Src\Domain\Process\Services\GroupProcessActionsService;

function groupProcessActionsPairingRow(array $overrides): array
{
    return array_merge([
        'process_action_id' => uniqid('act_', true),
        'process_number' => '15322310300120250004200',
        'action' => '',
        'action_text' => '',
        'annotation' => '',
        'cons_action' => 1,
        'registration_date_iso' => '2026-10-30',
        'action_date_iso' => '2026-10-30',
    ], $overrides);
}

it('pairs fijacion estado with an auto on the same day', function (): void {
    $fijacion = groupProcessActionsPairingRow([
        'process_action_id' => 'fij-1',
        'action' => 'Fijación Estado',
        'action_text' => 'Fijación Estado',
        'cons_action' => 10,
    ]);
    $auto = groupProcessActionsPairingRow([
        'process_action_id' => 'auto-1',
        'action' => 'Auto Niega Control De Legalidad',
        'action_text' => 'Auto Niega Control De Legalidad',
        'cons_action' => 11,
    ]);

    $result = app(GroupProcessActionsService::class)->handle(collect([$fijacion, $auto]));

    expect($result[0]['notified_action_id'])->toBe('auto-1')
        ->and($result[1]['fijacion_action_id'])->toBe('fij-1');
});

it('does not pair an auto with a fijacion from another month', function (): void {
    $oldFijacion = groupProcessActionsPairingRow([
        'process_action_id' => 'fij-nov',
        'action' => 'Fijación Estado',
        'action_text' => 'Fijación Estado',
        'cons_action' => 8,
        'registration_date_iso' => '2025-11-12',
        'action_date_iso' => '2025-11-12',
    ]);
    $auto = groupProcessActionsPairingRow([
        'process_action_id' => 'auto-oct',
        'action' => 'Auto No Repone Concede Queja',
        'action_text' => 'Auto No Repone Concede Queja',
        'cons_action' => 12,
        'registration_date_iso' => '2026-10-30',
        'action_date_iso' => '2026-10-30',
    ]);

    $result = app(GroupProcessActionsService::class)->handle(collect([$oldFijacion, $auto]));

    expect($result[0])->not->toHaveKey('notified_action_id')
        ->and($result[1])->not->toHaveKey('fijacion_action_id');
});

it('lets a leftover same-day auto stay unpaired instead of claiming an old fijacion', function (): void {
    $oldFijacion = groupProcessActionsPairingRow([
        'process_action_id' => 'fij-nov',
        'action' => 'Fijación Estado',
        'action_text' => 'Fijación Estado',
        'cons_action' => 5,
        'registration_date_iso' => '2025-11-12',
        'action_date_iso' => '2025-11-12',
    ]);
    $todayFijacion = groupProcessActionsPairingRow([
        'process_action_id' => 'fij-today',
        'action' => 'Fijación Estado',
        'action_text' => 'Fijación Estado',
        'cons_action' => 20,
    ]);
    $autoNiega = groupProcessActionsPairingRow([
        'process_action_id' => 'auto-niega',
        'action' => 'Auto Niega Control De Legalidad',
        'action_text' => 'Auto Niega Control De Legalidad',
        'cons_action' => 21,
    ]);
    $autoQueja = groupProcessActionsPairingRow([
        'process_action_id' => 'auto-queja',
        'action' => 'Auto No Repone Concede Queja',
        'action_text' => 'Auto No Repone Concede Queja',
        'cons_action' => 22,
    ]);

    $result = app(GroupProcessActionsService::class)
        ->handle(collect([$oldFijacion, $todayFijacion, $autoNiega, $autoQueja]))
        ->keyBy('process_action_id');

    expect($result['fij-today']['notified_action_id'])->toBe('auto-niega')
        ->and($result['auto-niega']['fijacion_action_id'])->toBe('fij-today')
        ->and($result['fij-nov'])->not->toHaveKey('notified_action_id')
        ->and($result['auto-queja'])->not->toHaveKey('fijacion_action_id');
});

it('pairs using spanish formatted dates from consolidado rows', function (): void {
    $fijacion = groupProcessActionsPairingRow([
        'process_action_id' => 'fij-es',
        'action' => 'Fijación Estado',
        'action_text' => 'Fijación Estado',
        'registration_date_iso' => null,
        'action_date_iso' => null,
        'registration_date' => '30 de octubre de 2026',
        'action_date' => '30 de octubre de 2026',
    ]);
    $auto = groupProcessActionsPairingRow([
        'process_action_id' => 'auto-es',
        'action' => 'Auto Niega Control De Legalidad',
        'action_text' => 'Auto Niega Control De Legalidad',
        'registration_date_iso' => null,
        'action_date_iso' => null,
        'registration_date' => '30 de octubre de 2026',
        'action_date' => '30 de octubre de 2026',
    ]);

    $result = app(GroupProcessActionsService::class)->handle(collect([$fijacion, $auto]));

    expect($result[0]['notified_action_id'])->toBe('auto-es');
});

it('does not pair when spanish dates belong to different days', function (): void {
    $fijacion = groupProcessActionsPairingRow([
        'process_action_id' => 'fij-es',
        'action' => 'Fijación Estado',
        'action_text' => 'Fijación Estado',
        'registration_date_iso' => null,
        'action_date_iso' => null,
        'registration_date' => '12 de noviembre de 2025',
    ]);
    $auto = groupProcessActionsPairingRow([
        'process_action_id' => 'auto-es',
        'action' => 'Auto Niega Control De Legalidad',
        'action_text' => 'Auto Niega Control De Legalidad',
        'registration_date_iso' => null,
        'action_date_iso' => null,
        'registration_date' => '30 de octubre de 2026',
    ]);

    $result = app(GroupProcessActionsService::class)->handle(collect([$fijacion, $auto]));

    expect($result[0])->not->toHaveKey('notified_action_id')
        ->and($result[1])->not->toHaveKey('fijacion_action_id');
});

it('prefers the auto whose text appears in the fijacion annotation', function (): void {
    $fijacion = groupProcessActionsPairingRow([
        'process_action_id' => 'fij-1',
        'action' => 'Fijación Estado',
        'action_text' => 'Fijación Estado',
        'annotation' => 'Se notifica Auto No Repone Concede Queja',
        'cons_action' => 10,
    ]);
    $autoNiega = groupProcessActionsPairingRow([
        'process_action_id' => 'auto-niega',
        'action' => 'Auto Niega Control De Legalidad',
        'action_text' => 'Auto Niega Control De Legalidad',
        'cons_action' => 11,
    ]);
    $autoQueja = groupProcessActionsPairingRow([
        'process_action_id' => 'auto-queja',
        'action' => 'Auto No Repone Concede Queja',
        'action_text' => 'Auto No Repone Concede Queja',
        'cons_action' => 12,
    ]);

    $result = app(GroupProcessActionsService::class)
        ->handle(collect([$fijacion, $autoNiega, $autoQueja]))
        ->keyBy('process_action_id');

    expect($result['fij-1']['notified_action_id'])->toBe('auto-queja');
});
