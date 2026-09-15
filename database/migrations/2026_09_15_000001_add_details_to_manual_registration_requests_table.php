<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_registration_requests', function (Blueprint $table) {
            $table->string('process_class')->nullable()->after('lawyer_role');
            $table->json('plaintiffs')->nullable()->after('process_class');
            $table->json('defendants')->nullable()->after('plaintiffs');
            $table->json('other_subjects')->nullable()->after('defendants');
        });
    }

    public function down(): void
    {
        Schema::table('manual_registration_requests', function (Blueprint $table) {
            $table->dropColumn(['process_class', 'plaintiffs', 'defendants', 'other_subjects']);
        });
    }
};
