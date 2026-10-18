<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\ExternalTransaction;
use App\Models\IntegrationAccount;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\ProviderSync;
use App\Models\User;
use App\Services\Payments\External\ExternalTransactionCsvImporter;
use App\Services\Payments\External\ExternalTransactionService;
use App\Services\Payments\External\ProviderSyncService;
use App\Services\Payments\PaymentReport;
use App\Services\Payments\ProviderCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsMerchantAccounts;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** Provider-side transactions: lookup by id, CSV import, linking, sync, capabilities. HTTP is faked; no real provider is contacted. */
class ExternalTransactionTest extends TestCase
{
    use BuildsMerchantAccounts, BuildsOrders, RefreshDatabase;

    private IntegrationAccount $pmA;
    private IntegrationAccount $pmB;
    private IntegrationAccount $fwA;
    private IntegrationAccount $fwB;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        $this->fakeProviders();
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        $this->pmA = $this->account('paymob', 'PA');
        $this->pmB = $this->account('paymob', 'PB');
        $this->fwA = $this->account('fawaterak', 'FA');
        $this->fwB = $this->account('fawaterak', 'FB');
        $this->admin = $this->userWith(['payments.accounts.view', 'payments.transactions.view', 'payments.transactions.inquire', 'payments.transactions.import', 'orders.view']);
    }

    private function userWith(array $perms): User
    {
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create(), fn ($u) => $u->givePermissionTo($perms));
    }

    private function svc(): ExternalTransactionService
    {
        return app(ExternalTransactionService::class);
    }

    private function pmTx(string $id, IntegrationAccount $owner, array $o = []): void
    {
        $this->paymobTx[$id] = $o + ['id' => (int) $id, 'owner' => 'KEY-'.$owner->name, 'merchant_order_id' => 'M-'.$id, 'amount_cents' => 15000, 'success' => true];
    }

    private function paidAttempt(IntegrationAccount $acc, string $tx, float $amount = 150.0, string $status = 'paid'): PaymentAttempt
    {
        $order = $this->makePendingOrder($this->admin, null, 1, $amount, 'paymob_card');

        return PaymentAttempt::create(['order_id' => $order->id, 'provider' => $acc->provider, 'method_code' => 'paymob_card', 'amount' => $amount, 'currency' => 'EGP', 'status' => $status, 'transaction_id' => $tx,
            'reference' => 'M-'.$tx, 'internal_reference' => PaymentAttempt::newInternalReference(), 'integration_account_id' => $acc->id]);
    }

    /* ------------------------------------------------------------------------- lookup by id */

    public function test_paymob_lookup_uses_the_chosen_account_and_never_another_one(): void
    {
        $this->pmTx('777001', $this->pmA, ['extra' => ['created_at' => '2026-10-01T10:00:00', 'order' => ['id' => 55, 'merchant_order_id' => 'M-777001'], 'source_data' => ['sub_type' => 'MasterCard']]]);

        // account B cannot see account A's transaction: provider answers 404, nothing is stored
        try {
            $this->svc()->lookup($this->pmB, '777001', $this->admin);
            $this->fail('another merchant account must not read it');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('did not return this transaction for the merchant account "PB"', $e->errors()['provider'][0]);
        }
        $this->assertSame(0, ExternalTransaction::count());

        $tx = $this->svc()->lookup($this->pmA, '777001', $this->admin);
        $this->assertSame(['paymob', $this->pmA->id, '777001', '55', 'M-777001', 15000, 'EGP', 'paid', 'MasterCard'], [$tx->provider, $tx->integration_account_id, $tx->provider_transaction_id, $tx->provider_order_id, $tx->provider_reference, $tx->amount_minor, $tx->currency, $tx->status, $tx->payment_method]);
        $this->assertNotNull($tx->verified_at);
        $this->assertSame('api_lookup', $tx->source);
        $this->assertSame(0, Order::count(), 'no order was created');
        // each call used the key of the account it was made for (PB only for the refused attempt above)
        $bearers = collect($this->httpLog)->pluck('bearer')->filter()->unique()->sort()->values()->all();
        $this->assertSame(['Bearer tok-KEY-PA', 'Bearer tok-KEY-PB'], $bearers);
    }

    public function test_fawaterak_lookup_maps_invoice_data_and_isolates_accounts(): void
    {
        $this->fawInvoices['INV-9'] = ['owner' => 'FKEY-FA', 'paid' => true, 'total' => 250.0, 'extra' => ['invoice_key' => 'KEY9', 'payment_method' => 'Credit Card', 'commission' => '6.25', 'customer_email' => 'buyer@example.test', 'invoice_created_at' => '2026-10-02 09:00:00',
            'invoice_transactions' => [['payment_method' => 'card', 'refrence_id' => 'REF-77', 'status' => 'paid', 'amount' => 250]]]];

        $this->expectException(ValidationException::class);
        try {
            $this->svc()->lookup($this->fwB, 'INV-9', $this->admin);
        } finally {
            $this->assertSame(0, ExternalTransaction::count());
            $tx = $this->svc()->lookup($this->fwA, 'INV-9', $this->admin);
            $this->assertSame(['paid', 25000, 625, 'REF-77', 'buyer@example.test', 'Credit Card'], [$tx->status, $tx->amount_minor, $tx->fee_minor, $tx->provider_reference, $tx->customer_label, $tx->payment_method]);
        }
    }

    public function test_status_mapping_covers_voided_refunded_partial_pending_and_failed(): void
    {
        $this->pmTx('1', $this->pmA, ['is_voided' => true]);
        $this->pmTx('2', $this->pmA, ['is_refunded' => true, 'extra' => ['refunded_amount_cents' => 15000]]);
        $this->pmTx('3', $this->pmA, ['extra' => ['refunded_amount_cents' => 5000]]);
        $this->pmTx('4', $this->pmA, ['pending' => true, 'success' => false]);
        $this->pmTx('5', $this->pmA, ['success' => false]);

        $got = collect(range(1, 5))->map(fn ($i) => $this->svc()->lookup($this->pmA, (string) $i)->status)->all();

        $this->assertSame(['voided', 'refunded', 'partially_refunded', 'pending', 'failed'], $got);
    }

    public function test_the_same_transaction_looked_up_twice_is_one_row_and_updates_in_place(): void
    {
        $this->pmTx('900', $this->pmA, ['pending' => true, 'success' => false]);
        $first = $this->svc()->lookup($this->pmA, '900');
        $this->paymobTx['900']['pending'] = false;
        $this->paymobTx['900']['success'] = true;
        $second = $this->svc()->lookup($this->pmA, '900');

        $this->assertSame(1, ExternalTransaction::count());
        $this->assertSame($first->id, $second->id);
        $this->assertSame('paid', $second->status);
    }

    public function test_rate_limit_and_unknown_id_give_clear_errors_and_store_nothing(): void
    {
        $this->providerRateLimit = true;
        try {
            $this->svc()->lookup($this->pmA, '123');
            $this->fail('rate limited');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('rate limiting', $e->errors()['provider'][0]);
        }
        $this->providerRateLimit = false;
        $this->expectException(ValidationException::class);
        try {
            $this->svc()->lookup($this->pmA, 'does-not-exist');
        } finally {
            $this->assertSame(0, ExternalTransaction::count());
        }
    }

    /* ------------------------------------------------------------------------- linking never guesses, never settles */

    public function test_linking_needs_an_exact_same_account_match_and_never_changes_the_local_payment(): void
    {
        $attempt = $this->paidAttempt($this->pmA, '555', 150.0, 'initiated');           // still waiting locally
        $orderStatus = $attempt->order->status;
        $this->pmTx('555', $this->pmA);
        $linked = $this->svc()->lookup($this->pmA, '555');

        $this->assertSame($attempt->id, $linked->matched_attempt_id);
        $this->assertSame('initiated', $attempt->fresh()->status, 'importing never marks a local payment paid');
        $this->assertSame($orderStatus, $attempt->order->fresh()->status);
        $this->assertNull($attempt->fresh()->paid_at);

        // same provider transaction id under ANOTHER merchant account: not linked to account A's attempt
        $this->pmTx('555', $this->pmB);
        $other = $this->svc()->lookup($this->pmB, '555');
        $this->assertNull($other->matched_attempt_id);
    }

    public function test_ambiguous_matches_are_not_linked_and_amount_mismatches_are_flagged_not_fixed(): void
    {
        $a = $this->paidAttempt($this->pmA, 'T-A', 150.0);
        $b = $this->paidAttempt($this->pmA, 'T-B', 150.0);
        $a->update(['provider_order_id' => '4242']);
        $b->update(['provider_order_id' => '4242']);
        $this->pmTx('600', $this->pmA, ['extra' => ['order' => ['id' => 4242, 'merchant_order_id' => 'M-600']]]);
        $amb = $this->svc()->lookup($this->pmA, '600');
        $this->assertNull($amb->matched_attempt_id);
        $this->assertTrue((bool) data_get($amb->snapshot, 'link_ambiguous'));

        $c = $this->paidAttempt($this->pmA, '601', 150.0);
        $this->pmTx('601', $this->pmA, ['amount_cents' => 99900]);
        $mismatch = $this->svc()->lookup($this->pmA, '601');
        $this->assertSame($c->id, $mismatch->matched_attempt_id);
        $this->assertSame(15000, data_get($mismatch->snapshot, 'amount_mismatch'));
        $this->assertEquals(150.0, (float) $c->fresh()->amount, 'the local amount is untouched');
    }

    public function test_a_transaction_without_a_reference_or_local_order_stays_unlinked_and_creates_no_order(): void
    {
        $this->pmTx('700', $this->pmA, ['merchant_order_id' => '']);
        $this->paymobTx['700']['extra'] = ['order' => ['merchant_order_id' => null]];
        $before = [Order::count(), PaymentAttempt::count()];

        $tx = $this->svc()->lookup($this->pmA, '700');

        $this->assertNull($tx->matched_attempt_id);
        $this->assertNull($tx->provider_reference);
        $this->assertSame($before, [Order::count(), PaymentAttempt::count()]);
        $this->actingAs($this->admin)->get(route('admin.payments.imported'))->assertOk()->assertSee('700')->assertSee('None');
    }

    /* ------------------------------------------------------------------------- CSV */

    private function csv(string $content, string $name = 'export.csv'): string
    {
        $path = storage_path('framework/testing/'.uniqid().'-'.$name);
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $content);

        return $path;
    }

    private function csvMap(string $path): array
    {
        $imp = app(ExternalTransactionCsvImporter::class);

        return $imp->suggestMapping($imp->inspect($path)['headers']);
    }

    public function test_csv_import_maps_columns_normalises_values_and_is_idempotent(): void
    {
        $path = $this->csv("\xEF\xBB\xBFTransaction ID;Amount;Currency;Status;Payment Method;Created At;Merchant Order ID;Email\n".
            "1001;1.234,50;EGP;Success;card;2026-10-01 10:00:00;MO-1;a@example.test\n".
            "1002;99;EGP;DECLINED;wallet;2026-10-02 11:00:00;MO-2;\n".
            "1003;abc;EGP;paid;card;2026-10-03;MO-3;\n".        // bad amount
            ";50;EGP;paid;card;2026-10-03;MO-4;\n".             // no id
            "1001;1.234,50;EGP;Refunded;card;2026-10-01 10:00:00;MO-1;a@example.test\n");  // duplicate id in the same file: last wins
        $imp = app(ExternalTransactionCsvImporter::class);
        $info = $imp->inspect($path);
        $map = $imp->suggestMapping($info['headers']);
        $this->assertSame(0, $map['provider_transaction_id']);
        $this->assertSame(1, $map['amount']);
        $this->assertSame(3, $map['status']);

        $r = $imp->import($this->pmA, $path, $map, 'major', $this->admin);

        $this->assertSame(5, $r['rows']);
        $this->assertSame(2, ExternalTransaction::count());
        $this->assertSame(2, $r['skipped'], 'bad amount and missing id');
        $this->assertCount(2, $r['errors']);
        $one = ExternalTransaction::where('provider_transaction_id', '1001')->firstOrFail();
        $this->assertSame([123450, 'refunded', 'csv_import', null], [$one->amount_minor, $one->status, $one->source, $one->verified_at]);
        $this->assertSame('failed', ExternalTransaction::where('provider_transaction_id', '1002')->value('status'));
        $this->assertSame(0, Order::count());

        $again = $imp->import($this->pmA, $path, $map, 'major', $this->admin);
        $this->assertSame(2, ExternalTransaction::count(), 're-importing the same file creates nothing new');
        $this->assertSame(0, $again['created']);
    }

    public function test_csv_rows_never_override_a_row_verified_by_the_provider_api(): void
    {
        $this->pmTx('2001', $this->pmA, ['amount_cents' => 5000]);
        $this->svc()->lookup($this->pmA, '2001');
        $path = $this->csv("id,amount,status\n2001,999,failed\n");

        $r = app(ExternalTransactionCsvImporter::class)->import($this->pmA, $path, ['provider_transaction_id' => 0, 'amount' => 1, 'status' => 2], 'major');

        $this->assertSame(1, $r['skipped']);
        $row = ExternalTransaction::firstOrFail();
        $this->assertSame([5000, 'paid'], [$row->amount_minor, $row->status]);
        $this->assertSame('api_lookup', $row->source);
    }

    public function test_csv_minor_units_required_mapping_and_size_limits(): void
    {
        $imp = app(ExternalTransactionCsvImporter::class);
        $path = $this->csv("id,amount\n3001,15050\n");
        $imp->import($this->pmA, $path, ['provider_transaction_id' => 0, 'amount' => 1], 'minor');
        $this->assertSame(15050, ExternalTransaction::where('provider_transaction_id', '3001')->value('amount_minor'));

        try {
            $imp->import($this->pmA, $path, ['provider_transaction_id' => 0], 'major');
            $this->fail('amount mapping is required');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Amount', $e->errors()['file'][0]);
        }

        $big = "id,amount\n".implode("\n", array_map(fn ($i) => "$i,1", range(1, ExternalTransactionCsvImporter::MAX_ROWS + 1)))."\n";
        $this->expectException(ValidationException::class);
        $imp->inspect($this->csv($big));
    }

    public function test_csv_upload_preview_and_confirm_over_http(): void
    {
        $file = UploadedFile::fake()->createWithContent('payments.csv', "Transaction ID,Amount,Status\n4001,10.00,paid\n4002,20.00,failed\n");

        $preview = $this->actingAs($this->admin)->post(route('admin.payments.imported.csv'), ['account_id' => $this->pmA->id, 'file' => $file])->assertOk();
        $preview->assertSee('Match the columns')->assertSee('4001')->assertSee('Nothing has been saved yet');
        $this->assertSame(0, ExternalTransaction::count(), 'the preview saves nothing');
        $token = $preview->viewData('token');

        $this->actingAs($this->admin)->post(route('admin.payments.imported.csv.import'), ['account_id' => $this->pmA->id, 'token' => $token, 'amount_unit' => 'major', 'map' => ['provider_transaction_id' => 0, 'amount' => 1, 'status' => 2]])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame(2, ExternalTransaction::count());
        Storage::disk('local')->assertMissing('payment-imports/'.$token.'.csv');

        // import needs its own permission
        $viewer = $this->userWith(['payments.accounts.view']);
        $this->actingAs($viewer)->post(route('admin.payments.imported.csv'), ['account_id' => $this->pmA->id, 'file' => UploadedFile::fake()->createWithContent('a.csv', "id,amount\n1,1\n")])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.payments.imported.lookup'), ['account_id' => $this->pmA->id, 'transaction_id' => '1'])->assertForbidden();
    }

    /* ------------------------------------------------------------------------- permissions and scope */

    public function test_agents_and_managers_without_account_access_cannot_open_provider_transactions(): void
    {
        $this->pmTx('800', $this->pmA);
        $tx = $this->svc()->lookup($this->pmA, '800');
        $agent = $this->userWith(['payments.transactions.view', 'orders.view']);

        $this->actingAs($agent)->get(route('admin.payments.transactions'))->assertOk();       // their own scoped payments still work
        $this->actingAs($agent)->get(route('admin.payments.imported'))->assertForbidden();
        $this->actingAs($agent)->get(route('admin.payments.imported.show', $tx))->assertForbidden();
        $this->actingAs($agent)->post(route('admin.payments.sync', $this->pmA))->assertForbidden();
        $this->actingAs($agent)->get(route('admin.payments.transactions'))->assertDontSee('Imported from provider');

        $this->actingAs($this->admin)->get(route('admin.payments.imported.show', $tx))->assertOk()->assertSee('Not linked to any TKT House order');
    }

    /* ------------------------------------------------------------------------- sync */

    public function test_sync_refreshes_known_records_logs_the_run_and_is_idempotent(): void
    {
        $this->pmTx('810', $this->pmA, ['pending' => true, 'success' => false]);
        $ext = $this->svc()->lookup($this->pmA, '810');
        $this->assertSame('pending', $ext->status);
        $this->paymobTx['810']['pending'] = false;
        $this->paymobTx['810']['success'] = true;                       // the provider moved on

        $run = app(ProviderSyncService::class)->run($this->pmA, 'manual', $this->admin);

        $this->assertSame(['succeeded', 1, 1, 0, false], [$run->status, $run->examined, $run->updated, $run->errors, $run->rate_limited]);
        $this->assertSame('paid', $ext->fresh()->status);
        $this->assertNotNull($run->finished_at);

        $second = app(ProviderSyncService::class)->run($this->pmA, 'scheduled');
        $this->assertSame([1, 0], [$second->examined, $second->updated], 'nothing changed → nothing updated');
        $this->assertSame(1, ExternalTransaction::count());
        $this->assertSame(2, ProviderSync::count());
        $this->assertSame(0, Order::count());
    }

    public function test_sync_settles_open_local_attempts_only_through_the_normal_settlement_rules(): void
    {
        $attempt = $this->paidAttempt($this->pmA, '820', 150.0, 'initiated');
        $attempt->forceFill(['created_at' => now()->subMinutes(10)])->save();
        $this->pmTx('820', $this->pmA, ['amount_cents' => 15000]);

        $run = app(ProviderSyncService::class)->run($this->pmA, 'manual', $this->admin);

        $this->assertSame('paid', $attempt->fresh()->status);
        $this->assertSame('paid', $attempt->order->fresh()->status);
        $this->assertSame(1, $run->updated);

        // a wrong amount at the provider is NOT settled
        $wrong = $this->paidAttempt($this->pmA, '821', 150.0, 'initiated');
        $wrong->forceFill(['created_at' => now()->subMinutes(10)])->save();
        $this->pmTx('821', $this->pmA, ['amount_cents' => 100]);
        app(ProviderSyncService::class)->run($this->pmA, 'manual', $this->admin);
        $this->assertNotSame('paid', $wrong->order->fresh()->status);
    }

    public function test_a_rate_limited_run_stops_cleanly_and_the_next_run_continues(): void
    {
        foreach (['831', '832'] as $id) {
            $this->pmTx($id, $this->pmA, ['pending' => true, 'success' => false]);
            $this->svc()->lookup($this->pmA, $id);
        }
        $this->providerRateLimit = true;
        $run = app(ProviderSyncService::class)->run($this->pmA, 'scheduled');
        $this->assertSame(['partial', true], [$run->status, $run->rate_limited]);
        $this->assertStringContainsString('rate limiting', (string) $run->message);

        $this->providerRateLimit = false;
        foreach (['831', '832'] as $id) {
            $this->paymobTx[$id]['pending'] = false;
            $this->paymobTx[$id]['success'] = true;
        }
        $next = app(ProviderSyncService::class)->run($this->pmA, 'scheduled');
        $this->assertSame(['succeeded', 2, 2], [$next->status, $next->examined, $next->updated]);
    }

    public function test_sync_is_skipped_where_there_is_nothing_official_to_call_and_a_failing_row_is_reported(): void
    {
        config(['payments.live_gateways.enabled' => false]);
        $this->assertSame('skipped', app(ProviderSyncService::class)->run($this->pmA)->status);
        config(['payments.live_gateways.enabled' => true]);

        $this->pmTx('840', $this->pmA);
        $row = $this->svc()->lookup($this->pmA, '840');
        unset($this->paymobTx['840']);                                    // the provider no longer returns it
        $run = app(ProviderSyncService::class)->run($this->pmA);
        $this->assertSame(['failed', 1], [$run->status, $run->errors]);
        $this->assertStringContainsString('840', (string) $run->message);
        $this->assertSame('paid', $row->fresh()->status, 'a failed refresh keeps the last known data');
    }

    public function test_sync_now_button_and_background_command_work_for_permitted_staff(): void
    {
        $this->pmTx('850', $this->pmA, ['pending' => true, 'success' => false]);
        $this->svc()->lookup($this->pmA, '850');

        $this->actingAs($this->admin)->post(route('admin.payments.sync', $this->pmA))->assertRedirect()->assertSessionHas('success');
        $this->assertSame(1, ProviderSync::where('trigger', 'manual')->count());
        $this->artisan('payments:sync', ['--account' => $this->pmA->id])->expectsOutputToContain('account #'.$this->pmA->id)->assertExitCode(0);
        $this->assertSame(1, ProviderSync::where('trigger', 'scheduled')->count());
        $this->actingAs($this->admin)->get(route('admin.payments.imported'))->assertSee('Last sync')->assertSee('Sync now');
    }

    /* ------------------------------------------------------------------------- capabilities (no fake buttons) */

    public function test_capabilities_are_honest_and_the_ui_offers_no_sync_all(): void
    {
        foreach (['paymob', 'fawaterak'] as $p) {
            $this->assertFalse(ProviderCapabilities::canListAll($p), "$p has no official listing API");
            $this->assertSame(ProviderCapabilities::ND, ProviderCapabilities::status($p, 'list_transactions'));
            $this->assertTrue(ProviderCapabilities::supports($p, 'transaction_details'));
        }
        $this->assertSame(ProviderCapabilities::MAN, ProviderCapabilities::status('fawaterak', 'refund'));
        $this->assertSame(ProviderCapabilities::NO, ProviderCapabilities::status('fawaterak', 'void'));
        $this->assertSame(ProviderCapabilities::API, ProviderCapabilities::status('paymob', 'void'));

        $page = $this->actingAs($this->admin)->get(route('admin.payments.imported'))->assertOk();
        $page->assertSee('Neither provider offers an API to list all transactions')->assertSee('Not documented by the provider')->assertSee('Manual / provider dashboard only');
        $page->assertDontSee('Sync all')->assertDontSee('Sync All')->assertDontSee('Import all');
    }

    /* ------------------------------------------------------------------------- reports keep the two data sets apart */

    public function test_reports_never_add_imported_rows_to_the_local_totals(): void
    {
        $local = $this->paidAttempt($this->pmA, '900100', 150.0);
        $local->forceFill(['paid_at' => now()])->save();
        $this->pmTx('900100', $this->pmA);
        $this->svc()->lookup($this->pmA, '900100');                    // linked → already counted locally
        $this->pmTx('900200', $this->pmA, ['amount_cents' => 40000]);
        $this->svc()->lookup($this->pmA, '900200');                    // provider-only
        $from = now()->startOfDay()->subDay();
        $to = now()->endOfDay();
        $reports = app(PaymentReport::class);

        $totals = $reports->build($this->admin, $from, $to, true)['totals'];
        $imported = $reports->imported($from, $to);

        $this->assertSame(15000, $totals['collected'], 'imported rows are not in the local figures');
        $this->assertSame(1, $imported['provider_only']['paid_count']);
        $this->assertSame(40000, $imported['provider_only']['paid_volume']);
        $this->assertSame(1, $imported['already_in_local']);

        $prev = $reports->previous($this->admin, $from, $to);
        $this->assertSame(0, $prev['totals']['collected']);
        $page = $this->actingAs($this->admin)->get(route('admin.payments.index', ['from' => $from->toDateString(), 'to' => $to->toDateString()]))->assertOk();
        $page->assertSee('Compared with the previous period')->assertDontSee('not included in the figures above');   // the provider-imported section is no longer on the overview
    }

    /* ------------------------------------------------------------------------- legacy payments still render */

    public function test_legacy_payments_without_references_or_accounts_render_in_list_and_details(): void
    {
        $order = $this->makePendingOrder($this->admin, null, 1, 80, 'paymob_card');
        $legacy = PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'paymob', 'method_code' => 'paymob_card', 'amount' => 80, 'currency' => 'EGP', 'status' => 'paid', 'transaction_id' => '555001', 'paid_at' => now()]);

        $this->actingAs($this->admin)->get(route('admin.payments.transactions'))->assertOk()->assertSee('legacy')->assertSee('555001')->assertSee('Legacy configuration');
        $this->actingAs($this->admin)->get(route('admin.payments.transactions', ['pm' => 'paymob_card', 'txn' => '5550', 'order' => $order->order_number]))->assertOk()->assertSee('555001');
        $show = $this->actingAs($this->admin)->get(route('admin.payments.transactions.show', $legacy))->assertOk();
        $show->assertSee('Status history')->assertSee('Refund history')->assertSee('What can be done here')->assertSee('Cancel');
    }

    /* ------------------------------------------------------------------------- final security & regression checks */

    public function test_a_csv_that_says_paid_never_changes_a_local_payment_or_order(): void
    {
        $attempt = $this->paidAttempt($this->pmA, '7001', 150.0, 'initiated');
        $statuses = [$attempt->status, $attempt->order->status, $attempt->paid_at];
        $path = $this->csv("Transaction ID,Amount,Status,Merchant Order ID\n7001,150.00,Success,M-7001\n");

        app(ExternalTransactionCsvImporter::class)->import($this->pmA, $path, $this->csvMap($path), 'major', $this->admin);

        $row = ExternalTransaction::where('provider_transaction_id', '7001')->firstOrFail();
        $this->assertSame(['paid', null], [$row->status, $row->verified_at], 'recorded as an unverified provider record');
        $this->assertSame($attempt->id, $row->matched_attempt_id, 'exact match is only a link');
        $fresh = $attempt->fresh();
        $this->assertSame($statuses, [$fresh->status, $fresh->order->status, $fresh->paid_at], 'a spreadsheet can never settle a payment');
        $this->assertSame(0, \App\Models\IssuedTicket::count());
    }

    public function test_csv_upload_rejects_wrong_empty_oversized_and_hostile_files_and_escapes_what_it_shows(): void
    {
        $post = fn ($file) => $this->actingAs($this->admin)->post(route('admin.payments.imported.csv'), ['account_id' => $this->pmA->id, 'file' => $file]);

        $post(UploadedFile::fake()->createWithContent('shell.php', "<?php system('id');"))->assertSessionHasErrors('file');          // wrong type
        $post(UploadedFile::fake()->createWithContent('empty.csv', ''))->assertSessionHasErrors('file');                                // empty
        $post(UploadedFile::fake()->create('huge.csv', 6000, 'text/csv'))->assertSessionHasErrors('file');                              // > 5 MB
        $post(UploadedFile::fake()->createWithContent('one-column.csv', "only\n1\n2\n"))->assertSessionHasErrors('file');             // no usable header
        $this->assertSame(0, ExternalTransaction::count());
        $this->assertSame([], Storage::disk('local')->files('payment-imports'), 'rejected files are not kept');

        // formulas and markup are stored as plain text and escaped when displayed
        $path = $this->csv("Transaction ID,Amount,Status,Payment Method,Email\n8001,10,paid,\"<script>alert(1)</script>\",\"=HYPERLINK(\"\"http://evil\"\")\"\n");
        app(ExternalTransactionCsvImporter::class)->import($this->pmA, $path, $this->csvMap($path), 'major', $this->admin);
        $html = $this->actingAs($this->admin)->get(route('admin.payments.imported'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_duplicate_prevention_is_per_provider_account_and_never_across_accounts(): void
    {
        $path = $this->csv("id,amount\n9001,10\n9001,10\n9002,20\n");
        $map = ['provider_transaction_id' => 0, 'amount' => 1];
        $imp = app(ExternalTransactionCsvImporter::class);

        $imp->import($this->pmA, $path, $map, 'major');
        $imp->import($this->pmA, $path, $map, 'major');
        $this->assertSame(2, ExternalTransaction::where('integration_account_id', $this->pmA->id)->count());

        $imp->import($this->pmB, $path, $map, 'major');                       // same ids under another merchant account are different records
        $this->assertSame(4, ExternalTransaction::count());
        $this->assertSame([2, 2], [ExternalTransaction::where('integration_account_id', $this->pmA->id)->count(), ExternalTransaction::where('integration_account_id', $this->pmB->id)->count()]);
    }

    public function test_the_csv_import_token_cannot_be_used_to_read_arbitrary_files(): void
    {
        $this->actingAs($this->admin)->post(route('admin.payments.imported.csv.import'), ['account_id' => $this->pmA->id, 'token' => '../../.env', 'amount_unit' => 'major', 'map' => ['provider_transaction_id' => 0, 'amount' => 1]])->assertSessionHasErrors('token');
        $this->actingAs($this->admin)->post(route('admin.payments.imported.csv.import'), ['account_id' => $this->pmA->id, 'token' => (string) \Illuminate\Support\Str::uuid(), 'amount_unit' => 'major', 'map' => ['provider_transaction_id' => 0, 'amount' => 1]])->assertNotFound();
    }
}
