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
        if (Schema::hasTable('avalanchestudio_avalanchecrm_projects_clients')) {
            return;
        }

        Schema::create('avalanchestudio_avalanchecrm_projects_clients', function (Blueprint $table) {
            $table->integer('project_id')->unsigned();
            $table->integer('client_id')->unsigned();
            $table->primary(['project_id', 'client_id'], 'project_client_primary');
        });

        // Optional: Migrate existing project->client relations to the pivot table
        // This is safer to do now while the data is fresh.
        $projects = \AvalancheStudio\AvalancheCRM\Models\Project::whereNotNull('client_id')->get();
        foreach ($projects as $project) {
            \Illuminate\Support\Facades\DB::table('avalanchestudio_avalanchecrm_projects_clients')->insert([
                'project_id' => $project->id,
                'client_id' => $project->client_id
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('avalanchestudio_avalanchecrm_projects_clients');
    }
};
