<?php

declare(strict_types=1);

namespace Tests\Domain\AiChat\QueryBuilders;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Src\Domain\AiChat\Models\AiChat;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Tests\TestCase;

class AiChatQueryBuilderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_where_public_or_transitive_filters_correctly(): void
    {
        $organization = Organization::factory()->create();
        $user1 = AppUser::factory()->create();
        $user2 = AppUser::factory()->create();
        $process = Process::factory()->create();

        // Chat público de user2
        AiChat::factory()->create([
            'organization_id' => $organization->id,
            'process_id' => $process->id,
            'app_user_id' => $user2->id,
            'is_private' => false,
        ]);

        // Chat privado de user2
        AiChat::factory()->create([
            'organization_id' => $organization->id,
            'process_id' => $process->id,
            'app_user_id' => $user2->id,
            'is_private' => true,
        ]);

        // Chat privado de user1
        AiChat::factory()->create([
            'organization_id' => $organization->id,
            'process_id' => $process->id,
            'app_user_id' => $user1->id,
            'is_private' => true,
        ]);

        $results = AiChat::query()->wherePublicOrTransitive($user1->id)->get();

        $this->assertCount(2, $results);
    }
}
