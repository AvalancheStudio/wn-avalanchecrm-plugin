<?php

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('avalanchestudio_avalanchecrm_clients', function (Blueprint $table) {
            $table->boolean('marketing_opt_out')->default(false)->after('company');
            $table->string('unsubscribe_token', 64)->nullable()->unique()->after('marketing_opt_out');
        });
    }

    public function down()
    {
        Schema::table('avalanchestudio_avalanchecrm_clients', function (Blueprint $table) {
            $table->dropColumn(['marketing_opt_out', 'unsubscribe_token']);
        });
    }
};
