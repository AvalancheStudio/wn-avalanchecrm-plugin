<?php

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;
use Winter\Storm\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        if (Schema::hasColumn('avalanchestudio_avalanchecrm_invoices', 'internal_status')) {
            return;
        }

        // 1. Add the new internal_status column
        Schema::table('avalanchestudio_avalanchecrm_invoices', function (Blueprint $table) {
            $table->string('internal_status')->default('draft')->after('status');
        });

        // 2. Migrate existing data:
        //    - 'draft'     Ã¢â€ â€™ internal_status='draft',  status='outstanding'
        //    - 'sent'      Ã¢â€ â€™ internal_status='sent',   status='outstanding'
        //    - 'paid'      Ã¢â€ â€™ internal_status='sent',   status='paid'
        //    - 'cancelled' Ã¢â€ â€™ internal_status='sent',   status='cancelled'
        DB::table('avalanchestudio_avalanchecrm_invoices')
            ->where('status', 'draft')
            ->update(['internal_status' => 'draft', 'status' => 'outstanding']);

        DB::table('avalanchestudio_avalanchecrm_invoices')
            ->where('status', 'sent')
            ->update(['internal_status' => 'sent', 'status' => 'outstanding']);

        // 'paid' and 'cancelled' keep their status value, just set internal_status
        DB::table('avalanchestudio_avalanchecrm_invoices')
            ->where('status', 'paid')
            ->update(['internal_status' => 'sent']);

        DB::table('avalanchestudio_avalanchecrm_invoices')
            ->where('status', 'cancelled')
            ->update(['internal_status' => 'sent']);
    }

    public function down()
    {
        if (!Schema::hasColumn('avalanchestudio_avalanchecrm_invoices', 'internal_status')) {
            return;
        }

        // Reverse: move internal_status back into status where applicable
        DB::table('avalanchestudio_avalanchecrm_invoices')
            ->where('internal_status', 'draft')
            ->update(['status' => 'draft']);

        DB::table('avalanchestudio_avalanchecrm_invoices')
            ->where('internal_status', 'sent')
            ->where('status', 'outstanding')
            ->update(['status' => 'sent']);

        Schema::table('avalanchestudio_avalanchecrm_invoices', function (Blueprint $table) {
            $table->dropColumn('internal_status');
        });
    }
};
