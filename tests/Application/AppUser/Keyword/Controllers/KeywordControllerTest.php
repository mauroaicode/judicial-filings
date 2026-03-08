<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Keyword\Enums\KeywordStatus;
use Src\Domain\Keyword\Models\Keyword;
use Src\Domain\Organization\Models\Organization;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->otherOrganization = Organization::factory()->create();

    $this->appUser = AppUser::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);

    // Attach user to organization
    $this->appUser->organizations()->attach($this->organization->id, [
        'is_owner' => true,
    ]);
});

it('requires authentication to list keywords', function (): void {
    $response = $this->getJson('/api/app-user/keywords');

    $response->assertStatus(401);
});

it('returns all keyword statuses', function (): void {
    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/keywords/statuses');

    $response->assertStatus(200);
    $response->assertExactJson([
        [
            'value' => 'active',
            'label' => 'Activa',
        ],
        [
            'value' => 'inactive',
            'label' => 'Inactiva',
        ],
    ]);
});

it('returns paginated keywords for the user organization', function (): void {
    $keyword1 = Keyword::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'My Keyword One',
    ]);
    $keyword2 = Keyword::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'My Keyword Two',
    ]);
    
    // Keyword from another organization should not appear
    Keyword::factory()->create([
        'organization_id' => $this->otherOrganization->id,
        'name' => 'Other Keyword',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/keywords');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveCount(2);
    
    $response->assertJsonFragment(['name' => 'My Keyword One']);
    $response->assertJsonFragment(['name' => 'My Keyword Two']);
    $response->assertJsonMissing(['name' => 'Other Keyword']);
});

it('allows creating a keyword for the organization', function (): void {
    $payload = [
        'name' => 'New Keyword',
        'keyword' => 'SearchWord',
        'status' => 'active',
    ];

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/keywords', $payload);

    $response->assertStatus(201);
    $response->assertJsonFragment([
        'name' => 'New Keyword',
        'keyword' => 'SearchWord',
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('keywords', [
        'organization_id' => $this->organization->id,
        'name' => 'New Keyword',
        'keyword' => 'SearchWord',
    ]);
});

it('filters keywords by name and keyword', function (): void {
    Keyword::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Target Name',
        'keyword' => 'other',
    ]);
    Keyword::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'other',
        'keyword' => 'TargetWord',
    ]);

    // Search by name
    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/keywords?name=Target');
    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Target Name');

    // Search by keyword
    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/keywords?keyword=TargetWord');
    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.keyword'))->toBe('TargetWord');
});

it('filters keywords by status', function (): void {
    Keyword::factory()->create([
        'organization_id' => $this->organization->id,
        'status' => KeywordStatus::ACTIVE,
    ]);
    Keyword::factory()->create([
        'organization_id' => $this->organization->id,
        'status' => KeywordStatus::INACTIVE,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson('/api/app-user/keywords?status=inactive');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['status'])->toBe('inactive');
});

it('shows a single keyword correctly', function (): void {
    $keyword = Keyword::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Specific Keyword',
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/keywords/{$keyword->id}");

    $response->assertStatus(200);
    $response->assertJsonFragment(['name' => 'Specific Keyword']);
});

it('does not show keyword from other organization', function (): void {
    $keyword = Keyword::factory()->create([
        'organization_id' => $this->otherOrganization->id,
    ]);

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/keywords/{$keyword->id}");

    $response->assertStatus(404);
});

it('allows updating a keyword', function (): void {
    $keyword = Keyword::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Old Name',
    ]);

    $payload = [
        'name' => 'Updated Name',
        'keyword' => 'UpdatedWord',
        'status' => 'inactive',
    ];

    $response = $this->actingAs($this->appUser)
        ->putJson("/api/app-user/keywords/{$keyword->id}", $payload);

    $response->assertStatus(200);
    $response->assertJsonFragment(['name' => 'Updated Name', 'status' => 'inactive']);

    $this->assertDatabaseHas('keywords', [
        'id' => $keyword->id,
        'name' => 'Updated Name',
    ]);
});

it('allows deleting a keyword', function (): void {
    $keyword = Keyword::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->appUser)
        ->deleteJson("/api/app-user/keywords/{$keyword->id}");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('keywords', ['id' => $keyword->id]);
});
