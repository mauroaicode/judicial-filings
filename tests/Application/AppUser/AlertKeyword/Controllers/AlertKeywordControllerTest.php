<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;

beforeEach(function (): void {
    if (! Schema::hasTable('alert_actions_keywords')) {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_02_05_000000_create_alert_actions_keywords_table.php',
        ]);
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_02_05_000001_create_process_action_alert_action_keyword_table.php',
        ]);
        (new \Database\Seeders\AlertKeywordSeeder)->run();
    }

    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create([
        'email' => 'alertkeywords@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);
});

it('requires authentication for alert keywords index', function (): void {
    $response = $this->getJson('/api/app-user/alert-keywords');

    $response->assertStatus(401);
});

it('returns list of alert keywords with id name slug', function (): void {
    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/alert-keywords');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toBeArray();
    if (count($data) > 0) {
        $response->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);
    }
});
