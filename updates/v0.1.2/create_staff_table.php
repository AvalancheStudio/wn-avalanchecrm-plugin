<?php

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;
use Winter\User\Models\UserGroup;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasTable('avalanchestudio_avalanchecrm_staff')) {
            Schema::create('avalanchestudio_avalanchecrm_staff', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('user_id')->unsigned()->nullable()->index();
                $table->string('name');
                $table->string('email')->index();
                $table->string('phone')->nullable();
                $table->string('job_title')->nullable();
                $table->timestamps();
            });
        }

        if (class_exists(UserGroup::class) && Schema::hasTable('user_groups')) {
            if (!UserGroup::where('code', 'staff')->exists()) {
                UserGroup::create([
                    'name' => 'Staff',
                    'code' => 'staff',
                    'description' => 'CRM Staff Group'
                ]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('avalanchestudio_avalanchecrm_staff');

        if (class_exists(UserGroup::class) && Schema::hasTable('user_groups')) {
            $group = UserGroup::where('code', 'staff')->first();
            if ($group) {
                $group->delete();
            }
        }
    }
};
