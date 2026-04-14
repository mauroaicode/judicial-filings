<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Src\Domain\Organization\Models\Organization;

class OrganizationNotificationChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates only email, whatsapp and sms channels (one of each type per org).
     * Each organization gets randomly 1, 2 or 3 of these channel types.
     */
    public function run(): void
    {
        $organizations = Organization::query()->get();

        foreach ($organizations as $organization) {
            // Always create internal and email for testing
            $this->createChannel($organization, 'internal');
            $this->createChannel($organization, 'email');

            // Pick 1 or 2 more randomly
            $extra = $this->pickRandomExtraChannels();

            foreach ($extra as $channelType) {
                $this->createChannel($organization, $channelType);
            }
        }
    }

    /**
     * Pick randomly 1 or 2 extra channel types (whatsapp, sms).
     *
     * @return array<int, string>
     */
    private function pickRandomExtraChannels(): array
    {
        $all = ['whatsapp', 'sms'];
        $count = random_int(0, 2);

        if ($count === 0) {
            return [];
        }

        /** @var list<string> $result */
        $result = [];

        while (count($result) < $count) {
            $channel = $all[random_int(0, count($all) - 1)];
            if (! in_array($channel, $result, true)) {
                $result[] = $channel;
            }
        }

        return $result;
    }

    private function createChannel(Organization $organization, string $channelType): void
    {
        $channelValue = $this->channelValueFor($organization, $channelType);

        OrganizationNotificationChannel::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'channel_type' => $channelType,
                'priority' => 1,
            ],
            [
                'channel_value' => $channelValue,
                'is_active' => true,
            ]
        );
    }

    private function channelValueFor(Organization $organization, string $channelType): string
    {
        return match ($channelType) {
            'email' => $organization->email ?? 'contacto@'.Str::slug($organization->name).'.com',
            'whatsapp', 'sms' => $organization->phone ?? '+57 300 '.random_int(100, 999).' '.random_int(1000, 9999),
            'internal' => 'internal',
            default => '',
        };
    }
}
