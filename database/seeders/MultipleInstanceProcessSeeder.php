<?php

namespace Database\Seeders;

// use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotification;
// use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;
// use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;
// use Core\Shared\Infrastructure\Persistence\Eloquent\Models\ProcessAction;
// use Core\Shared\Infrastructure\Persistence\Eloquent\Models\ProcessSubject;
use Illuminate\Database\Seeder;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessSubject;

class MultipleInstanceProcessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "Creando proceso con múltiples instancias...\n";

        // Crear el proceso principal con múltiples instancias
        $mainProcess = Process::create([
            'process_id' => 1811521814,
            'process_number' => '76001333301320170009301',
            'court' => 'JUZGADO 007 ADMINISTRATIVO DE CALI',
            'department' => 'VALLE DEL CAUCA',
            'process_type' => 'ADMINISTRATIVO',
            'process_class' => 'ADMINISTRATIVO',
            'subclass_process' => 'RESPONSABILIDAD',
            'litigants' => 'Demandante: ROSALBA BENAVIDEZ | Demandante: NANCY ESPERANZA ANACONA | Demandante: GLORIA STELLA BENAVIDEZ Y OTROS | Demandante: DAVID ALEXANDER BENAVIDEZ VILLA | Demandado: EMCALI E.I.C.E. E.S.P. | Demandado: MUNICIPIO DE SANTIAGO DE CALI Y OTROS',
            'process_date' => '2017-04-06',
            'last_activity_date' => '2025-07-23',
            'location' => 'CALI',
            'filing_content' => 'Proceso administrativo por responsabilidad extracontractual',
            'is_private' => false,
            'has_multiple_instances' => true,
            'last_api_update' => now(),
        ]);

        echo "Proceso principal creado: {$mainProcess->process_number}\n";

        // Crear la segunda instancia del proceso
        $secondInstance = Process::create([
            'process_id' => 1834511724,
            'process_number' => '76001333301320170009301', // Mismo número de proceso
            'court' => 'DESPACHO 000 - TRIBUNAL ADMINISTRATIVO - SIN SECCIÓN - ORAL - CALI',
            'department' => 'VALLE DEL CAUCA',
            'process_type' => 'ADMINISTRATIVO',
            'process_class' => 'ADMINISTRATIVO',
            'subclass_process' => 'RESPONSABILIDAD',
            'litigants' => 'Demandante: FRANCISCO FERLY RAMIREZ BENAVIDES | Demandante: BERTHA CECILIA BENAVIDES | Demandante: ROSALBA BENAVIDEZ | Demandante: NANCY ESPERANZA ANACONA | Demandante: GLORIA STELLA BENAVIDEZ Y OTROS',
            'process_date' => '2024-12-06',
            'last_activity_date' => '2025-04-30',
            'location' => 'CALI',
            'filing_content' => 'Segunda instancia del proceso administrativo',
            'is_private' => false,
            'has_multiple_instances' => true,
            'last_api_update' => now(),
        ]);

        echo "Segunda instancia creada: {$secondInstance->process_number}\n";

        // Obtener organizaciones para asignar
        $organizations = Organization::take(3)->get();

        // Asignar procesos a organizaciones
        foreach ([$mainProcess, $secondInstance] as $process) {
            foreach ($organizations as $organization) {
                $process->organizations()->attach($organization->id, [
                    'interest_date' => now(),
                    'is_active' => true,
                ]);
            }
        }

        echo "Procesos asignados a organizaciones\n";

        // Crear sujetos procesales para el proceso principal
        $this->createProcessSubjects($mainProcess, 'main');

        // Crear sujetos procesales para la segunda instancia
        $this->createProcessSubjects($secondInstance, 'second');

        // Crear actuaciones para ambos procesos
        $this->createProcessActions($mainProcess, 'main');
        $this->createProcessActions($secondInstance, 'second');

        echo "Proceso con múltiples instancias creado exitosamente.\n";
    }

    private function createProcessSubjects(Process $process, string $instance): void
    {
        if ($instance === 'main') {
            // Sujetos del proceso principal
            $subjects = [
                ['type' => 'Demandante', 'name' => 'ROSALBA BENAVIDEZ', 'identification' => '1234567890'],
                ['type' => 'Demandante', 'name' => 'NANCY ESPERANZA ANACONA', 'identification' => '0987654321'],
                ['type' => 'Demandante', 'name' => 'GLORIA STELLA BENAVIDEZ Y OTROS', 'identification' => '1122334455'],
                ['type' => 'Demandante', 'name' => 'DAVID ALEXANDER BENAVIDEZ VILLA', 'identification' => '5566778899'],
                ['type' => 'Demandado', 'name' => 'EMCALI E.I.C.E. E.S.P.', 'identification' => '9001234567'],
                ['type' => 'Demandado', 'name' => 'MUNICIPIO DE SANTIAGO DE CALI Y OTROS', 'identification' => null],
            ];
        } else {
            // Sujetos de la segunda instancia
            $subjects = [
                ['type' => 'Demandante', 'name' => 'FRANCISCO FERLY RAMIREZ BENAVIDES', 'identification' => '1111111111'],
                ['type' => 'Demandante', 'name' => 'BERTHA CECILIA BENAVIDES', 'identification' => '2222222222'],
                ['type' => 'Demandante', 'name' => 'ROSALBA BENAVIDEZ', 'identification' => '1234567890'],
                ['type' => 'Demandante', 'name' => 'NANCY ESPERANZA ANACONA', 'identification' => '0987654321'],
                ['type' => 'Demandante', 'name' => 'GLORIA STELLA BENAVIDEZ Y OTROS', 'identification' => '1122334455'],
                ['type' => 'Demandante', 'name' => 'HECTOR MARINO BENAVIDES', 'identification' => '3333333333'],
                ['type' => 'Demandado', 'name' => 'EMPRESAS MUNICIPALES DE CALI EMCALI EICE ESP', 'identification' => '9001234567'],
                ['type' => 'Demandado', 'name' => 'DISTRITO ESPECIAL DE SANTIAGO DE CALI', 'identification' => null],
            ];
        }

        foreach ($subjects as $subject) {
            ProcessSubject::create([
                'process_id' => $process->id,
                'subject_registration_id' => fake()->unique()->numberBetween(1000000, 9999999),
                'subject_type' => $subject['type'],
                'is_cited' => false,
                'identification' => $subject['identification'],
                'name_or_business_name' => $subject['name'],
            ]);
        }

        echo "Sujetos procesales creados para instancia {$instance}\n";
    }

    private function createProcessActions(Process $process, string $instance): void
    {
        if ($instance === 'main') {
            // Actuaciones del proceso principal
            $actions = [
                [
                    'action' => 'Recepción memorial al despacho',
                    'annotation' => 'MOS-C25-28072 - Renuncia de poder - ABDÓN MAURICIO ROJAS MARROQUÍN - Apoderado EMCALI E.I.C.E. E.S.P.',
                    'action_date' => '2025-06-20',
                    'registration_date' => '2025-06-20',
                ],
                [
                    'action' => 'A despacho',
                    'annotation' => 'MTV-para sentencia',
                    'action_date' => '2025-05-14',
                    'registration_date' => '2025-05-14',
                ],
                [
                    'action' => 'RECIBE MEMORIALES ONLINE AL DESPACHO',
                    'annotation' => 'El Señor(a):CAROLINA OCAMPO FRANCO a través de la ventanilla virtual, radicó la solicitud No. 1651763 tipo: Recepción de Memoriales de fecha: 05/05/2025 15:52:20. Se realiza la siguiente gestión: ALEGATOS DE CONCLUSION CAROLINA OCAMPO FRANCO DISTRITO ESPECIAL DE SANTIAGO DE CALI',
                    'action_date' => '2025-05-05',
                    'registration_date' => '2025-05-06',
                ],
            ];
        } else {
            // Actuaciones de la segunda instancia
            $actions = [
                [
                    'action' => 'Apelación interpuesta',
                    'annotation' => 'Se interpone recurso de apelación contra la sentencia de primera instancia',
                    'action_date' => '2025-04-30',
                    'registration_date' => '2025-04-30',
                ],
                [
                    'action' => 'Admisión de apelación',
                    'annotation' => 'Se admite el recurso de apelación y se remite al tribunal superior',
                    'action_date' => '2025-04-25',
                    'registration_date' => '2025-04-25',
                ],
                [
                    'action' => 'Notificación a partes',
                    'annotation' => 'Se notifica a todas las partes sobre la admisión del recurso',
                    'action_date' => '2025-04-20',
                    'registration_date' => '2025-04-20',
                ],
            ];
        }

        foreach ($actions as $action) {
            $processAction = ProcessAction::create([
                'process_id' => $process->id,
                'action_registration_id' => fake()->unique()->numberBetween(1000000, 9999999),
                'action_date' => $action['action_date'],
                'action' => $action['action'],
                'annotation' => $action['annotation'],
                'registration_date' => $action['registration_date'],
            ]);

            //            // Asignar actuación a organizaciones
            //            $organizations = Organization::take(2)->get();
            //            foreach ($organizations as $organization) {
            //                OrganizationNotification::create([
            //                    'organization_id' => $organization->id,
            //                    'notifiable_id' => $processAction->id,
            //                    'notifiable_type' => ProcessAction::class,
            //                    'notification_type' => 'new_action',
            //                    'is_viewed' => fake()->boolean(80),
            //                    'is_notified' => fake()->boolean(60),
            //                    'viewed_at' => fake()->optional(0.8)->dateTimeBetween('-1 month', 'now'),
            //                    'notified_at' => fake()->optional(0.6)->dateTimeBetween('-1 month', 'now'),
            //                ]);
            //            }
        }

        echo "Actuaciones creadas para instancia {$instance}\n";
    }
}
