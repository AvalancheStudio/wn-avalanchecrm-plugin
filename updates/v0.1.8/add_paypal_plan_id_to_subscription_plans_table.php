<?php
namespace AvalancheStudio\AvalancheCRM\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class AddPaypalPlanIdToSubscriptionPlansTable extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('avalanchestudio_avalanchecrm_subscription_plans', 'paypal_plan_id')) {
            return;
        }

        Schema::table('avalanchestudio_avalanchecrm_subscription_plans', function ($table) {
            $table->string('paypal_plan_id')->nullable()->after('billing_cycle');
        });
    }

    public function down()
    {
        Schema::table('avalanchestudio_avalanchecrm_subscription_plans', function ($table) {
            $table->dropColumn('paypal_plan_id');
        });
    }
}
