<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Keep one activity row per app user, organization and day so the admin
     * can filter logins and presence by organization.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('app_user_daily_activities', 'organization_id')) {
            Schema::table('app_user_daily_activities', function (Blueprint $table) {
                $table->uuid('organization_id')->nullable()->after('app_user_id');
                $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
                $table->index(['organization_id', 'activity_date']);
            });
        }

        Schema::table('app_user_daily_activities', function (Blueprint $table) {
            $table->index('app_user_id', 'app_user_daily_activities_app_user_id_index');
        });

        Schema::table('app_user_daily_activities', function (Blueprint $table) {
            $table->dropUnique(['app_user_id', 'activity_date']);
        });

        $activities = DB::table('app_user_daily_activities')->orderBy('id')->get();

        foreach ($activities as $activity) {
            $organizationIds = DB::table('app_user_organization')
                ->where('app_user_id', $activity->app_user_id)
                ->orderBy('organization_id')
                ->pluck('organization_id');

            if ($organizationIds->isEmpty()) {
                continue;
            }

            $primaryOrganizationId = $organizationIds->shift();

            DB::table('app_user_daily_activities')
                ->where('id', $activity->id)
                ->update(['organization_id' => $primaryOrganizationId]);

            foreach ($organizationIds as $organizationId) {
                DB::table('app_user_daily_activities')->insert([
                    'id' => (string) Str::uuid(),
                    'app_user_id' => $activity->app_user_id,
                    'organization_id' => $organizationId,
                    'activity_date' => $activity->activity_date,
                    'first_seen_at' => $activity->first_seen_at,
                    'last_seen_at' => $activity->last_seen_at,
                    'login_count' => $activity->login_count,
                    'ip_address' => $activity->ip_address,
                    'country' => $activity->country,
                    'region' => $activity->region,
                    'city' => $activity->city,
                    'created_at' => $activity->created_at,
                    'updated_at' => $activity->updated_at,
                ]);
            }
        }

        Schema::table('app_user_daily_activities', function (Blueprint $table) {
            $table->unique(['app_user_id', 'organization_id', 'activity_date'], 'app_user_daily_activities_user_org_date_unique');
            $table->dropIndex('app_user_daily_activities_app_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('app_user_daily_activities', function (Blueprint $table) {
            $table->dropUnique('app_user_daily_activities_user_org_date_unique');
        });

        $duplicateIds = DB::table('app_user_daily_activities as activity')
            ->select('activity.id')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('app_user_daily_activities as earlier')
                    ->whereColumn('earlier.app_user_id', 'activity.app_user_id')
                    ->whereColumn('earlier.activity_date', 'activity.activity_date')
                    ->whereColumn('earlier.id', '<', 'activity.id');
            })
            ->pluck('id');

        if ($duplicateIds->isNotEmpty()) {
            DB::table('app_user_daily_activities')->whereIn('id', $duplicateIds)->delete();
        }

        Schema::table('app_user_daily_activities', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id', 'activity_date']);
            $table->dropColumn('organization_id');
            $table->unique(['app_user_id', 'activity_date']);
        });
    }
};
