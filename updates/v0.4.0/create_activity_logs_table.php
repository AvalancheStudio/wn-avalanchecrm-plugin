<?php

namespace AvalancheStudio\AvalancheCRM\Updates\v040;

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

class CreateActivityLogsTable extends Migration
{
    public function up()
    {
        Schema::create('avalanchestudio_avalanchecrm_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->unsigned()->nullable()->index();
            $table->integer('staff_id')->unsigned()->nullable()->index();
            $table->integer('client_id')->unsigned()->nullable()->index();
            $table->string('module')->nullable();
            $table->string('action')->nullable();
            $table->text('message')->nullable();
            $table->string('object_type')->nullable();
            $table->integer('object_id')->unsigned()->nullable();
            $table->mediumText('data')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('avalanchestudio_avalanchecrm_activity_logs');
    }
}
