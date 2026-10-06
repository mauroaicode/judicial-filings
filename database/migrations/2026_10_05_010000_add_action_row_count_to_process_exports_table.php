<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_exports', function (Blueprint $table) {
            $table->unsignedInteger('action_row_count')->default(0)->after('row_count');
        });
    }

    public function down(): void
    {
        Schema::table('process_exports', function (Blueprint $table) {
            $table->dropColumn('action_row_count');
        });
    }
};
