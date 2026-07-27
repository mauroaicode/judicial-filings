<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manual Excel source for small-court / procedural publications (is_manual_sync processes).
 */
return new class extends Migration
{
    private const ID = 'a0000001-0000-4000-8000-000000000003';

    private const SLUG = 'publicaciones_procesales';

    public function up(): void
    {
        if (! Schema::hasTable('process_data_sources')) {
            return;
        }

        $exists = DB::table('process_data_sources')->where('slug', self::SLUG)->exists();
        if ($exists) {
            return;
        }

        $now = now();
        DB::table('process_data_sources')->insert([
            'id' => self::ID,
            'slug' => self::SLUG,
            'name' => 'Publicaciones Procesales',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('process_data_sources')) {
            return;
        }

        DB::table('process_data_sources')->where('slug', self::SLUG)->delete();
    }
};
