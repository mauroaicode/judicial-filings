<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('app_user_organization', function (Blueprint $table) {
            $table->uuid('app_user_id');
            $table->uuid('organization_id');
            $table->boolean('is_owner')->default(false);
            $table->timestamps();

            $table->primary(['app_user_id', 'organization_id']);
            $table->foreign('app_user_id')->references('id')->on('app_users')->onDelete('cascade');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_user_organization');
    }
};
