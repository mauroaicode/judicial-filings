<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_notification_channels', function (Blueprint $table): void {
            $table->timestamp('validated_at')->nullable()->after('is_active');
        });

        $now = now();

        DB::table('organization_notification_channels')->update([
            'validated_at' => $now,
        ]);

        Schema::create('validated_notification_emails', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->timestamp('validated_at');
            $table->timestamps();
        });

        DB::table('organization_notification_channels')
            ->where('channel_type', 'email')
            ->whereNotNull('channel_value')
            ->orderBy('id')
            ->select(['channel_value'])
            ->chunk(200, function ($rows) use ($now): void {
                $payload = [];

                foreach ($rows as $row) {
                    $email = strtolower(trim((string) $row->channel_value));

                    if ($email === '' || isset($payload[$email])) {
                        continue;
                    }

                    $payload[$email] = [
                        'id' => (string) Str::uuid(),
                        'email' => $email,
                        'validated_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($payload !== []) {
                    DB::table('validated_notification_emails')->insertOrIgnore(array_values($payload));
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('validated_notification_emails');

        Schema::table('organization_notification_channels', function (Blueprint $table): void {
            $table->dropColumn('validated_at');
        });
    }
};
