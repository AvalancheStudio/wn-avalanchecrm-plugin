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
        if (Schema::hasTable('avalanchestudio_avalanchecrm_transactions')) {
            return;
        }

        Schema::create('avalanchestudio_avalanchecrm_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('client_id')->unsigned()->nullable();
            $table->integer('invoice_id')->unsigned()->nullable();
            $table->integer('subscription_id')->unsigned()->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 10)->nullable();
            $table->string('status')->default('completed');
            $table->string('payment_method')->nullable();
            $table->string('transaction_id')->nullable(); // Gateway reference
            $table->text('description')->nullable();
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
        Schema::dropIfExists('avalanchestudio_avalanchecrm_transactions');
    }
};
