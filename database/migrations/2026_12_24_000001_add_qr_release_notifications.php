<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * QR Release e-mails: per-ticket notification state, per-event Test / Live mode and a test sandbox.
 *
 * Every event starts in TEST mode: nothing is e-mailed to customers until an admin deliberately activates Live.
 * Existing tickets are NOT eligible (qr_locked_at_issue = false), so deploying this never sends anything for old tickets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issued_tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('order_item_id');
            $table->boolean('qr_locked_at_issue')->default(false)->after('holder_email_changes');
            $table->string('qr_notice_status', 16)->default('not_eligible')->after('qr_locked_at_issue');   // not_eligible | pending | sent | failed
            $table->timestamp('qr_notice_eligible_at')->nullable()->after('qr_notice_status');
            $table->timestamp('qr_notice_sent_at')->nullable()->after('qr_notice_eligible_at');
            $table->unsignedTinyInteger('qr_notice_attempts')->default(0)->after('qr_notice_sent_at');
            $table->string('qr_notice_error', 255)->nullable()->after('qr_notice_attempts');
            $table->string('qr_notice_claim', 36)->nullable()->after('qr_notice_error');
            $table->timestamp('qr_notice_claimed_at')->nullable()->after('qr_notice_claim');
            $table->timestamp('qr_notice_skipped_at')->nullable()->after('qr_notice_claimed_at');
            $table->index(['event_id', 'qr_notice_status'], 'issued_tickets_event_notice_index');
        });

        // Which event a ticket belongs to, denormalised so per-event queries need no joins (tickets already carry it).
        DB::statement('UPDATE issued_tickets SET event_id = (SELECT tickets.event_id FROM tickets WHERE tickets.ticket_number = issued_tickets.ticket_number)');

        Schema::table('events', function (Blueprint $table) {
            $table->string('qr_release_mode', 8)->default('test')->after('entry_changed_at');
            $table->timestamp('qr_release_activated_at')->nullable()->after('qr_release_mode');
            $table->unsignedBigInteger('qr_release_activated_by')->nullable()->after('qr_release_activated_at');
            $table->json('qr_sandbox')->nullable()->after('qr_release_activated_by');
        });

        Permission::findOrCreate('events.qr-notifications.manage', 'web');
        $new = Permission::where('name', 'events.qr-notifications.manage')->value('id');
        $old = Permission::where('name', 'events.qr-visibility.manage')->where('guard_name', 'web')->value('id');
        if ($old) {
            foreach (DB::table('role_has_permissions')->where('permission_id', $old)->pluck('role_id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $new, 'role_id' => $roleId]);
            }
        }
        foreach (Role::query()->get() as $role) {
            if (strtolower((string) preg_replace('/[^a-z0-9]/i', '', $role->name)) === 'superadmin') {
                $role->givePermissionTo('events.qr-notifications.manage');
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'events.qr-notifications.manage')->delete();
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn(['qr_release_mode', 'qr_release_activated_at', 'qr_release_activated_by', 'qr_sandbox']));
        Schema::table('issued_tickets', function (Blueprint $t) {
            $t->dropIndex('issued_tickets_event_notice_index');
            $t->dropColumn(['event_id', 'qr_locked_at_issue', 'qr_notice_status', 'qr_notice_eligible_at', 'qr_notice_sent_at', 'qr_notice_attempts', 'qr_notice_error', 'qr_notice_claim', 'qr_notice_claimed_at', 'qr_notice_skipped_at']);
        });
    }
};
