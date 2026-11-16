<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Support\BuildsOrders;
use App\Services\Wallet\ApplePassBuilder;
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
        $dir = sys_get_temp_dir().'/wallet-test-'.getmypid();
        @mkdir($dir);
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $csr = openssl_csr_new(['commonName' => 'Pass'], $key);
        $cert = openssl_csr_sign($csr, null, $key, 2);
        openssl_x509_export($cert, $pem);
        openssl_pkcs12_export($cert, $p12, $key, 'pw');
        file_put_contents("$dir/pass.p12", $p12);
        file_put_contents("$dir/wwdr.pem", $pem);
        config(['wallet.apple' => array_merge(config('wallet.apple'), [
            'pass_type_id' => 'pass.test.tickets', 'team_id' => 'ABCDE12345',
            'certificate_path' => "$dir/pass.p12", 'certificate_password' => 'pw', 'wwdr_path' => "$dir/wwdr.pem",
        ])]);
    }

    public function test_not_configured_means_no_button_and_no_pass(): void
    {
        $this->assertFalse(ApplePassBuilder::configured());
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
}
