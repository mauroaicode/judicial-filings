<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repository of actuaciones imported from manual Excel (Publicaciones Procesales)
 * whose radicado did not yet exist as a Process. When a Process is later created
 * for that radicado, these rows are migrated onto process_actions (retroactivity).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unassigned_process_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('process_number', 23)->index();
            $table->string('court')->nullable();
            $table->string('process_class')->nullable();
            $table->text('plaintiffs_raw')->nullable();
            $table->text('defendants_raw')->nullable();
            $table->string('action');
            $table->text('annotation')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('registration_date')->nullable()->index();
            $table->string('dedupe_hash', 64);
            $table->uuid('import_batch_id')->nullable()->index();
            $table->uuid('imported_by')->nullable();
            $table->uuid('assigned_process_id')->nullable()->index();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['process_number', 'dedupe_hash'],
                'unassigned_process_actions_radicado_dedupe_unique'
            );

            $table->foreign('import_batch_id')
                ->references('id')
                ->on('process_import_batches')
                ->nullOnDelete();

            $table->foreign('assigned_process_id')
                ->references('id')
                ->on('processes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unassigned_process_actions');
    }
};
