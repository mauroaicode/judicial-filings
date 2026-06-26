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

it('prioritizes alerts and limits rows for the email presenter', function (): void {
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
        ->and($presented['processGroups']->flatMap(fn (array $group) => $group['actions'])->first()['action_text'])
        ->toBe('Alerta 1');
});

it('groups displayed actions by process number', function (): void {
    $data = collect([
        makeDigestRow('111', false, 'A1'),
        makeDigestRow('111', false, 'A2'),
        makeDigestRow('222', false, 'B1'),
    ]);

    config(['notification.mail.digest_max_rows' => 8]);

    $groups = (new ConsolidatedDigestEmailPresenter)->present($data, 'digest-uuid')['processGroups'];

    expect($groups)->toHaveCount(2)
        ->and($groups->firstWhere('process_number', '111')['actions'])->toHaveCount(2)
        ->and($groups->firstWhere('process_number', '222')['actions'])->toHaveCount(1);
});

it('renders grouped digest email without wide table markup', function (): void {
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
        ->not->toContain('min-width: 800px')
        ->not->toContain('<thead>');
});

it('shows remaining actions notice when digest exceeds configured max rows', function (): void {
    $data = Collection::times(5, fn (int $i) => makeDigestRow('111', false, "Actuación {$i}"));

    $html = (new ConsolidatedJudicialActionsMailable($data, 'Org', 'digest-uuid'))->render();

    expect($html)->toContain('... y 2 actuaciones más en el portal.');
});
