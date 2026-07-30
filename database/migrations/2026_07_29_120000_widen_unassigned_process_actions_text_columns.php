<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual Excel actuaciones often exceed VARCHAR(255) (full auto/state titles).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unassigned_process_actions', function (Blueprint $table): void {
            $table->text('action')->change();
            $table->text('court')->nullable()->change();
            $table->text('process_class')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('unassigned_process_actions', function (Blueprint $table): void {
            $table->string('action')->change();
            $table->string('court')->nullable()->change();
            $table->string('process_class')->nullable()->change();
        });
    }
};
