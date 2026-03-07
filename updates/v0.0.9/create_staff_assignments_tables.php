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
        if (!Schema::hasTable('avalanchestudio_avalanchecrm_projects_staff')) {
            Schema::create('avalanchestudio_avalanchecrm_projects_staff', function (Blueprint $table) {
                $table->integer('project_id')->unsigned();
                $table->integer('user_id')->unsigned(); // Backend User
                $table->primary(['project_id', 'user_id'], 'project_staff_primary');
            });
        }

        if (!Schema::hasTable('avalanchestudio_avalanchecrm_tickets_staff')) {
            Schema::create('avalanchestudio_avalanchecrm_tickets_staff', function (Blueprint $table) {
                $table->integer('ticket_id')->unsigned();
                $table->integer('user_id')->unsigned(); // Backend User
                $table->primary(['ticket_id', 'user_id'], 'ticket_staff_primary');
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
        Schema::dropIfExists('avalanchestudio_avalanchecrm_projects_staff');
        Schema::dropIfExists('avalanchestudio_avalanchecrm_tickets_staff');
    }
};
