<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_registration_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('app_user_id');
            $table->string('process_number', 23);
            $table->string('reason'); // not_found | private | all_private
            $table->string('status')->default('pending'); // pending | registered | rejected
            $table->string('lawyer_role')->nullable();
            $table->unsignedInteger('unassigned_actions_count')->default(0);
            $table->boolean('discord_notified')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('app_user_id')->references('id')->on('app_users')->onDelete('cascade');
            $table->index(['organization_id', 'process_number', 'status'], 'manual_reg_req_org_number_status_idx');
            $table->index(['status', 'created_at'], 'manual_reg_req_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_registration_requests');
    }
};
