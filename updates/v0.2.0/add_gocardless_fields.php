<?php

namespace AvalancheStudio\AvalancheCRM\Updates;

use Schema;
use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;

/**
 * Add GoCardless fields to clients and subscriptions tables.
 */
class AddGoCardlessFields extends Migration
{
    public function up()
    {
        // Add gocardless_customer_id and gocardless_mandate_id to clients
        if (!Schema::hasColumn('avalanchestudio_avalanchecrm_clients', 'gocardless_customer_id')) {
            Schema::table('avalanchestudio_avalanchecrm_clients', function (Blueprint $table) {
                $table->string('gocardless_customer_id')->nullable()->after('stripe_customer_id');
                $table->string('gocardless_mandate_id')->nullable()->after('gocardless_customer_id');
            });
        }

        // Add gocardless fields to subscriptions
        if (!Schema::hasColumn('avalanchestudio_avalanchecrm_subscriptions', 'gocardless_subscription_id')) {
            Schema::table('avalanchestudio_avalanchecrm_subscriptions', function (Blueprint $table) {
                $table->string('gocardless_subscription_id')->nullable()->after('stripe_checkout_session_id');
                $table->string('gocardless_redirect_flow_id')->nullable()->after('gocardless_subscription_id');
            });
        }
    }

    public function down()
    {
        Schema::table('avalanchestudio_avalanchecrm_clients', function (Blueprint $table) {
            $table->dropColumn(['gocardless_customer_id', 'gocardless_mandate_id']);
        });

        Schema::table('avalanchestudio_avalanchecrm_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['gocardless_subscription_id', 'gocardless_redirect_flow_id']);
        });
    }
}
