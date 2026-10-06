<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Src\Application\Admin\Process\Jobs\GenerateOrganizationProcessExportJob;

it('builds export file names with organization slug and generation datetime', function (): void {
    $exportedAt = Carbon::parse('2026-10-08 14:35:22');

    expect(GenerateOrganizationProcessExportJob::buildFileName('CH Abogados & Consultores', $exportedAt))
        ->toBe('procesos-ch-abogados-consultores-2026-10-08_143522.xlsx');
});

it('builds file names with actuaciones prefix when actions are included', function (): void {
    $exportedAt = Carbon::parse('2026-10-08 14:35:22');

    expect(GenerateOrganizationProcessExportJob::buildFileName('Org Demo', $exportedAt, true))
        ->toBe('procesos-y-actuaciones-org-demo-2026-10-08_143522.xlsx');
});

it('falls back when organization name is empty', function (): void {
    $exportedAt = Carbon::parse('2026-10-08 09:00:00');

    expect(GenerateOrganizationProcessExportJob::buildFileName('   ', $exportedAt))
        ->toBe('procesos-organizacion-2026-10-08_090000.xlsx');
});
