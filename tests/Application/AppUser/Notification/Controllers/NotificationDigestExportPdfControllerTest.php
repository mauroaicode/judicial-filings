<?php

declare(strict_types=1);

use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Organization\Models\Organization;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create(['name' => 'Bufete Export PDF']);
    $this->appUser = AppUser::factory()->create();
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);

    $this->digest = NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'email_sent_at' => now(),
        'created_at' => now()->setTime(13, 0),
        'data' => [
            [
                'court' => 'Juzgado 002 de Pequeñas Causas y Competencia Múltiple de Cali',
                'process_number' => '76001418900220250039700',
                'demandante' => 'Banco Av Villas, Seguros Del Estado SA, Fondo Especial',
                'demandado' => 'Alexandra Ortiz Reyes, Empresa Uno SAS, Empresa Dos SAS',
                'action_date' => '13/10/2026',
                'registration_date' => '13/10/2026',
                'action_text' => 'Fijación Estado',
                'annotation' => 'Decisión automática',
                'term_start_date' => null,
                'term_end_date' => null,
                'is_alert' => false,
                'is_merged' => true,
                'linked_action_text' => 'Auto Ordena Seguir Adelante la Ejecución',
                'linked_annotation' => 'Auto requiere',
            ],
            [
                'court' => 'Juzgado 001 Civil Municipal de Palmira',
                'process_number' => '76520418900220190076300',
                'demandante' => 'Seguros Comerciales Bolivar SA',
                'demandado' => 'Heber Panza Camelo',
                'action_date' => '13/10/2026',
                'registration_date' => '13/10/2026',
                'action_text' => 'Traslado Liquidación Crédito',
                'annotation' => '---',
                'term_start_date' => '14/10/2026',
                'term_end_date' => '20/10/2026',
                'is_alert' => true,
                'matched_keywords' => 'liquidación',
            ],
        ],
    ]);
});

it('requires authentication to export consolidado pdf', function (): void {
    get("/api/app-user/notification-digests/{$this->digest->id}/export-pdf")
        ->assertStatus(401);
});

it('returns 404 when consolidado belongs to another organization', function (): void {
    $otherOrg = Organization::factory()->create();
    $foreignDigest = NotificationDigest::factory()->create([
        'organization_id' => $otherOrg->id,
    ]);

    actingAs($this->appUser, 'sanctum')
        ->get("/api/app-user/notification-digests/{$foreignDigest->id}/export-pdf")
        ->assertStatus(404);
});

it('returns 404 when consolidado does not exist', function (): void {
    actingAs($this->appUser, 'sanctum')
        ->get('/api/app-user/notification-digests/'.fake()->uuid().'/export-pdf')
        ->assertStatus(404);
});

it('downloads a pdf for an existing consolidado of the user organization', function (): void {
    $response = actingAs($this->appUser, 'sanctum')
        ->get("/api/app-user/notification-digests/{$this->digest->id}/export-pdf");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $disposition = (string) $response->headers->get('content-disposition');
    expect($disposition)->toContain('attachment')
        ->and($disposition)->toContain('consolidado-bufete-export-pdf')
        ->and($disposition)->toContain('.pdf');

    $content = $response->getContent();
    expect($content)->toBeString()
        ->and($content)->toStartWith('%PDF');
});
