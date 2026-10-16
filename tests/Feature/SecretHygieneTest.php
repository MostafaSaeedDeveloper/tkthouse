<?php

namespace Tests\Feature;

use Database\Seeders\FawaterakPaymentMethodsSeeder;
use Database\Seeders\PaymobPaymentMethodsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Real credentials never live in source. (A fingerprint, not the value, of the key that leaked earlier is kept here so it cannot come back.) */
class SecretHygieneTest extends TestCase
{
    use RefreshDatabase;

    /** sha256 of the Fawaterak API key that was committed in the past. It must be rotated at the provider; this only stops re-introduction. */
    private const LEAKED_FINGERPRINTS = ['7ae270ef307a30226e189c43ecaec5ec65459515addfb9ef287dd105a05dd09d'];

    private function sourceFiles(): \Generator
    {
        foreach (['app', 'config', 'database', 'routes', 'tests', 'docs', 'resources/views', 'bootstrap'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && preg_match('/\.(php|md|json|js|txt|yml|yaml|env|example)$/', $f->getFilename()) && $f->getSize() < 1_500_000) {
                    yield $f->getPathname();
                }
            }
        }
        foreach (['README.md', '.env.example', 'phpunit.xml'] as $f) {
            if (is_file(base_path($f))) {
                yield base_path($f);
            }
        }
    }

    public function test_the_previously_leaked_key_does_not_appear_anywhere_in_source(): void
    {
        $hits = [];
        foreach ($this->sourceFiles() as $path) {
            preg_match_all('/[A-Za-z0-9_\-+\/=.]{30,}/', (string) file_get_contents($path), $m);
            foreach (array_unique($m[0]) as $token) {
                if (in_array(hash('sha256', $token), self::LEAKED_FINGERPRINTS, true)) {
                    $hits[] = str_replace(base_path().'/', '', $path);
                }
            }
        }
        $this->assertSame([], $hits, 'a known-leaked credential is back in source (file names only shown)');
    }

    public function test_seeders_contain_no_credential_looking_literals(): void
    {
        foreach (glob(database_path('seeders/*.php')) as $file) {
            $code = (string) file_get_contents($file);
            preg_match_all("/['\"]([A-Za-z0-9_\-+\/=]{32,})['\"]/", $code, $m);
            $suspicious = array_filter($m[1], fn ($v) => ! preg_match('/^(public|storage)\//', $v) && ! str_contains($v, '/'));
            $this->assertSame([], array_values($suspicious), basename($file).' contains a long secret-looking literal');
        }
    }

    public function test_payment_seeders_never_overwrite_delete_or_store_credentials_from_code(): void
    {
        config(['services.fawaterak.api_key' => '', 'services.paymob.api_key' => '']);
        DB::table('payment_methods')->updateOrInsert(['code' => 'visa'], ['name' => 'Legacy Visa', 'provider' => 'paymob', 'is_active' => true, 'config' => json_encode(['api_key' => 'OPERATOR-CONFIGURED']), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payment_methods')->updateOrInsert(['code' => 'fawaterak_card'], ['name' => 'Configured Card', 'provider' => 'fawaterak', 'is_active' => true, 'config' => json_encode(['api_key' => 'OPERATOR-CONFIGURED', 'provider_key' => 'X.1']), 'created_at' => now(), 'updated_at' => now()]);

        $this->seed(FawaterakPaymentMethodsSeeder::class);
        $this->seed(PaymobPaymentMethodsSeeder::class);

        $this->assertSame('Legacy Visa', DB::table('payment_methods')->where('code', 'visa')->value('name'), 'legacy codes are never deleted');
        $kept = DB::table('payment_methods')->where('code', 'fawaterak_card')->first();
        $this->assertSame('Configured Card', $kept->name);
        $this->assertStringContainsString('OPERATOR-CONFIGURED', $kept->config, 'an existing method is never overwritten');

        foreach (['fawaterak_wallet', 'paymob_card', 'paymob_wallet'] as $code) {
            $row = DB::table('payment_methods')->where('code', $code)->first();
            $this->assertNotNull($row, "{$code} created when missing");
            $this->assertFalse((bool) $row->is_active, 'created inactive when the environment has no credentials');
            $this->assertSame('', json_decode($row->config, true)['api_key']);
        }
    }
}
