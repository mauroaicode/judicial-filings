<?php

declare(strict_types=1);

use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;

it('exposes a non-empty localized label for every case', function (): void {
    foreach (JudicialSyncRunStatus::cases() as $status) {
        $label = $status->getLabel();

        expect($label)->not->toBe('')
            ->and($label)->not->toBe('enums.judicial_sync_run_status.'.$status->value);
    }
});
