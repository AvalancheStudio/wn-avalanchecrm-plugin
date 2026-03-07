<?php

namespace AvalancheStudio\AvalancheCRM\Updates;

use DB;
use Schema;
use Winter\Storm\Database\Updates\Migration;

/**
 * v0.3.5 — Create a "CRM Staff" backend role with all Avalanche CRM permissions
 * and add backend_user_id column to staff table.
 *
 * Uses DB::table() instead of UserRole model to bypass unique-name validation
 * when the role already exists (e.g. after a plugin rename migration).
 */
class CreateCrmBackendRole extends Migration
{
    public function up()
    {
        // 1. Add backend_user_id to staff table
        if (!Schema::hasColumn('avalanchestudio_avalanchecrm_staff', 'backend_user_id')) {
            Schema::table('avalanchestudio_avalanchecrm_staff', function ($table) {
                $table->unsignedInteger('backend_user_id')->nullable()->after('user_id');
                $table->index('backend_user_id');
            });
        }

        // 2. Seed the CRM Staff backend role (skip if code OR name already exists)
        $exists = DB::table('backend_user_roles')
            ->where('code', 'avalanchecrm-staff')
            ->orWhere('name', 'CRM Staff')
            ->exists();

        if (!$exists) {
            DB::table('backend_user_roles')->insert([
                'name'        => 'CRM Staff',
                'code'        => 'avalanchecrm-staff',
                'description' => 'Backend role for CRM staff members with access to all CRM features and settings.',
                'permissions' => json_encode([
                    'avalanchestudio.avalanchecrm.*'               => 1,
                    'avalanchestudio.avalanchecrm.manage_settings' => 1,
                    'avalanchestudio.avalanchecrm.tickets.*'       => 1,
                    'avalanchestudio.avalanchecrm.marketing.*'     => 1,
                ]),
                'is_system'  => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        // Remove the backend role
        DB::table('backend_user_roles')
            ->where('code', 'avalanchecrm-staff')
            ->delete();

        // Drop column
        if (Schema::hasColumn('avalanchestudio_avalanchecrm_staff', 'backend_user_id')) {
            Schema::table('avalanchestudio_avalanchecrm_staff', function ($table) {
                $table->dropColumn('backend_user_id');
            });
        }
    }
}
