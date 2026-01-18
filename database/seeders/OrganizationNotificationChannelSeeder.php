<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Src\Domain\Organization\Models\Organization;

class OrganizationNotificationChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //        $organizations = Organization::all();
        //
        //        foreach ($organizations as $organization) {
        //            // Crear 2 canales de email por organización
        //            $this->createEmailChannels($organization);
        //
        //            // Crear 2 canales de WhatsApp por organización
        //            $this->createWhatsAppChannels($organization);
        //
        //            // Crear 2 canales de SMS por organización
        //            $this->createSmsChannels($organization);
        //
        //            // Crear 2 canales internos por organización
        //            $this->createInternalChannels($organization);
        //        }
    }

    /**
     * Create email notification channels for an organization.
     */
    //    private function createEmailChannels(Organization $organization): void
    //    {
    //        $emails = [
    //            $organization->email ?? 'contacto@' . Str::slug($organization->name) . '.cl',
    //            'notificaciones@' . Str::slug($organization->name) . '.cl',
    //        ];
    //
    //        foreach ($emails as $index => $email) {
    //            OrganizationNotificationChannel::create([
    //                'id' => Str::uuid(),
    //                'organization_id' => $organization->id,
    //                'channel_type' => NotificationChannelType::EMAIL,
    //                'channel_value' => $email,
    //                'is_active' => true,
    //                'priority' => $index + 1,
    //            ]);
    //        }
    //    }

    /**
     * Create WhatsApp notification channels for an organization.
     */
    //    private function createWhatsAppChannels(Organization $organization): void
    //    {
    //        $phones = [
    //            $organization->phone ?? '+56 9 1234 5678',
    //            '+56 9 8765 4321',
    //        ];
    //
    //        foreach ($phones as $index => $phone) {
    //            OrganizationNotificationChannel::create([
    //                'id' => Str::uuid(),
    //                'organization_id' => $organization->id,
    //                'channel_type' => NotificationChannelType::WHATSAPP,
    //                'channel_value' => $phone,
    //                'is_active' => true,
    //                'priority' => $index + 1,
    //            ]);
    //        }
    //    }

    /**
     * Create SMS notification channels for an organization.
     */
    //    private function createSmsChannels(Organization $organization): void
    //    {
    //        $phones = [
    //            $organization->phone ?? '+56 9 1234 5678',
    //            '+56 9 8765 4321',
    //        ];
    //
    //        foreach ($phones as $index => $phone) {
    //            OrganizationNotificationChannel::create([
    //                'id' => Str::uuid(),
    //                'organization_id' => $organization->id,
    //                'channel_type' => NotificationChannelType::SMS,
    //                'channel_value' => $phone,
    //                'is_active' => true,
    //                'priority' => $index + 1,
    //            ]);
    //        }
    //    }

    //    /**
    //     * Create internal notification channels for an organization.
    //     */
    //    private function createInternalChannels(Organization $organization): void
    //    {
    //        $internalChannels = [
    //            'dashboard',
    //            'mobile_app',
    //        ];
    //
    //        foreach ($internalChannels as $index => $channel) {
    //            OrganizationNotificationChannel::create([
    //                'id' => Str::uuid(),
    //                'organization_id' => $organization->id,
    //                'channel_type' => NotificationChannelType::INTERNAL,
    //                'channel_value' => $channel,
    //                'is_active' => true,
    //                'priority' => $index + 1,
    //            ]);
    //        }
    //    }
}
