<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;

class ProcessSubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "Creando sujetos procesales para cada proceso...\n";

        // Get all existing processes
        $processes = Process::all();
        echo 'Procesos encontrados: '.$processes->count()."\n";

        foreach ($processes as $process) {
            echo 'Procesando proceso: '.$process->process_number."\n";

            $plaintiff = ProcessSubject::create([
                'subject_registration_id' => fake()->unique()->numberBetween(1000000, 9999999),
                'subject_type' => 'Demandante',
                'is_cited' => false,
                'identification' => fake()->numerify('##########'),
                'name_or_business_name' => fake()->name().' '.fake()->lastName().' Y OTROS',
            ]);

            $defendant = ProcessSubject::create([
                'subject_registration_id' => fake()->unique()->numberBetween(1000000, 9999999),
                'subject_type' => 'Demandado',
                'is_cited' => false,
                'identification' => fake()->numerify('##########'),
                'name_or_business_name' => fake()->company().' - '.fake()->companySuffix(),
            ]);

            $process->subjects()->attach([$plaintiff->id, $defendant->id]);
            echo "  - Demandante y Demandado creados\n";
        }

        echo "Sujetos procesales creados exitosamente.\n";
        echo 'Total de sujetos creados: '.ProcessSubject::count()."\n";
    }
}
