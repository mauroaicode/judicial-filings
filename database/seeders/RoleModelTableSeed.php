<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Domain\Role\Models\Role;

class RoleModelTableSeed extends Seeder
{
    public function run()
    {
        Role::create([
            'name' => 'admin',
            'guard_name' => 'admin',
        ]);
        Role::create([
            'name' => 'admin',
            'guard_name' => 'app_user',
        ]);
    }
}
