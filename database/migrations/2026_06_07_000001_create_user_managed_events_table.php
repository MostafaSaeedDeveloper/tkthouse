<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_managed_events')) {
            Schema::create('user_managed_events', function (Blueprint $table) {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->primary(['user_id', 'event_id']);
            });
        }

        // Migrate existing single managed_event_id data to pivot table
        if (Schema::hasColumn('users', 'managed_event_id')) {
            \DB::table('users')
                ->whereNotNull('managed_event_id')
                ->orderBy('id')
                ->each(function ($user) {
                    \DB::table('user_managed_events')->insertOrIgnore([
                        'user_id'  => $user->id,
                        'event_id' => $user->managed_event_id,
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_managed_events');
    }
};
