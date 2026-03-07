<?php

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (Schema::hasTable('avalanchestudio_avalanchecrm_email_templates')) {
            return;
        }

        Schema::create('avalanchestudio_avalanchecrm_email_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('avalanchestudio_avalanchecrm_email_templates');
    }
};
