<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive. `roles.name` stays the stable key the code uses; these columns only add something people read.
 * Existing roles keep their name, permissions and user assignments untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('permission.table_names.roles', 'roles');

        Schema::table($table, function (Blueprint $t) use ($table) {
            if (! Schema::hasColumn($table, 'display_name')) {
                $t->string('display_name', 80)->nullable();
                $t->string('description', 255)->nullable();
                $t->boolean('is_system')->default(false);
            }
        });

        foreach (\App\Support\RoleLabel::SYSTEM as $key => [$label, $description]) {
            DB::table($table)->where('name', $key)->where('guard_name', 'web')->update([
                'display_name' => DB::raw("COALESCE(display_name, ".DB::getPdo()->quote($label).')'),
                'description' => DB::raw("COALESCE(description, ".DB::getPdo()->quote($description).')'),
                'is_system' => true,
            ]);
        }
    }

    public function down(): void
    {
        $table = config('permission.table_names.roles', 'roles');

        Schema::table($table, function (Blueprint $t) use ($table) {
            foreach (['display_name', 'description', 'is_system'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $t->dropColumn($column);
                }
            }
        });
    }
};
