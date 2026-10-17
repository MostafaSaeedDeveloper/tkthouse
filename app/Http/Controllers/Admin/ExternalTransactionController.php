<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalTransaction;
use App\Models\IntegrationAccount;
use App\Orders\OrderAccess;
use App\Services\Payments\External\ExternalTransactionCsvImporter;
use App\Services\Payments\External\ExternalTransactionService;
use App\Services\Payments\External\ProviderSyncService;
use App\Services\Payments\ProviderCapabilities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Provider-side transactions that TKT House did not create (lookup by id, CSV import) + provider sync.
 * They have no order, so they are visible only to staff who may see merchant accounts; they are never an order and never settle anything.
 */
class ExternalTransactionController extends Controller
{
    private function accounts()
    {
        return IntegrationAccount::query()->whereIn('provider', ['paymob', 'fawaterak', 'fake'])->whereNull('archived_at')->orderBy('provider')->orderBy('name')->get(['id', 'name', 'provider', 'environment', 'enabled']);
    }

    private function authorizeView(Request $request): void
    {
        abort_unless(TransactionController::canSeeAccounts($request->user()) || $request->user()->can('payments.transactions.import'), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeView($request);
        $f = [
            'provider' => (string) $request->query('provider', ''), 'account' => (int) $request->query('account', 0), 'status' => (string) $request->query('status', ''),
            'method' => trim((string) $request->query('pm', '')), 'txn' => trim((string) $request->query('txn', '')), 'ref' => trim((string) $request->query('ref', '')),
            'link' => (string) $request->query('link', ''),
        ];
        $esc = fn (string $v) => '%'.str_replace(['%', '_'], ['\%', '\_'], $v).'%';

        $rows = ExternalTransaction::query()->with(['account:id,name,provider', 'attempt:id,order_id,internal_reference', 'attempt.order:id,order_number'])
            ->when($f['provider'] !== '', fn ($q) => $q->where('provider', $f['provider']))
            ->when($f['account'] > 0, fn ($q) => $q->where('integration_account_id', $f['account']))
            ->when($f['status'] !== '', fn ($q) => $q->where('status', $f['status']))
            ->when($f['method'] !== '', fn ($q) => $q->where('payment_method', 'like', $esc($f['method'])))
            ->when($f['txn'] !== '', fn ($q) => $q->where('provider_transaction_id', 'like', $esc($f['txn'])))
            ->when($f['ref'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('provider_reference', 'like', $esc($f['ref']))->orWhere('provider_order_id', 'like', $esc($f['ref']))))
            ->when($f['link'] === 'linked', fn ($q) => $q->whereNotNull('matched_attempt_id'))
            ->when($f['link'] === 'unlinked', fn ($q) => $q->whereNull('matched_attempt_id'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('provider_created_at', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('provider_created_at', '<=', $request->string('to')))
            ->orderByDesc('provider_created_at')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('admin.payments.imported', [
            'rows' => $rows, 'filters' => $f, 'accounts' => $this->accounts(), 'syncs' => ProviderSyncService::latestByAccount(),
            'canImport' => $request->user()->can('payments.transactions.import'), 'canSync' => $request->user()->can('payments.transactions.inquire'),
            'canSeeCustomer' => TransactionController::canSeeAccounts($request->user()),
        ]);
    }

    public function show(Request $request, ExternalTransaction $external)
    {
        $this->authorizeView($request);
        $external->load(['account', 'attempt.order']);

        return view('admin.payments.imported-show', ['tx' => $external, 'canInquire' => $request->user()->can('payments.transactions.inquire'), 'canSeeCustomer' => TransactionController::canSeeAccounts($request->user())]);
    }

    /** Import ONE transaction by its provider id, using the merchant account the admin chose. */
    public function lookup(Request $request, ExternalTransactionService $service)
    {
        $data = $request->validate(['account_id' => ['required', 'integer', 'exists:integration_accounts,id'], 'transaction_id' => ['required', 'string', 'max:120']]);
        $account = IntegrationAccount::query()->findOrFail($data['account_id']);

        try {
            $tx = $service->lookup($account, $data['transaction_id'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
        activity('payments')->performedOn($tx)->causedBy($request->user())->withProperties(['provider' => $tx->provider, 'account' => $account->id, 'source' => 'api_lookup'])->log('External transaction imported by id');

        return redirect()->route('admin.payments.imported.show', $tx)->with('success', $tx->wasRecentlyCreated || $tx->created_at->gt(now()->subSeconds(5)) ? 'Transaction fetched from the provider and saved.' : 'Transaction refreshed from the provider.');
    }

    public function verify(Request $request, ExternalTransaction $external, ExternalTransactionService $service)
    {
        $this->authorizeView($request);
        try {
            $service->lookup($external->account, $external->provider_transaction_id, $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', (string) collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Checked with the provider: the record is now verified and up to date.');
    }

    /** Step 1 of the CSV import: store the file privately and show the column mapping + a preview. Nothing is imported yet. */
    public function csvUpload(Request $request, ExternalTransactionCsvImporter $importer)
    {
        $data = $request->validate(['account_id' => ['required', 'integer', 'exists:integration_accounts,id'], 'file' => ['required', 'file', 'max:5120', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel']]);
        $account = IntegrationAccount::query()->findOrFail($data['account_id']);
        $token = (string) Str::uuid();
        $path = $request->file('file')->storeAs('payment-imports', $token.'.csv', 'local');

        try {
            $info = $importer->inspect(Storage::disk('local')->path($path));
        } catch (ValidationException $e) {
            Storage::disk('local')->delete($path);

            return back()->withErrors($e->errors());
        }

        return view('admin.payments.imported-csv', ['account' => $account, 'token' => $token, 'info' => $info, 'mapping' => $importer->suggestMapping($info['headers']), 'fields' => ExternalTransactionCsvImporter::FIELDS, 'name' => $request->file('file')->getClientOriginalName()]);
    }

    public function csvImport(Request $request, ExternalTransactionCsvImporter $importer)
    {
        $data = $request->validate(['account_id' => ['required', 'integer', 'exists:integration_accounts,id'], 'token' => ['required', 'uuid'], 'amount_unit' => ['required', 'in:major,minor'], 'map' => ['required', 'array']]);
        $path = Storage::disk('local')->path('payment-imports/'.$data['token'].'.csv');
        abort_unless(is_file($path), 404);
        $account = IntegrationAccount::query()->findOrFail($data['account_id']);

        try {
            $report = $importer->import($account, $path, $data['map'], $data['amount_unit'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
        Storage::disk('local')->delete('payment-imports/'.$data['token'].'.csv');
        activity('payments')->causedBy($request->user())->withProperties(['account' => $account->id] + collect($report)->except('errors')->all())->log('External transactions imported from CSV');

        return redirect()->route('admin.payments.imported', ['account' => $account->id])->with('success', "CSV imported: {$report['created']} new, {$report['updated']} updated, {$report['skipped']} skipped.")
            ->with($report['errors'] ? 'warning' : 'info', $report['errors'] ? 'Some rows were skipped: '.implode('; ', array_slice($report['errors'], 0, 8)).(count($report['errors']) > 8 ? '…' : '') : 'All rows were imported. Imported rows are unverified until checked with the provider.');
    }

    public function sync(Request $request, IntegrationAccount $account, ProviderSyncService $sync)
    {
        abort_unless(TransactionController::canSeeAccounts($request->user()), 403);
        abort_unless(ProviderCapabilities::canLookup($account->provider), 404);
        $run = $sync->run($account, 'manual', $request->user(), 100);

        $text = $run->status === 'skipped' ? 'Sync skipped.' : "Sync finished: {$run->status} — examined {$run->examined}, updated {$run->updated}, errors {$run->errors}.";

        return back()->with($run->status === 'succeeded' ? 'success' : ($run->status === 'skipped' ? 'info' : 'warning'), $text.($run->message ? ' '.$run->message : ''));
    }
}
