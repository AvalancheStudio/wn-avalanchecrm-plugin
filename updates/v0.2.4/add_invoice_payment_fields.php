<?php

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (Schema::hasColumn('avalanchestudio_avalanchecrm_invoices', 'payment_method')) {
            return;
        }

        Schema::table('avalanchestudio_avalanchecrm_invoices', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('status');
            $table->string('payment_reference')->nullable()->after('payment_method');
            $table->timestamp('paid_at')->nullable()->after('payment_reference');
            // Stripe checkout session ID for tracking
            $table->string('stripe_checkout_session_id')->nullable()->after('paid_at');
            // PayPal order ID for tracking
            $table->string('paypal_order_id')->nullable()->after('stripe_checkout_session_id');
            // GoCardless payment ID for tracking
            $table->string('gocardless_payment_id')->nullable()->after('paypal_order_id');
        });
    }

    public function down()
    {
        Schema::table('avalanchestudio_avalanchecrm_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'payment_reference',
                'paid_at',
                'stripe_checkout_session_id',
                'paypal_order_id',
                'gocardless_payment_id',
            ]);
        });
    }
};
