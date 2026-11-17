<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\User;
use Illuminate\Support\Str;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\BuildsOrders;
use App\Services\Wallet\ApplePassBuilder;
use App\Services\Wallet\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class AppleWalletPassTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private function ticket(?User $user = null, ?Event $event = null): IssuedTicket
    {
        $user ??= User::factory()->create();
        $order = $this->makePendingOrder($user, $event, 1);
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
        $item = $order->items()->first();

        return IssuedTicket::query()->where('order_id', $order->id)->first() ?? IssuedTicket::create(['order_id' => $order->id, 'order_item_id' => $item->id, 'seat_index' => 1, 'uuid' => (string) Str::uuid(), 'ticket_number' => 'TKT-1', 'holder_name' => 'Jane', 'holder_email' => 'jane@example.test', 'holder_phone' => '0100', 'ticket_name' => 'Pay Night - GA', 'ticket_price' => 100]);
    }

    private function configure(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $csr = openssl_csr_new(['commonName' => 'Pass'], $key);
        $cert = openssl_csr_sign($csr, null, $key, 2);
        openssl_x509_export($cert, $pem);
        openssl_pkcs12_export($cert, $p12, $key, 'pw');
        SystemSettings::set('wallet_apple_pass_type_id', 'pass.test.tickets');
        SystemSettings::set('wallet_apple_team_id', 'ABCDE12345');
        SystemSettings::set('wallet_apple_secrets', Crypt::encryptString(json_encode(['p12' => base64_encode($p12), 'password' => 'pw', 'wwdr' => $pem])));
    }

    public function test_not_configured_means_no_button_and_no_pass(): void
    {
        $this->assertFalse(Wallet::configured());
    }

    public function test_builds_a_signed_pkpass_with_event_and_qr(): void
    {
        $this->configure();
        $event = $this->makeEvent(['name' => 'Fest', 'event_time' => '20:30:00', 'location' => 'Cairo Stadium']);
        $ticket = $this->ticket(null, $event);
        $path = (new ApplePassBuilder())->build($ticket, $event);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path));
        foreach (['pass.json', 'manifest.json', 'signature', 'icon.png', 'logo.png'] as $f) {
            $this->assertNotFalse($zip->locateName($f), $f);
        }
        $pass = json_decode($zip->getFromName('pass.json'), true);
        $this->assertSame((string) $ticket->ticket_number, $pass['barcodes'][0]['message']);
        $this->assertSame('Fest', $pass['eventTicket']['primaryFields'][0]['value']);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertSame(sha1($zip->getFromName('pass.json')), $manifest['pass.json']);
        $this->assertNotSame('', $zip->getFromName('signature'));
        unlink($path);
    }

    public function test_only_the_owner_can_download_the_pass(): void
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        $this->configure();
        $owner = User::factory()->create();
        $ticket = $this->ticket($owner);

        $this->actingAs(User::factory()->create())->get(route('front.tickets.wallet', $ticket))->assertForbidden();
        $this->actingAs($owner)->get(route('front.tickets.wallet', $ticket))->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.apple.pkpass');
    }

    public function test_unconfigured_wallet_is_a_404_even_for_the_owner(): void
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        $owner = User::factory()->create();
        $this->actingAs($owner)->get(route('front.tickets.wallet', $this->ticket($owner)))->assertNotFound();
    }

    public function test_admin_saves_wallet_settings_and_rejects_a_wrong_password(): void
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        $admin = User::factory()->create();
        \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web');
        $admin->assignRole('super_admin');
        \Illuminate\Support\Facades\Gate::before(fn () => true);

        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $cert = openssl_csr_sign(openssl_csr_new(['commonName' => 'Pass'], $key), null, $key, 2);
        openssl_x509_export($cert, $pem);
        openssl_pkcs12_export($cert, $p12, $key, 'pw');
        $upload = fn ($name, $bytes) => \Illuminate\Http\UploadedFile::fake()->createWithContent($name, $bytes);
        $base = ['wallet_apple_pass_type_id' => 'pass.test.tickets', 'wallet_apple_team_id' => 'ABCDE12345'];

        $this->actingAs($admin)->put(route('admin.settings.section.update', 'wallet'), $base + ['wallet_certificate' => $upload('p.p12', $p12), 'wallet_certificate_password' => 'WRONG', 'wallet_wwdr' => $upload('w.pem', $pem)])
            ->assertSessionHasErrors('wallet_certificate');
        $this->assertFalse(Wallet::configured());

        $this->actingAs($admin)->put(route('admin.settings.section.update', 'wallet'), $base + ['wallet_certificate' => $upload('p.p12', $p12), 'wallet_certificate_password' => 'pw', 'wallet_wwdr' => $upload('w.pem', $pem)])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Wallet::configured());
        $stored = (string) \App\Models\SystemSetting::where('key', 'wallet_apple_secrets')->value('value');
        $this->assertStringNotContainsString('BEGIN CERTIFICATE', $stored);
        $this->assertStringNotContainsString(base64_encode($p12), $stored); // encrypted, not just encoded

        $this->actingAs($admin)->get(route('admin.settings.section', 'wallet'))->assertOk()->assertSee('Apple certificates')->assertDontSee('BEGIN CERTIFICATE');
    }

    public function test_walletwallet_provider_posts_the_ticket_and_serves_the_returned_pkpass(): void
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        SystemSettings::set('wallet_provider', 'walletwallet');
        SystemSettings::set('wallet_apple_secrets', Crypt::encryptString(json_encode(['ww_api_key' => 'ww_live_'.str_repeat('a', 32)])));
        \Illuminate\Support\Facades\Http::fake(['api.walletwallet.dev/*' => \Illuminate\Support\Facades\Http::response('PKPASSBYTES', 200, ['Content-Type' => 'application/vnd.apple.pkpass'])]);
        $owner = User::factory()->create();
        $ticket = $this->ticket($owner);

        $this->assertTrue(Wallet::configured());
        $res = $this->actingAs($owner)->get(route('front.tickets.wallet', $ticket));
        $res->assertOk()->assertHeader('Content-Type', 'application/vnd.apple.pkpass');

        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer ww_live_'.str_repeat('a', 32))
            && $r['barcodeValue'] === (string) $ticket->ticket_number && $r['barcodeFormat'] === 'QR' && $r['colorPreset'] === 'dark' && ! isset($r['logoURL']));
    }

    public function test_walletwallet_failure_is_a_friendly_503_not_a_crash(): void
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        SystemSettings::set('wallet_provider', 'walletwallet');
        SystemSettings::set('wallet_apple_secrets', Crypt::encryptString(json_encode(['ww_api_key' => 'ww_live_'.str_repeat('a', 32)])));
        \Illuminate\Support\Facades\Http::fake(['api.walletwallet.dev/*' => \Illuminate\Support\Facades\Http::response(['error' => 'rate limit'], 429)]);
        $owner = User::factory()->create();

        $this->actingAs($owner)->get(route('front.tickets.wallet', $this->ticket($owner)))->assertStatus(503);
    }
}
