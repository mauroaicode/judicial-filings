<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasPrimaryKey = collect(DB::select("SHOW INDEX FROM users WHERE Key_name = 'PRIMARY'"))->isNotEmpty();

        if ($hasPrimaryKey) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->primary('id');
        });
    }

    public function down(): void
    {
        $hasPrimaryKey = collect(DB::select("SHOW INDEX FROM users WHERE Key_name = 'PRIMARY'"))->isNotEmpty();

        if (! $hasPrimaryKey) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropPrimary(['id']);
        });
    }
};
