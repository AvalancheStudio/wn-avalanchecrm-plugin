<?php

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (Schema::hasTable('avalanchestudio_avalanchecrm_campaigns')) {
            return;
        }

        Schema::create('avalanchestudio_avalanchecrm_campaigns', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->string('status')->default('draft'); // draft, scheduled, sending, sent
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('template_id')->unsigned()->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('avalanchestudio_avalanchecrm_campaigns');
    }
};
