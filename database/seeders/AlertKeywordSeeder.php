<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Domain\Process\Models\AlertActionKeyword;

class AlertKeywordSeeder extends Seeder
{
    /**
     * Keywords used for filtering alerts (actuacion_alerta). Slug is used as query param.
     * Table: alert_actions_keywords.
     *
     * @var array<int, array{name: string, slug: string}>
     */
    private const KEYWORDS = [
        ['name' => 'Consulta', 'slug' => 'consulta'],
        ['name' => 'Apelación', 'slug' => 'apelacion'],
        ['name' => 'Sentencia', 'slug' => 'sentencia'],
        ['name' => 'Rechaza', 'slug' => 'rechaza'],
        ['name' => 'Fijación Estado', 'slug' => 'fijacion_estado'],
        ['name' => 'Traslado', 'slug' => 'traslado'],
    ];

    public function run(): void
    {
        foreach (self::KEYWORDS as $item) {
            AlertActionKeyword::query()->firstOrCreate(
                ['slug' => $item['slug']],
                ['name' => $item['name']]
            );
        }
    }
}
