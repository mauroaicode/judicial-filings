<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Role\Models\Role;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::query()->where('guard_name', 'app_user')->firstOrFail();

        $organizations = [
            [
                'name' => 'Juan Pérez',
                'type' => 'natural',
                'identification' => '12345678-9',
                'address' => 'Av. Providencia 123, Santiago',
                'phone' => '+56 9 1234 5678',
                'email' => 'juan.perez@example.com',
                'contact_person' => null,
                'owner' => [
                    'name' => 'Juan',
                    'last_name' => 'Pérez',
                    'email' => 'juan.perez@example.com',
                    'password' => bcrypt('password1234'),
                ],
            ],
            [
                'name' => 'Empresa ABC Ltda.',
                'type' => 'juridical',
                'identification' => '76.123.456-7',
                'address' => 'Av. Las Condes 456, Santiago',
                'phone' => '+56 2 2345 6789',
                'email' => 'contacto@empresaabc.cl',
                'contact_person' => 'María García',
                'owner' => [
                    'name' => 'María',
                    'last_name' => 'García',
                    'email' => 'maria.garcia@example.com',
                    'password' => bcrypt('password1234'),
                ],
            ],
            [
                'name' => 'Carlos López',
                'type' => 'natural',
                'identification' => '98765432-1',
                'address' => 'Av. Vitacura 789, Santiago',
                'phone' => '+56 9 8765 4321',
                'email' => 'carlos.lopez@example.com',
                'contact_person' => null,
                'owner' => [
                    'name' => 'Carlos',
                    'last_name' => 'López',
                    'email' => 'carlos.lopez@example.com',
                    'password' => bcrypt('password1234'),
                ],
            ],
            [
                'name' => 'Consultora XYZ SpA',
                'type' => 'juridical',
                'identification' => '65.987.654-3',
                'address' => 'Av. Apoquindo 321, Santiago',
                'phone' => '+56 2 3456 7890',
                'email' => 'info@consultoraxyz.cl',
                'contact_person' => 'Ana Martínez',
                'owner' => [
                    'name' => 'Ana',
                    'last_name' => 'Martínez',
                    'email' => 'ana.martinez@example.com',
                    'password' => bcrypt('password1234'),
                ],
            ],
        ];

        foreach ($organizations as $orgData) {
            $ownerData = $orgData['owner'];
            unset($orgData['owner']);

            $organization = Organization::create([
                'id' => Str::uuid(),
                'slug' => Str::slug($orgData['name']),
                ...$orgData,
            ]);

            $owner = AppUser::create([
                'id' => Str::uuid(),
                'name' => $ownerData['name'],
                'last_name' => $ownerData['last_name'],
                'slug' => Str::slug($ownerData['name'].' '.$ownerData['last_name']),
                'email' => $ownerData['email'],
                'password' => $ownerData['password'],
                'profile_image' => null,
                'email_verified_at' => now(),
            ]);

            $organization->appUsers()->attach($owner->id, ['is_owner' => true]);

            $owner->roles()->attach($role->id);
        }

        // Create additional random organizations using factory
        $factoryOrganizations = Organization::factory(3)->create();
        foreach ($factoryOrganizations as $organization) {
            $owner = AppUser::factory()->create();
            $organization->appUsers()->attach($owner->id, ['is_owner' => true]);
            $owner->roles()->attach($role->id);
        }
    }
}
