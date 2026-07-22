<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Src\Application\Shared\Mail\ConsolidatedJudicialActionsMailable;
use Src\Application\Shared\Services\Notification\ResendDigestEmailsService;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Organization\Models\Organization;

it('resends latest today digest only to secondary email channels by default', function (): void {
    $organization = Organization::factory()->create(['name' => 'Cooperativa Test']);
    $organization->notificationChannels()->createMany([
        [
            'channel_type' => 'email',
            'channel_value' => 'primary@example.com',
            'is_active' => true,
            'priority' => 1,
        ],
        [
            'channel_type' => 'email',
            'channel_value' => 'secondary@example.com',
            'is_active' => true,
            'priority' => 2,
        ],
    ]);

    $digest = NotificationDigest::factory()->create([
        'organization_id' => $organization->id,
        'data' => [
            [
                'process_number' => '76001310501820260012000',
                'action_text' => 'Auto Decide',
                'court' => 'Juzgado Test',
                'demandante' => 'A',
                'demandado' => 'B',
                'registration_date' => now()->format('Y-m-d'),
            ],
        ],
        'email_sent_at' => now(),
        'created_at' => now(),
    ]);

    Mail::fake();

    $result = app(ResendDigestEmailsService::class)->resend(
        organizationId: $organization->id,
    );

    expect($result->digestId)->toBe($digest->id);
    expect($result->sentTo)->toBe(['secondary@example.com']);
    expect($result->failed)->toBe([]);

    Mail::assertSent(ConsolidatedJudicialActionsMailable::class, 1);
    Mail::assertSent(
        ConsolidatedJudicialActionsMailable::class,
        fn ($mail) => $mail->hasTo('secondary@example.com'),
    );
    Mail::assertNotSent(
        ConsolidatedJudicialActionsMailable::class,
        fn ($mail) => $mail->hasTo('primary@example.com'),
    );
});
