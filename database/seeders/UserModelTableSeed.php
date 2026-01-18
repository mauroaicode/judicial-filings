<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Models\User;

class UserModelTableSeed extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Usuario administrador
        User::factory()->count(1)->create([
            'name' => 'Mauricio',
            'last_name' => 'Gutierrez',
            'email' => 'developer.mauricio2310@gmail.com',
            'slug' => Str::slug(strtolower('Mauricio').'-'.strtolower('Gutierrez').'-'.Str::random(10), '-'),
        ])->each(function (User $user) {

            $role = Role::query()->where('guard_name', 'admin')->firstOrFail();

            $user->roles()->attach($role->id);
        });
    }
}
