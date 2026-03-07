<?php

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasColumn('avalanchestudio_avalanchecrm_subscription_plans', 'stripe_price_id')) {
            Schema::table('avalanchestudio_avalanchecrm_subscription_plans', function (Blueprint $table) {
                $table->string('stripe_price_id')->nullable()->after('paypal_plan_id');
            });
        }

        if (!Schema::hasColumn('avalanchestudio_avalanchecrm_clients', 'stripe_customer_id')) {
            Schema::table('avalanchestudio_avalanchecrm_clients', function (Blueprint $table) {
                $table->string('stripe_customer_id')->nullable()->after('user_id');
            });
        }

        if (!Schema::hasColumn('avalanchestudio_avalanchecrm_subscriptions', 'stripe_subscription_id')) {
            Schema::table('avalanchestudio_avalanchecrm_subscriptions', function (Blueprint $table) {
                $table->string('stripe_subscription_id')->nullable()->after('reference_id');
                $table->string('stripe_checkout_session_id')->nullable()->after('stripe_subscription_id');
            });
        }
    }

    public function down()
    {
        Schema::table('avalanchestudio_avalanchecrm_subscription_plans', function (Blueprint $table) {
            $table->dropColumn('stripe_price_id');
        });

        Schema::table('avalanchestudio_avalanchecrm_clients', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });

        Schema::table('avalanchestudio_avalanchecrm_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['stripe_subscription_id', 'stripe_checkout_session_id']);
        });
    }
};
