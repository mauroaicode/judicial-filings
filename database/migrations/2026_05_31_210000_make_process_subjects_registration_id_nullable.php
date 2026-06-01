<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('process_subjects')
            ->where('subject_registration_id', '<', 0)
            ->update(['subject_registration_id' => null]);

        Schema::table('process_subjects', function (Blueprint $table): void {
            $table->dropUnique(['subject_registration_id']);
        });

        Schema::table('process_subjects', function (Blueprint $table): void {
            $table->bigInteger('subject_registration_id')->nullable()->change();
            $table->unique('subject_registration_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_subjects', function (Blueprint $table): void {
            $table->dropUnique(['subject_registration_id']);
        });

        Schema::table('process_subjects', function (Blueprint $table): void {
            $table->bigInteger('subject_registration_id')->nullable(false)->change();
            $table->unique('subject_registration_id');
        });
    }
};
