<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();

    $this->user = AppUser::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->user->organizations()->attach($this->organization->id, ['is_owner' => true]);
});

it('returns all task statuses', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/app-user/tasks/statuses');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        '*' => ['value', 'label'],
    ]);
    $response->assertJsonFragment(['value' => 'pending']);
    $response->assertJsonFragment(['value' => 'completed']);
    $response->assertJsonFragment(['value' => 'draft']);
    $response->assertJsonFragment(['label' => __('enums.task_status.pending')]);
});
