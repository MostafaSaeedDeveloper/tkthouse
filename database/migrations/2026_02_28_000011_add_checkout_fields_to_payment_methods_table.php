<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('checkout_label')->nullable()->after('name');
            $table->string('checkout_icon')->nullable()->after('checkout_label');
            $table->string('checkout_description')->nullable()->after('checkout_icon');
        });

        $defaultIcons = [
            'visa' => '💳',
            'wallet' => '👛',
            'paymob_card' => '💳',
            'paymob_wallet' => '📱',
            'paymob_apple_pay' => '🍎',
        ];

        DB::table('payment_methods')->orderBy('id')->get()->each(function ($method) use ($defaultIcons) {
            DB::table('payment_methods')
                ->where('id', $method->id)
                ->update([
                    'checkout_label' => $method->name,
                    'checkout_icon' => $defaultIcons[$method->code] ?? '💰',
                    'checkout_description' => null,
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['checkout_label', 'checkout_icon', 'checkout_description']);
        });
    }
};
