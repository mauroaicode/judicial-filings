<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->where('guard_name', 'admin')->first();
    if ($adminRole) {
        $this->user->roles()->attach($adminRole->id);
    }
});

it('requires authentication to list organizations', function (): void {
    $response = $this->getJson('/api/admin/organizations');

    $response->assertStatus(401);
});

it('returns paginated organizations when authenticated as admin', function (): void {
    $org1 = Organization::factory()->create([
        'name' => 'Org First',
        'slug' => 'org-first',
        'email' => 'first@example.com',
    ]);
    $org2 = Organization::factory()->create([
        'name' => 'Org Second',
        'slug' => 'org-second',
        'email' => 'second@example.com',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/organizations');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data',
        'current_page',
        'per_page',
        'total',
    ]);
    $data = $response->json('data');
    expect($data)->toBeArray();
    $response->assertJsonFragment([
        'id' => $org1->id,
        'name' => 'Org First',
        'slug' => 'org-first',
        'email' => 'first@example.com',
    ]);
    $response->assertJsonFragment([
        'id' => $org2->id,
        'name' => 'Org Second',
        'slug' => 'org-second',
        'email' => 'second@example.com',
    ]);
    $first = collect($data)->first();
    expect($first)->toHaveKeys(['index', 'id', 'name', 'slug', 'type', 'email', 'is_active', 'created_at', 'updated_at']);
});

it('returns organizations ordered by created_at descending', function (): void {
    $older = Organization::factory()->create([
        'name' => 'Older Org',
        'email' => 'older@example.com',
        'created_at' => now()->subDay(),
    ]);
    $newer = Organization::factory()->create([
        'name' => 'Newer Org',
        'email' => 'newer@example.com',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/organizations?per_page=100');

    $response->assertStatus(200);
    $data = $response->json('data');
    $ids = array_column($data, 'id');
    $newerPos = array_search($newer->id, $ids, true);
    $olderPos = array_search($older->id, $ids, true);
    expect($newerPos)->not->toBeFalse();
    expect($olderPos)->not->toBeFalse();
    expect($newerPos)->toBeLessThan($olderPos);
});

it('returns paginated results with per_page and total', function (): void {
    Organization::factory()->count(5)->create();

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/organizations?per_page=2&page=1');

    $response->assertStatus(200);
    expect($response->json('per_page'))->toBe(2);
    expect($response->json('current_page'))->toBe(1);
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('total'))->toBeGreaterThanOrEqual(5);
});

it('includes index field that continues across pages', function (): void {
    $page1 = $this->actingAs($this->user)->getJson('/api/admin/organizations?per_page=2&page=1');
    $page2 = $this->actingAs($this->user)->getJson('/api/admin/organizations?per_page=2&page=2');

    $page1->assertStatus(200);
    $page2->assertStatus(200);
    $firstPageIndices = array_column($page1->json('data'), 'index');
    $secondPageIndices = array_column($page2->json('data'), 'index');
    expect($firstPageIndices[0] ?? null)->toBe(1);
    expect($firstPageIndices[1] ?? null)->toBe(2);
    expect($secondPageIndices[0] ?? null)->toBe(3);
});

it('filters organizations by name', function (): void {
    $org = Organization::factory()->create(['name' => 'Unique Name Org', 'email' => 'unique@example.com']);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/organizations?name=Unique+Name');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect(collect($data)->pluck('id')->toArray())->toContain($org->id);
    expect(collect($data)->every(fn ($row) => str_contains($row['name'], 'Unique Name')))->toBeTrue();
});

it('filters organizations by type', function (): void {
    $natural = Organization::factory()->natural()->create(['name' => 'Natural One', 'email' => 'n1@example.com']);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/organizations?type=natural');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect(collect($data)->pluck('id')->toArray())->toContain($natural->id);
    expect(collect($data)->every(fn ($row) => $row['type'] === 'natural'))->toBeTrue();
});

it('filters organizations by email', function (): void {
    $org = Organization::factory()->create(['name' => 'Org A', 'email' => 'specific.email@example.com']);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/organizations?email=specific.email');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect(collect($data)->pluck('id')->toArray())->toContain($org->id);
    expect(collect($data)->every(fn ($row) => str_contains($row['email'], 'specific.email')))->toBeTrue();
});

it('filters organizations by is_active active', function (): void {
    Organization::factory()->create(['name' => 'Active Org', 'email' => 'active@example.com', 'is_active' => true]);
    Organization::factory()->create(['name' => 'Inactive Org', 'email' => 'inactive@example.com', 'is_active' => false]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/organizations?is_active=active');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect(collect($data)->every(fn ($row) => $row['is_active'] === true))->toBeTrue();
});

it('filters organizations by is_active inactive', function (): void {
    Organization::factory()->create(['name' => 'Inactive One', 'email' => 'in1@example.com', 'is_active' => false]);
    Organization::factory()->create(['name' => 'Active One', 'email' => 'a1@example.com', 'is_active' => true]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/organizations?is_active=inactive');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect(collect($data)->every(fn ($row) => $row['is_active'] === false))->toBeTrue();
});

it('filters organizations by created_at date range', function (): void {
    $inRange = Organization::factory()->create([
        'name' => 'In Range',
        'email' => 'inrange@example.com',
        'created_at' => now()->subDays(5),
    ]);
    $outRange = Organization::factory()->create([
        'name' => 'Out Range',
        'email' => 'outrange@example.com',
        'created_at' => now()->subDays(30),
    ]);

    $from = now()->subDays(10)->format('Y-m-d');
    $to = now()->subDays(1)->format('Y-m-d');

    $response = $this->actingAs($this->user)
        ->getJson('/api/admin/organizations?created_at_from='.$from.'&created_at_to='.$to);

    $response->assertStatus(200);
    $data = $response->json('data');
    $ids = array_column($data, 'id');
    expect($ids)->toContain($inRange->id);
    expect($ids)->not->toContain($outRange->id);
});

it('requires authentication to store organization', function (): void {
    $response = $this->postJson('/api/admin/organizations', [
        'name' => 'Test Org',
        'type' => 'natural',
        'phone' => '9123456789',
        'email' => 'org@example.com',
    ]);

    $response->assertStatus(401);
});

it('validates required fields when storing organization', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/organizations', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name', 'type', 'phone', 'email']);
});

it('validates phone has 10 digits when storing organization', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/organizations', [
            'name' => 'Test Org',
            'type' => 'natural',
            'phone' => '123',
            'email' => 'org@example.com',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['phone']);
});

it('validates email format when storing organization', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/organizations', [
            'name' => 'Test Org',
            'type' => 'natural',
            'phone' => '9123456789',
            'email' => 'invalid-email',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

it('validates email is unique when storing organization', function (): void {
    Organization::factory()->create(['email' => 'taken@example.com']);

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/organizations', [
            'name' => 'Another Org',
            'type' => 'natural',
            'phone' => '9123456789',
            'email' => 'taken@example.com',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

it('validates contact_person is required for juridical type', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/organizations', [
            'name' => 'Empresa XYZ',
            'type' => 'juridical',
            'phone' => '9123456789',
            'email' => 'empresa@example.com',
            'contact_person' => null,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['contact_person']);
});

it('creates organization and owner app user and sends notification', function (): void {
    Notification::fake();

    $payload = [
        'name' => 'Carlos López',
        'type' => 'natural',
        'identification' => '98765432-9',
        'address' => 'Av. Vitacura 789, Santiago',
        'phone' => '9876543210',
        'email' => 'carlos.org.test@example.com',
        'contact_person' => null,
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/organizations', $payload);

    $response->assertStatus(201);
    $response->assertJsonFragment([
        'name' => 'Carlos López',
        'type' => 'natural',
        'email' => 'carlos.org.test@example.com',
        'identification' => '98765432-9',
        'address' => 'Av. Vitacura 789, Santiago',
        'is_active' => true,
    ]);

    $org = Organization::query()->where('email', 'carlos.org.test@example.com')->first();
    expect($org)->not->toBeNull();
    expect($org->slug)->toContain('carlos-lopez');
    expect($org->phone)->toContain('+57');

    $org->load('appUsers');
    expect($org->appUsers)->toHaveCount(1);
    $owner = $org->appUsers->first();
    expect((bool) $owner->pivot->is_owner)->toBeTrue();
    expect($owner->email)->toBe('carlos.org.test@example.com');

    Notification::assertSentTo(
        $owner,
        \Src\Application\Shared\Notifications\AccountCreatedNotification::class
    );

    // Verify notification channels
    $org->load('notificationChannels');
    expect($org->notificationChannels)->toHaveCount(2);
    expect($org->notificationChannels->pluck('channel_type')->toArray())->toContain('email');
    expect($org->notificationChannels->pluck('channel_type')->toArray())->toContain('internal');
});

it('creates juridical organization with contact person', function (): void {
    Notification::fake();

    $payload = [
        'name' => 'Empresa ABC Ltda.',
        'type' => 'juridical',
        'identification' => '76.123.456-9',
        'address' => 'Av. Las Condes 456',
        'phone' => '2234567890',
        'email' => 'contacto.test@empresaabc.test',
        'contact_person' => 'María García',
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/organizations', $payload);

    $response->assertStatus(201);

    $org = Organization::query()->where('email', 'contacto.test@empresaabc.test')->first();
    expect($org)->not->toBeNull();
    expect($org->contact_person)->toBe('María García');
    expect($org->is_active)->toBeTrue();

    $owner = $org->appUsers()->wherePivot('is_owner', true)->first();
    expect($owner->name)->toBe('María');
    expect($owner->last_name)->toBe('García');
});
