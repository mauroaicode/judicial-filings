<?php

namespace Database\Seeders;

use Core\BoundedContext\Admin\{
    User\Infrastructure\Persistence\Eloquent\UserModel,
    Role\Infrastructure\Persistence\Eloquent\RoleModel
};
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;


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
        UserModel::factory()->count(1)->create([
            'name' => 'Mauricio',
            'last_name' => 'Gutierrez',
            'email' => 'developer.mauricio2310@gmail.com',
            'slug' => Str::slug(strtolower('Mauricio') . '-' . strtolower('Gutierrez') . '-' . Str::random(10), '-'),
        ])->each(function (UserModel $user) {

            $role = RoleModel::query()->where('guard_name', 'admin')->firstOrFail();

            $user->roles()->attach($role->id);
        });;
    }
}
