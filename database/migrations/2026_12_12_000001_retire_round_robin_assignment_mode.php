<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Round-robin assignment was removed: a stored "round_robin" mode becomes "manual" (nobody is assigned automatically). */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->where('key', 'order_assignment_mode')->where('value', 'round_robin')->update(['value' => 'manual', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // not restorable: the mode no longer exists
    }
};
