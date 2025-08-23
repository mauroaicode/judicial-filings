<?php

namespace Database\Seeders;

use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotification;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\ProcessAction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProcessActionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener el proceso existente
        $process = Process::first();

        if (!$process) {
            $this->command->info('No se encontró ningún proceso. Creando uno...');
            $process = Process::factory()->create();
        }

        // Datos de actuaciones reales basados en el ejemplo proporcionado
        $actions = [
            [
                'action_registration_id' => 1229284324,
                'action_date' => '2025-06-20',
                'action' => 'Recepción memorial oa al despacho',
                'annotation' => 'MOS-C25-28072 - Renuncia de poder - ABDÓN MAURICIO ROJAS MARROQUÍN - Apoderado EMCALI E.I.C.E. E.S.P.',
                'start_date' => null,
                'end_date' => null,
                'registration_date' => '2025-06-20',
            ],
            [
                'action_registration_id' => 1224098984,
                'action_date' => '2025-05-14',
                'action' => 'A despacho',
                'annotation' => 'MTV-para sentencia',
                'start_date' => null,
                'end_date' => null,
                'registration_date' => '2025-05-14',
            ],
            [
                'action_registration_id' => 1222890564,
                'action_date' => '2025-05-05',
                'action' => 'RECIBE MEMORIALES ONLINE AL DESPACHO',
                'annotation' => 'El Señor(a):CAROLINA OCAMPO FRANCO a través de la ventanilla virtual, radicó la solicitud No. 1651763 tipo: Recepción de Memoriales de fecha: 05/05/2025 15:52:20. Se realiza la siguiente gestión: ALEGATOS DE CONCLUSION CAROLINA OCAMPO FRANCO DISTRITO ESPECIAL DE SANTIAGO DE CALI',
                'start_date' => null,
                'end_date' => null,
                'registration_date' => '2025-05-06',
            ],
            [
                'action_registration_id' => 1221500000,
                'action_date' => '2025-04-15',
                'action' => 'Citación a audiencia',
                'annotation' => 'Se cita a las partes para audiencia de conciliación el día 20 de abril de 2025 a las 9:00 AM',
                'start_date' => '2025-04-20',
                'end_date' => '2025-04-20',
                'registration_date' => '2025-04-15',
            ],
            [
                'action_registration_id' => 1220000000,
                'action_date' => '2025-03-10',
                'action' => 'Auto de trámite',
                'annotation' => 'Se admite la demanda y se ordena la práctica de pruebas por el término de 10 días',
                'start_date' => '2025-03-10',
                'end_date' => '2025-03-20',
                'registration_date' => '2025-03-10',
            ],
        ];

        // Crear las actuaciones
        foreach ($actions as $actionData) {
            $action = ProcessAction::create([
                'id' => Str::uuid(),
                'process_id' => $process->id,
                ...$actionData,
            ]);

            // Asignar la actuación a las primeras 3 organizaciones usando el nuevo sistema
            $organizations = Organization::take(3)->get();
            foreach ($organizations as $organization) {
                OrganizationNotification::create([
                    'organization_id' => $organization->id,
                    'notifiable_id' => $action->id,
                    'notifiable_type' => ProcessAction::class,
                    'notification_type' => 'new_action',
                    'is_viewed' => fake()->boolean(70), // 70% probabilidad de estar vista
                    'is_notified' => fake()->boolean(80), // 80% probabilidad de estar notificada
                    'viewed_at' => fake()->optional(0.7)->dateTimeBetween('-1 month', 'now'),
                    'notified_at' => fake()->optional(0.8)->dateTimeBetween('-1 month', 'now'),
                ]);
            }
        }

        // Crear algunas actuaciones adicionales usando el factory
        $factoryActions = ProcessAction::factory(5)->create([
            'process_id' => $process->id,
        ]);

        foreach ($factoryActions as $action) {
            // Asignar a organizaciones aleatorias
            $organizations = Organization::inRandomOrder()->take(rand(1, 3))->get();
            foreach ($organizations as $organization) {
                OrganizationNotification::create([
                    'organization_id' => $organization->id,
                    'notifiable_id' => $action->id,
                    'notifiable_type' => ProcessAction::class,
                    'notification_type' => 'new_action',
                    'is_viewed' => fake()->boolean(60),
                    'is_notified' => fake()->boolean(90),
                    'viewed_at' => fake()->optional(0.6)->dateTimeBetween('-1 month', 'now'),
                    'notified_at' => fake()->optional(0.9)->dateTimeBetween('-1 month', 'now'),
                ]);
            }
        }

        $this->command->info('Actuaciones creadas y asignadas a organizaciones exitosamente usando el nuevo sistema de notificaciones.');
    }
}
