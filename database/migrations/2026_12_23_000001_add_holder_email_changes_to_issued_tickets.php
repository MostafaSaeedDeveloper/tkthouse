<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issued_tickets', function (Blueprint $table) {
            // How many times the buyer moved this ticket to another e-mail (capped by TicketHolderService::MAX_EMAIL_CHANGES).
            $table->unsignedTinyInteger('holder_email_changes')->default(0)->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('issued_tickets', fn (Blueprint $table) => $table->dropColumn('holder_email_changes'));
    }
};
