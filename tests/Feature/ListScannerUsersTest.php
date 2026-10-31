<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ListScannerUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_scanner_users_and_never_a_password(): void
    {
        $role = Role::findOrCreate('scanner', 'web');
        $s = User::factory()->create(['name' => 'Gate One', 'username' => 'gate1', 'email' => 'gate1@example.test']);
        $s->assignRole($role);
        User::factory()->create(['name' => 'Not Scanner', 'username' => 'other']);

        $this->artisan('scanners:list', ['--format' => 'csv'])
            ->expectsOutputToContain('gate1@example.test')->doesntExpectOutputToContain('Not Scanner')->doesntExpectOutputToContain((string) $s->password)
            ->assertSuccessful();
    }

    public function test_rejects_unknown_format(): void
    {
        $this->artisan('scanners:list', ['--format' => 'xml'])->assertFailed();
    }
}
