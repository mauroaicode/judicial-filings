<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('process_actions', 'removed_from_judicial_branch_at')) {
            Schema::table('process_actions', function (Blueprint $table): void {
                $table->timestamp('removed_from_judicial_branch_at')
                    ->nullable()
                    ->after('registration_date')
                    ->comment('Marcada por admin cuando el juzgado retira la actuación en Rama Judicial');

                $table->uuid('removed_from_judicial_branch_by')
                    ->nullable()
                    ->after('removed_from_judicial_branch_at');

                $table->text('removed_from_judicial_branch_reason')
                    ->nullable()
                    ->after('removed_from_judicial_branch_by');
            });
        }

        $hasRemovedAtIndex = collect(DB::select(
            "SHOW INDEX FROM process_actions WHERE Key_name = 'process_actions_removed_from_rama_index'"
        ))->isNotEmpty();

        if (! $hasRemovedAtIndex) {
            Schema::table('process_actions', function (Blueprint $table): void {
                $table->index('removed_from_judicial_branch_at', 'process_actions_removed_from_rama_index');
            });
        }

        $hasRemovedByForeign = collect(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_TYPE = ?
               AND CONSTRAINT_NAME = ?',
            ['process_actions', 'FOREIGN KEY', 'process_actions_removed_by_foreign']
        ))->isNotEmpty();

        if (! $hasRemovedByForeign) {
            Schema::table('process_actions', function (Blueprint $table): void {
                $table->foreign('removed_from_judicial_branch_by', 'process_actions_removed_by_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('process_actions', function (Blueprint $table): void {
            if ($this->foreignKeyExists('process_actions_removed_by_foreign')) {
                $table->dropForeign('process_actions_removed_by_foreign');
            }

            if ($this->indexExists('process_actions_removed_from_rama_index')) {
                $table->dropIndex('process_actions_removed_from_rama_index');
            }

            if (Schema::hasColumn('process_actions', 'removed_from_judicial_branch_at')) {
                $table->dropColumn([
                    'removed_from_judicial_branch_at',
                    'removed_from_judicial_branch_by',
                    'removed_from_judicial_branch_reason',
                ]);
            }
        });
    }

    private function foreignKeyExists(string $name): bool
    {
        return collect(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_TYPE = ?
               AND CONSTRAINT_NAME = ?',
            ['process_actions', 'FOREIGN KEY', $name]
        ))->isNotEmpty();
    }

    private function indexExists(string $name): bool
    {
        return collect(DB::select(
            'SHOW INDEX FROM process_actions WHERE Key_name = ?',
            [$name]
        ))->isNotEmpty();
    }
};
