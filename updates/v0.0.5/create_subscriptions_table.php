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
        if (Schema::hasTable('avalanchestudio_avalanchecrm_subscriptions')) {
            return;
        }

        Schema::create('avalanchestudio_avalanchecrm_subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('client_id')->unsigned()->nullable();
            $table->string('provider')->nullable();
            $table->string('plan_name')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('avalanchestudio_avalanchecrm_subscriptions');
    }
};
