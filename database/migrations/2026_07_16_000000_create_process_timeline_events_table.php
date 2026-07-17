<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_timeline_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('process_id');
            $table->string('process_number', 23);
            $table->uuid('organization_id')->nullable();
            $table->string('event_type');
            $table->timestamp('occurred_at');
            $table->timestamp('recorded_at');
            $table->string('source');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('actor_type')->nullable();
            $table->uuid('actor_id')->nullable();
            $table->json('payload');
            $table->string('idempotency_key', 191)->unique();
            $table->boolean('is_backfilled')->default(false);
            $table->boolean('occurred_at_is_estimated')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->foreign('process_id')->references('id')->on('processes')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();

            $table->index(['process_id', 'occurred_at']);
            $table->index(['organization_id', 'process_id', 'occurred_at'], 'timeline_org_process_occurred_idx');
            $table->index(['process_number', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_timeline_events');
    }
};
