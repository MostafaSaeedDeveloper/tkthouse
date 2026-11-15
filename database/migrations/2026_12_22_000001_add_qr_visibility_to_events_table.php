<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event control of when customers may see their ticket QR codes.
 *
 * Existing events default to "immediate", so nothing changes for them until an admin opts in.
 * An override ('show' | 'hide') sits on top of any mode and wins over it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('qr_visibility_mode', 20)->default('immediate')->after('requires_booking_approval');
            $table->unsignedInteger('qr_reveal_minutes_before')->nullable()->after('qr_visibility_mode');
            $table->string('qr_override', 10)->nullable()->after('qr_reveal_minutes_before');
            $table->unsignedBigInteger('qr_visibility_changed_by')->nullable()->after('qr_override');
            $table->timestamp('qr_visibility_changed_at')->nullable()->after('qr_visibility_changed_by');
        });

        // QR payloads are looked up on every scan. The column is TEXT, which MySQL can only index by prefix.
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::getConnection()->statement('CREATE INDEX tickets_qr_payload_index ON tickets (qr_payload(64))');
        } else {
            Schema::table('tickets', fn (Blueprint $table) => $table->index('qr_payload', 'tickets_qr_payload_index'));
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_qr_payload_index');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['qr_visibility_mode', 'qr_reveal_minutes_before', 'qr_override', 'qr_visibility_changed_by', 'qr_visibility_changed_at']);
        });
    }
};
