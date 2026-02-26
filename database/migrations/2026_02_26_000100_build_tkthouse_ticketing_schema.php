<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone')->nullable()->index()->after('email');
            $table->string('avatar')->nullable()->after('password');
            $table->enum('status', ['active', 'suspended', 'pending'])->default('active')->after('avatar');
            $table->decimal('wallet_balance', 12, 2)->default(0)->after('status');
            $table->string('referral_code')->nullable()->unique()->after('wallet_balance');
            $table->foreignId('referred_by_user_id')->nullable()->after('referral_code')->constrained('users')->nullOnDelete();
            $table->timestamp('last_login_at')->nullable()->after('referred_by_user_id');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
            $table->softDeletes();
        });

        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('city');
            $table->string('address');
            $table->string('map_url')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('view_key');
            $table->string('preview_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('fees_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->enum('scope', ['global', 'event', 'ticket_type', 'payment_method']);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('description');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->enum('status', ['draft', 'published', 'hidden', 'cancelled'])->default('draft');
            $table->string('cover_image')->nullable();
            $table->json('gallery')->nullable();
            $table->foreignId('ticket_template_id')->nullable()->constrained('ticket_templates')->nullOnDelete();
            $table->foreignId('fees_policy_id')->nullable()->constrained('fees_policies')->nullOnDelete();
            $table->string('currency', 3)->default('EGP');
            $table->timestamps();
        });

        Schema::create('ticket_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('qty_total');
            $table->unsignedInteger('qty_sold')->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->unsignedInteger('min_per_order')->default(1);
            $table->unsignedInteger('max_per_order')->default(10);
            $table->foreignId('fees_policy_id')->nullable()->constrained('fees_policies')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('driver_key');
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->foreignId('fees_policy_id')->nullable()->constrained('fees_policies')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_no')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('fees_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'cancelled', 'refunded', 'expired'])->default('pending');
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->enum('payment_status', ['unpaid', 'paid', 'failed', 'refunded'])->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_subtotal', 12, 2);
            $table->decimal('line_fees', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_ref')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EGP');
            $table->enum('status', ['initiated', 'succeeded', 'failed', 'refunded'])->default('initiated');
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('qr_token')->unique();
            $table->enum('status', ['valid', 'used', 'cancelled', 'refunded'])->default('valid');
            $table->string('attendee_name')->nullable();
            $table->string('attendee_phone')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('checkins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checked_in_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('gate')->nullable();
            $table->string('device_id')->nullable();
            $table->enum('result', ['success', 'duplicate', 'invalid']);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('fees_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fees_policy_id')->constrained('fees_policies')->cascadeOnDelete();
            $table->enum('type', ['fixed', 'percent']);
            $table->decimal('value', 12, 2);
            $table->enum('applies_to', ['order', 'ticket']);
            $table->decimal('min_amount', 12, 2)->nullable();
            $table->decimal('max_amount', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 12, 2);
            $table->enum('reason', ['refund', 'referral', 'recharge', 'purchase', 'adjustment']);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'qualified', 'paid'])->default('pending');
            $table->decimal('reward_amount', 12, 2)->default(0);
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->enum('type', ['percent', 'fixed']);
            $table->decimal('value', 12, 2);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('applies_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('fees_rules');
        Schema::dropIfExists('checkins');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('events');
        Schema::dropIfExists('fees_policies');
        Schema::dropIfExists('ticket_templates');
        Schema::dropIfExists('venues');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('referred_by_user_id');
            $table->dropColumn([
                'phone',
                'avatar',
                'status',
                'wallet_balance',
                'referral_code',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
