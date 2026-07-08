<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Src\Application\Shared\Mail\ConsolidatedDigestEmailPresenter;
use Src\Application\Shared\Mail\ConsolidatedJudicialActionsMailable;

beforeEach(function (): void {
    config([
        'notification.mail.digest_max_rows' => 3,
        'notification.mail.frontend_url_email_consolidated' => 'https://app.example.com/actuaciones',
        'notification.mail.frontend_digest_path' => '/notification-digests',
    ]);
});

function makeDigestRow(
    string $processNumber,
    bool $isAlert = false,
    string $actionText = 'Auto admite',
): array {
    return [
        'process_action_id' => uniqid(),
        'court' => 'Juzgado 001',
        'process_number' => $processNumber,
        'demandante' => 'Demandante Test',
        'demandado' => 'Demandado Test',
        'action_date' => '25 de junio de 2026',
        'action_text' => $actionText,
        'annotation' => '---',
        'term_start_date' => null,
        'term_end_date' => null,
        'registration_date' => '25 de junio de 2026',
        'is_alert' => $isAlert,
        'is_registration_alert' => false,
        'matched_keywords' => $isAlert ? 'Estado' : null,
        'alert_highlights' => [],
    ];
}

it('shows all rows by default without a row limit', function (): void {
    config(['notification.mail.digest_max_rows' => 0]);

    $data = Collection::times(5, fn (int $i) => makeDigestRow('111', false, "Actuación {$i}"));

    $presented = (new ConsolidatedDigestEmailPresenter)->present($data, 'digest-uuid');

    expect($presented['displayedActionsCount'])->toBe(5)
        ->and($presented['remainingActionsCount'])->toBe(0);
});

it('prioritizes alerts and limits rows when digest_max_rows is configured', function (): void {
    $data = collect([
        makeDigestRow('111', false, 'Normal 1'),
        makeDigestRow('111', true, 'Alerta 1'),
        makeDigestRow('222', false, 'Normal 2'),
        makeDigestRow('222', false, 'Normal 3'),
        makeDigestRow('333', false, 'Normal 4'),
    ]);

    $presented = (new ConsolidatedDigestEmailPresenter)->present($data, 'digest-uuid');

    expect($presented['totalActionsCount'])->toBe(5)
        ->and($presented['displayedActionsCount'])->toBe(3)
        ->and($presented['remainingActionsCount'])->toBe(2)
        ->and($presented['alertsCount'])->toBe(1)
        ->and($presented['digestUrl'])->toBe('https://app.example.com/actuaciones/notification-digests/digest-uuid')
        ->and($presented['displayedRows']->first()['action_text'])
        ->toBe('Alerta 1');
});

it('renders scrollable table digest email markup', function (): void {
    config([
        'notification.mail.digest_max_rows' => 0,
        'notification.mail.digest_table_width' => 1280,
    ]);

    $data = collect([
        makeDigestRow('76001418900120220081900', true, 'Fijacion Estado'),
        makeDigestRow('76001418900120220081900', false, 'Auto Ordena'),
    ]);

    $html = (new ConsolidatedJudicialActionsMailable($data, 'Mauricio Gutierrez', 'digest-uuid'))
        ->render();

    expect($html)
        ->toContain('76001418900120220081900')
        ->toContain('Ver detalle completo en NotiJudicial')
        ->toContain('https://app.example.com/actuaciones/notification-digests/digest-uuid')
        ->toContain('-webkit-overflow-scrolling: touch')
        ->toContain('Desliza horizontalmente la tabla para ver todas las columnas.')
        ->toContain('<thead>')
        ->toContain('width: 1280px')
        ->toContain('overflow-x: auto')
        ->toContain('max-width: 1200px');
});

it('shows remaining actions notice when digest exceeds configured max rows', function (): void {
    config(['notification.mail.digest_max_rows' => 3]);

    $data = Collection::times(5, fn (int $i) => makeDigestRow('111', false, "Actuación {$i}"));

    $html = (new ConsolidatedJudicialActionsMailable($data, 'Org', 'digest-uuid'))->render();

    expect($html)->toContain('... y 2 actuaciones más en el portal.');
});
