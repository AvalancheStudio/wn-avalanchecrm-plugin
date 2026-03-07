<?php

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (Schema::hasTable('avalanchestudio_avalanchecrm_campaign_recipients')) {
            return;
        }

        Schema::create('avalanchestudio_avalanchecrm_campaign_recipients', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('campaign_id')->unsigned();
            $table->integer('client_id')->unsigned();
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')
                ->references('id')
                ->on('avalanchestudio_avalanchecrm_campaigns')
                ->onDelete('cascade');

            $table->foreign('client_id')
                ->references('id')
                ->on('avalanchestudio_avalanchecrm_clients')
                ->onDelete('cascade');

            $table->unique(['campaign_id', 'client_id']);
        });

        // Add tracking counters to campaigns
        Schema::table('avalanchestudio_avalanchecrm_campaigns', function (Blueprint $table) {
            $table->integer('sent_count')->default(0)->after('total_recipients');
            $table->integer('failed_count')->default(0)->after('sent_count');
        });
    }

    public function down()
    {
        Schema::dropIfExists('avalanchestudio_avalanchecrm_campaign_recipients');

        if (Schema::hasColumn('avalanchestudio_avalanchecrm_campaigns', 'sent_count')) {
            Schema::table('avalanchestudio_avalanchecrm_campaigns', function (Blueprint $table) {
                $table->dropColumn(['sent_count', 'failed_count']);
            });
        }
    }
};
