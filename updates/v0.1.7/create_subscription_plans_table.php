<?php

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasTable('avalanchestudio_avalanchecrm_subscription_plans')) {
            Schema::create('avalanchestudio_avalanchecrm_subscription_plans', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('slug')->nullable()->unique();
                $table->text('description')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->string('billing_cycle')->default('monthly');
                $table->text('features')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('avalanchestudio_avalanchecrm_subscriptions', 'plan_id')) {
            Schema::table('avalanchestudio_avalanchecrm_subscriptions', function (Blueprint $table) {
                $table->integer('plan_id')->unsigned()->nullable()->after('client_id');
            });
        }
    }

    public function down()
    {
        Schema::table('avalanchestudio_avalanchecrm_subscriptions', function (Blueprint $table) {
            $table->dropColumn('plan_id');
        });

        Schema::dropIfExists('avalanchestudio_avalanchecrm_subscription_plans');
    }
};
