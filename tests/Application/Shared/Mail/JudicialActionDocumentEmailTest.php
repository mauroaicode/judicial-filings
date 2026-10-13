<?php

declare(strict_types=1);

use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Mail\JudicialActionDetectedMailable;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

it('builds document email deep link and formats dates with day of week', function (): void {
    config(['app.frontend_url' => 'https://app.example.com']);

    $process = Process::factory()->create([
        'process_number' => '76001418900520240095800',
    ]);
    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Fijacion Estado',
        'action_date' => '2026-08-06',
        'start_date' => '2026-08-06',
        'end_date' => '2026-08-06',
    ]);

    $mailable = new JudicialActionDetectedMailable(
        action: $action,
        process: $process,
        organizationId: (string) \Illuminate\Support\Str::uuid(),
        notificationType: 'actuacion_documento',
        mediaId: 13,
        originalFilename: 'edicto.pdf',
    );

    $mailable->assertSeeInHtml(DateFormatHelper::formatDateWithDayOfWeek('2026-08-06'));
    $mailable->assertSeeInHtml('edicto.pdf');
    $mailable->assertSeeInHtml('Ver documento');
    $mailable->assertSeeInHtml(
        'https://app.example.com/gestion-procesos/'.$process->id
        .'?section=documents&mediaId=13&filename=edicto.pdf&actionId='.$action->id
    );
});
