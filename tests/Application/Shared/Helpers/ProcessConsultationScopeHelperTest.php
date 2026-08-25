<?php

declare(strict_types=1);

use Src\Application\Shared\Helpers\ProcessConsultationScopeHelper;

it('sends only administrative and consejo de estado radicados to SAMAI', function (): void {
    expect(ProcessConsultationScopeHelper::shouldConsultSamai('76001333301320160005700'))->toBeTrue()
        ->and(ProcessConsultationScopeHelper::shouldConsultSamai('76001233300020190002000'))->toBeTrue()
        ->and(ProcessConsultationScopeHelper::shouldConsultSamai('11001031500020240000100'))->toBeTrue()
        ->and(ProcessConsultationScopeHelper::shouldConsultSamai('76520310500320260013300'))->toBeFalse()
        ->and(ProcessConsultationScopeHelper::shouldConsultSamai('08001400301020230078300'))->toBeFalse();
});

it('uses despacho text when the court name is already known', function (): void {
    expect(ProcessConsultationScopeHelper::shouldConsultSamai(
        '08001400301020230078300',
        'Juzgado 007 Administrativo de Cali',
    ))->toBeTrue()
        ->and(ProcessConsultationScopeHelper::shouldConsultSamai(
            '08001400301020230078300',
            'Consejo de Estado - Sección Segunda',
        ))->toBeTrue()
        ->and(ProcessConsultationScopeHelper::shouldConsultSamai(
            '08001400301020230078300',
            'Juzgado 003 Civil Municipal de Cali',
        ))->toBeFalse();
});

it('detects laborales by radicado and court name', function (): void {
    expect(ProcessConsultationScopeHelper::isLaboral('76520310500320260013300'))->toBeTrue()
        ->and(ProcessConsultationScopeHelper::isLaboral('76001410500620240046200'))->toBeTrue()
        ->and(ProcessConsultationScopeHelper::isLaboral(
            '08001400301020230078300',
            'Juzgado 020 Laboral de Cali',
        ))->toBeTrue()
        ->and(ProcessConsultationScopeHelper::isLaboral('76001333301320160005700'))->toBeFalse();
});
