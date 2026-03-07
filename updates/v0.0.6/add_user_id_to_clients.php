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
        if (Schema::hasColumn('avalanchestudio_avalanchecrm_clients', 'user_id')) {
            return;
        }

        Schema::table('avalanchestudio_avalanchecrm_clients', function (Blueprint $table) {
            $table->integer('user_id')->unsigned()->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('avalanchestudio_avalanchecrm_clients', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
