<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_directory_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('name', 500);
            $table->string('corporation')->nullable();
            $table->string('specialty')->nullable();
            $table->string('department')->nullable();
            $table->string('city')->nullable();
            $table->string('account_type')->nullable();
            $table->char('desk_code', 12)->nullable()->index();
            $table->boolean('is_active');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('missing_at')->nullable();
            $table->foreignUuid('last_sync_run_id')
                ->nullable()
                ->constrained('court_directory_sync_runs')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_directory_entries');
    }
};
