<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('provider')->default('manual');
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        DB::table('payment_methods')->insert([
            [
                'name' => 'Visa / Card',
                'code' => 'visa',
                'provider' => 'manual',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Wallet',
                'code' => 'wallet',
                'provider' => 'manual',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Paymob',
                'code' => 'paymob',
                'provider' => 'paymob',
                'is_active' => false,
                'config' => json_encode([
                    'api_key' => '',
                    'iframe_id' => '',
                    'integration_id_card' => '',
                    'integration_id_wallet' => '',
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
