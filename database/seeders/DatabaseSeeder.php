<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            TicketTemplateSeeder::class,
            SettingsSeeder::class,
        ]);

        $admin = User::factory()->create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'admin@tkthouse.test',
            'status' => 'active',
        ]);
        $admin->assignRole('super_admin');
    }
}
