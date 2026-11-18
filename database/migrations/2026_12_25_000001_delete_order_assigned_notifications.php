<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Assignment no longer produces bell notifications: clear the ones already stored.
        DB::table('notifications')->where('data', 'like', '%"type":"order.assigned"%')->delete();
    }

    public function down(): void
    {
    }
};
