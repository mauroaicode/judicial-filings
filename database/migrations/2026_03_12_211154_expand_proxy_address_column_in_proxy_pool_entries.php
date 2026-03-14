<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expands proxy_address from varchar(50) to varchar(200) to accommodate
 * Webshare backbone connection URLs:
 *   http://wfvehrrc-250:ab7xwhoq3eip@p.webshare.io:10249
 * which are ~52 characters and exceed the previous limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proxy_pool_entries', function (Blueprint $table): void {
            $table->string('proxy_address', 200)->change();
        });
    }

    public function down(): void
    {
        Schema::table('proxy_pool_entries', function (Blueprint $table): void {
            $table->string('proxy_address', 50)->change();
        });
    }
};
