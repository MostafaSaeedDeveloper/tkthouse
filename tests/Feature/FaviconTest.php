<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** One favicon (/favicon.ico) for Frontend, Admin, Auth and Scanner; no reference to a missing file. */
class FaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_icon_file_is_a_real_multi_size_ico(): void
    {
        $bytes = (string) file_get_contents(public_path('favicon.ico'));

        $this->assertGreaterThan(1000, strlen($bytes), 'favicon.ico must not be empty');
        $this->assertSame("\x00\x00\x01\x00", substr($bytes, 0, 4), 'ICO header');
        $this->assertGreaterThanOrEqual(3, unpack('v', substr($bytes, 4, 2))[1], 'several sizes inside');
    }

    public function test_no_view_points_at_the_missing_png_and_every_page_family_links_the_same_icon(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && ! str_contains($f->getPathname(), 'html template')) {
                $this->assertStringNotContainsString('images/favicon.png', (string) file_get_contents($f->getPathname()), $f->getFilename());
            }
        }

        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        \Spatie\Permission\Models\Permission::findOrCreate('dashboard.view', 'web');
        $admin = tap(User::factory()->create(), fn ($u) => $u->givePermissionTo('dashboard.view'));
        $pages = [
            'frontend' => $this->get('/'),
            'login' => $this->get('/login'),
            'admin' => $this->actingAs($admin)->get('/dashboard'),
        ];
        foreach ($pages as $name => $response) {
            $response->assertOk();
            $this->assertMatchesRegularExpression('#<link rel="icon" href="[^"]*/favicon\.ico" sizes="any">#', $response->getContent(), $name);
            $this->assertSame(1, substr_count($response->getContent(), 'rel="icon"'), $name.' links one icon');
        }
    }
}
