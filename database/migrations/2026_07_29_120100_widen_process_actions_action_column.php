<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * process_actions.action was VARCHAR(255); Excel titles from small courts often exceed that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_actions', function (Blueprint $table): void {
            $table->text('action')->change();
        });
    }

    public function down(): void
    {
        Schema::table('process_actions', function (Blueprint $table): void {
            $table->string('action')->change();
        });
    }
};
