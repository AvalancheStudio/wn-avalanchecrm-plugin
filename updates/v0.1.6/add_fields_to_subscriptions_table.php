<?php

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('avalanchestudio_avalanchecrm_subscriptions', 'payment_method')) {
            return;
        }

        Schema::table('avalanchestudio_avalanchecrm_subscriptions', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('provider');
            $table->decimal('amount', 10, 2)->nullable()->after('payment_method');
            $table->string('billing_cycle')->nullable()->after('amount');
            $table->date('next_billing_date')->nullable()->after('billing_cycle');
            $table->text('notes')->nullable()->after('next_billing_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('avalanchestudio_avalanchecrm_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'amount', 'billing_cycle', 'next_billing_date', 'notes']);
        });
    }
};
