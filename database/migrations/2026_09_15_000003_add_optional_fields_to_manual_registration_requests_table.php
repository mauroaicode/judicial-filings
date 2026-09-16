<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_registration_requests', function (Blueprint $table) {
            $table->string('speaker')->nullable()->after('court');
            $table->string('subclass_process')->nullable()->after('speaker');
            $table->string('location')->nullable()->after('subclass_process');
        });
    }

    public function down(): void
    {
        Schema::table('manual_registration_requests', function (Blueprint $table) {
            $table->dropColumn(['speaker', 'subclass_process', 'location']);
        });
    }
};
