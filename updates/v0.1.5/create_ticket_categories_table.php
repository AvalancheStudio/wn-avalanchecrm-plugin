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
        if (!Schema::hasTable('avalanchestudio_avalanchecrm_ticket_categories')) {
            Schema::create('avalanchestudio_avalanchecrm_ticket_categories', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('color')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('avalanchestudio_avalanchecrm_tickets', 'category_id')) {
            Schema::table('avalanchestudio_avalanchecrm_tickets', function (Blueprint $table) {
                $table->integer('category_id')->unsigned()->nullable()->after('project_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('avalanchestudio_avalanchecrm_tickets', function (Blueprint $table) {
            $table->dropColumn('category_id');
        });

        Schema::dropIfExists('avalanchestudio_avalanchecrm_ticket_categories');
    }
};
