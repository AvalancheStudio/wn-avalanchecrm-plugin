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
        if (Schema::hasTable('avalanchestudio_avalanchecrm_invoices')) {
            return;
        }

        Schema::create('avalanchestudio_avalanchecrm_invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('client_id')->unsigned()->nullable();
            $table->integer('project_id')->unsigned()->nullable();
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('status')->default('draft');
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
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
        Schema::dropIfExists('avalanchestudio_avalanchecrm_invoices');
    }
};
