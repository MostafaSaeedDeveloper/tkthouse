<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Backup\DatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Database backups are a Super Admin feature: private gzip SQL files, audited, never downloadable by anyone else. */
class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->assignRole(Role::findOrCreate('super_admin', 'web'));

        return $u;
    }

    private function other(): User
    {
        Permission::findOrCreate('settings.view', 'web');
        Permission::findOrCreate('dashboard.view', 'web');

        return tap(User::factory()->create(), fn ($u) => $u->givePermissionTo(['settings.view', 'dashboard.view']));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_only_a_super_admin_can_see_create_download_or_delete_backups(): void
    {
        $other = $this->other();
        $this->actingAs($other)->get(route('admin.backups.index'))->assertForbidden();
        $this->actingAs($other)->post(route('admin.backups.store'))->assertForbidden();
        $this->actingAs($other)->get(route('admin.dashboard'))->assertDontSee('Database backups');

        $super = $this->superAdmin();
        $this->actingAs($super)->get(route('admin.backups.index'))->assertOk()->assertSee('No backups yet');
        $this->actingAs($super)->post(route('admin.backups.store'))->assertRedirect(route('admin.backups.index'));

        $file = (new DatabaseBackup())->all()[0]['name'];
        $this->actingAs($other)->get(route('admin.backups.download', $file))->assertForbidden();
        $this->actingAs($other)->delete(route('admin.backups.destroy', $file))->assertForbidden();
        $this->assertCount(1, (new DatabaseBackup())->all());
    }

    public function test_the_backup_is_a_gzip_sql_dump_with_the_data_and_the_actions_are_audited(): void
    {
        $super = $this->superAdmin();
        User::factory()->create(['email' => 'backup-me@example.test']);

        $this->actingAs($super)->post(route('admin.backups.store'))->assertSessionHasNoErrors();
        $file = (new DatabaseBackup())->all()[0]['name'];
        $path = (new DatabaseBackup())->path($file);
        $sql = gzdecode(file_get_contents($path));
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('backup-me@example.test', $sql);

        $res = $this->actingAs($super)->get(route('admin.backups.download', $file))->assertOk();
        $this->assertStringContainsString('gzip', (string) $res->headers->get('Content-Type'));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'backup', 'causer_id' => $super->id, 'description' => 'Downloaded database backup '.$file]);

        $this->actingAs($super)->delete(route('admin.backups.destroy', $file))->assertRedirect();
        $this->assertCount(0, (new DatabaseBackup())->all());
    }

    public function test_only_files_this_feature_created_can_be_addressed_and_old_ones_are_pruned(): void
    {
        $b = new DatabaseBackup();
        $this->assertNull($b->path('../../.env'));
        $this->assertNull($b->path('tkthouse-x-20260101-000000.sql.gz'));
        $this->actingAs($this->superAdmin())->get(route('admin.backups.download', '..%2F.env'))->assertNotFound();

        Storage::disk('local')->makeDirectory(DatabaseBackup::DIR);
        foreach (range(1, 13) as $i) {
            Storage::disk('local')->put(DatabaseBackup::DIR.'/tkthouse-db-202601'.sprintf('%02d', $i).'-000000.sql.gz', 'x');
            touch(Storage::disk('local')->path(DatabaseBackup::DIR.'/tkthouse-db-202601'.sprintf('%02d', $i).'-000000.sql.gz'), time() - (100 - $i));
        }
        $b->prune();
        $this->assertCount(DatabaseBackup::KEEP, $b->all());
    }
}
