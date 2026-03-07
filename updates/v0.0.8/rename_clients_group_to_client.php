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

        // Skip if already renamed or target already exists
        if (UserGroup::where('code', 'client')->exists()) {
            return;
        }

        $group = UserGroup::where('code', 'clients')->first();
        if ($group) {
            $group->code = 'client';
            $group->name = 'Client';
            $group->save();
        }
    }

    public function down()
    {
        // Skipped
    }
};
