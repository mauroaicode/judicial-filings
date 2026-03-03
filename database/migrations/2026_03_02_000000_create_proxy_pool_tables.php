<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxy_pool_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('proxy_id')->unique()->comment('ID del proveedor, ej: d-17329297559');
            $table->string('proxy_address', 50)->comment('ip:port con autenticación, ej: user:pass@ip:port');
            $table->unsignedInteger('position')->unique()->comment('Orden secuencial 0..N para round-robin');
            $table->boolean('is_active')->default(true)->comment('false cuando falla cURL 7 o cURL 28');
            $table->timestamps();

            $table->index('position');
            $table->index('is_active');
        });

        Schema::create('proxy_pool_state', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->default(1)->primary()->comment('Siempre 1 — registro único');
            $table->unsignedInteger('current_position')->default(0)->comment('Puntero round-robin actual');
            $table->unsignedInteger('total_count')->default(0)->comment('Total de proxies en el pool');
            $table->unsignedInteger('active_count')->default(0)->comment('Proxies con is_active = true');
            $table->string('provider', 50)->default('webshare');
            $table->timestamp('last_fetched_at')->nullable()->comment('Última vez que se obtuvieron IPs del proveedor');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxy_pool_entries');
        Schema::dropIfExists('proxy_pool_state');
    }
};
