<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;

class ProcessSeeder extends Seeder
{
    public function __construct(

    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $process = Process::create([
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
            $process->organizations()->attach($organization->id, [
                'interest_date' => now()->subDays(random_int(1, 30)),
                'is_active' => true,
            ]);
        }

        $this->command->info("Proceso creado y asignado a {$organizations->count()} organizaciones.");

        // Proceso de prueba para organización de juan.perez@example.com (sin actuaciones ni sujetos)
        $organizationJuanPerez = Organization::query()->where('email', 'juan.perez@example.com')->firstOrFail();

        $processJuanPerez = Process::create([
            'process_id' => 149513233,
            'process_number' => '11001020500020250094900',
            'court' => 'DESPACHO 000 - CORTE SUPREMA DE JUSTICIA - LABORAL - BOGOTÁ *',
            'department' => 'BOGOTÁ',
            'process_type' => 'Laboral',
            'process_class' => 'Acción Constitucional',
            'subclass_process' => 'Tutela 1ra Instancia',
            'litigants' => 'Demandante: FRANCISCO FERLY RAMIREZ BENAVIDES | Demandante: BERTHA CECILIA BENAVIDES | Demandante: ROSALBA BENAVIDEZ | Demandante: NANCY ESPERANZA ANACONA | Demandante: GLORIA STELLA BENAVIDEZ Y OTROS',
            'process_date' => '2024-05-08',
            'last_activity_date' => '2025-04-30',
            'location' => 'Corte Constitucional',
            'filing_content' => 'Generación de Tutela en línea No 2813332',
            'is_private' => false,
            'has_multiple_instances' => false,
            'last_api_update' => '2026-02-03 16:17:01',
        ]);

        $processJuanPerez->organizations()->attach($organizationJuanPerez->id, [
            'interest_date' => now()->subDays(7),
            'is_active' => true,
        ]);

        $this->command->info('Proceso de prueba (11001020500020250094900) creado y asignado a organización de juan.perez@example.com.');
    }
}
