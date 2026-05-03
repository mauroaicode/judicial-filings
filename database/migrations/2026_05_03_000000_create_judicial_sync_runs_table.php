<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('judicial_sync_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamp('started_at');
            $table->timestamp('command_finished_at')->nullable();
            $table->timestamp('batch_finished_at')->nullable();
            $table->string('trigger', 20);
            $table->string('radicado_filter', 23)->nullable();
            $table->unsignedInteger('processes_queued')->default(0);
            $table->string('laravel_batch_id')->nullable()->index();
            $table->string('status', 40);
            $table->smallInteger('command_exit_code')->nullable();
            $table->text('dispatch_error')->nullable();
            $table->unsignedInteger('failed_jobs_count')->nullable();
            $table->timestamps();

            $table->index('started_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judicial_sync_runs');
    }
};
