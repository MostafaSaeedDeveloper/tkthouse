<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Several agents per order. `orders.assigned_user_id` stays the PRIMARY assignee (sales attribution, reports, payment routing);
 * this table lists every agent who may see and work the order, the primary included. Existing assignments are copied over.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_assignees')) {
            Schema::create('order_assignees', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('assigned_by_user_id')->nullable();
                $table->timestamps();
                $table->unique(['order_id', 'user_id']);
                $table->index('user_id');
            });
        }

        DB::table('orders')->whereNotNull('assigned_user_id')->select('id', 'assigned_user_id', 'assigned_by_user_id', 'assigned_at')->orderBy('id')->chunk(500, function ($orders) {
            DB::table('order_assignees')->insertOrIgnore($orders->map(fn ($o) => [
                'order_id' => $o->id, 'user_id' => $o->assigned_user_id, 'assigned_by_user_id' => $o->assigned_by_user_id,
                'created_at' => $o->assigned_at ?? now(), 'updated_at' => $o->assigned_at ?? now(),
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_assignees');
    }
};
