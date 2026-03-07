<?php

use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;
use Winter\User\Models\UserGroup;

return new class extends Migration {
    public function up()
    {
        if (!class_exists(UserGroup::class) || !Schema::hasTable('user_groups')) {
            return;
        }

        if (!UserGroup::where('code', 'clients')->exists()) {
            UserGroup::create([
                'name' => 'Clients',
                'code' => 'clients',
                'description' => 'CRM Clients Group'
            ]);
        }
    }

    public function down()
    {
        if (!class_exists(UserGroup::class) || !Schema::hasTable('user_groups')) {
            return;
        }

        $group = UserGroup::where('code', 'clients')->first();
        if ($group) {
            $group->delete();
        }
    }
};
