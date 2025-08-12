<?php

namespace Database\Seeders;

use Core\BoundedContext\Customer\Process\Domain\Repositories\ProcessRepositoryInterface;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;
use Illuminate\Database\Seeder;

class ProcessSeeder extends Seeder
{
    public function __construct(
        private readonly ProcessRepositoryInterface $processRepository
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $process = $this->processRepository->create([
            'process_id' => 1811038834,
            'process_number' => '76001333301720180029100',
            'court' => 'JUZGADO 017 ADMINISTRATIVO DE CALI',
            'department' => 'VALLE DEL CAUCA',
            'process_type' => 'Ordinario',
            'process_class' => 'ACCION DE REPARACION DIRECTA',
            'subclass_process' => 'Sin Subclase de Proceso',
            'litigants' => 'Demandante: CESAR AUJGUSTO NOREÑA ARCILA Y OTROS | Demandado: MUNICIPIO DE SANTIAGO DE CALI | Demandado: EMPRESAS MUNICIPALES DE CALI - EMCALI EICE ESP | Llamado en Garantia: MAPFRE SEGUROS GENERALES DE COLOMBIA S.A | Llamado en Garantia: ASEGURADORA COLSEGUROS S.A. | Llamado en Garantia: SEGUROS COLPATRIA S.A | Llamado en Garantia: COMPAÑIA DE CENTRAL DE SEGUROS S.A | Desvinculado: AGENCIA NACIONAL DE DEFENSA JURIDICA DEL ESTADO | Ministerio Publico: PROCURADURIA 59 JUDICIAL 1 ADMINISTRATIVA',
            'process_date' => '2018-11-22',
            'last_activity_date' => '2025-06-20',
            'location' => 'Despacho',
            'filing_content' => 'ANEXA 3 COPIAS Y 1 CD',
            'is_private' => false,
            'last_api_update' => '2025-08-01T19:17:37.393',
        ]);

        $organizations = Organization::query()->take(3)->get();

        if ($organizations->count() < 3) {

            $organizations = collect();
            for ($i = 1; $i <= 3; $i++) {
                $organizations->push(Organization::create([
                    'name' => "Organización de Prueba {$i}",
                    'type' => 'juridical',
                    'identification' => "12345678{$i}",
                    'address' => "Dirección de prueba {$i}",
                    'phone' => "300123456{$i}",
                    'email' => "org{$i}@test.com",
                    'contact_person' => "Contacto {$i}",
                ]));
            }
        }

        foreach ($organizations as $organization) {
            $this->processRepository->attachOrganization($process->id, $organization->id, [
                'interest_date' => now()->subDays(rand(1, 30)),
                'is_active' => true,
            ]);
        }

        $this->command->info("Proceso creado y asignado a {$organizations->count()} organizaciones.");
    }
}
