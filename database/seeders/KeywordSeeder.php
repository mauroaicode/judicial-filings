<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Domain\Keyword\Models\Keyword;
use Src\Domain\Organization\Models\Organization;

class KeywordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = Organization::all();

        if ($organizations->isEmpty()) {
            $organizations = collect([Organization::factory()->create()]);
        }

        $keywords = [
            'Apelación',
            'Consulta',
            'Translado',
            'Estado',
            'Notificación Estado',
            'Sentencia',
            'Auto',
            'Audiencia',
        ];

        foreach ($organizations as $organization) {
            foreach ($keywords as $keyword) {
                Keyword::factory()->create([
                    'organization_id' => $organization->id,
                    'name' => "Alerta de {$keyword}",
                    'keyword' => $keyword,
                    'status' => 'active',
                ]);
            }

            // Create some inactive ones
            Keyword::factory()->count(2)->create([
                'organization_id' => $organization->id,
                'status' => 'inactive',
            ]);
        }
    }
}
