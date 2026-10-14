<?php

declare(strict_types=1);

use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Mail\JudicialActionDetectedMailable;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessSubject;

it('builds document email deep link with parties and full action without filename', function (): void {
    config(['app.frontend_url' => 'https://app.example.com']);

    $process = Process::factory()->create([
        'process_number' => '76001418900520240095800',
    ]);

    ProcessSubject::factory()->forProcess($process)->plaintiff()->create([
        'name_or_business_name' => 'BANCO DE BOGOTA',
    ]);
    ProcessSubject::factory()->forProcess($process)->defendant()->create([
        'name_or_business_name' => 'JUAN PEREZ',
    ]);

    $action = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Fijacion Estado con texto completo sin truncar en el correo de documento',
        'annotation' => 'Anotacion completa del documento',
        'action_date' => '2026-08-06',
        'start_date' => '2026-08-06',
        'end_date' => '2026-08-06',
        'cons_action' => 10,
    ]);

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Auto requiere presentacion de documentos',
        'action_date' => '2026-08-06',
        'registration_date' => $action->registration_date,
        'cons_action' => 11,
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
    $mailable->assertSeeInHtml('Número de Radicación');
    $mailable->assertSeeInHtml('Demandante(s)');
    $mailable->assertSeeInHtml('Demandado(s)');
    $mailable->assertSeeInHtml('Banco de Bogota');
    $mailable->assertSeeInHtml('Juan Perez');
    $mailable->assertSeeInHtml('Fijacion Estado con texto completo sin truncar en el correo de documento');
    $mailable->assertSeeInHtml('Auto requiere presentacion de documentos');
    $mailable->assertDontSeeInHtml('Archivo:');
    $mailable->assertSeeInHtml('Ver documento');
    $mailable->assertSeeInHtml(
        'https://app.example.com/gestion-procesos/'.$process->id
        .'?section=documents&mediaId=13&filename=edicto.pdf&actionId='.$action->id
    );
});
