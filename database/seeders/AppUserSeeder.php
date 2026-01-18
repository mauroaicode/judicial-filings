<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Role\Models\Role;

class AppUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [
            [
                'name' => 'Juan',
                'last_name' => 'Pérez',
                'slug' => 'juan-perez',
                'email' => 'juan.perez@example.com',
                'password' => bcrypt('password1234'),
                'profile_image' => null,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'María',
                'last_name' => 'García',
                'slug' => 'maria-garcia',
                'email' => 'maria.garcia@example.com',
                'password' => bcrypt('password1234'),
                'profile_image' => null,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Carlos',
                'last_name' => 'López',
                'slug' => 'carlos-lopez',
                'email' => 'carlos.lopez@example.com',
                'password' => bcrypt('password1234'),
                'profile_image' => null,
                'email_verified_at' => now(),
            ],
        ];

        $role = Role::query()->where('guard_name', 'app_user')->firstOrFail();

        foreach ($customers as $customerData) {
            $customer = AppUser::create([
                'id' => Str::uuid(),
                ...$customerData,
            ]);
            $customer->roles()->attach($role->id);
        }

        $factoryCustomers = AppUser::factory(5)->create();
        foreach ($factoryCustomers as $customer) {
            $customer->roles()->attach($role->id);
        }
    }
}
