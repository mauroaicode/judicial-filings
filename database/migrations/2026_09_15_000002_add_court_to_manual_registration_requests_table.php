<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_registration_requests', function (Blueprint $table) {
            $table->string('court')->nullable()->after('process_class');
        });
    }

    public function down(): void
    {
        Schema::table('manual_registration_requests', function (Blueprint $table) {
            $table->dropColumn('court');
        });
    }
};
