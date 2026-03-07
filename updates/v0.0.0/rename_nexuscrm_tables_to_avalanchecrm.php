<?php

namespace AvalancheStudio\AvalancheCRM\Updates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Winter\Storm\Database\Updates\Migration;

/**
 * Migrate everything from the old AvalancheStudio.NexusCRM plugin to the renamed
 * AvalancheStudio.AvalancheCRM plugin:
 *
 *  1. Rename all avalanchestudio_nexuscrm_* tables → avalanchestudio_avalanchecrm_*
 *  2. Rename the backend role code nexuscrm-staff → avalanchecrm-staff
 *  3. Rename the settings record code
 *  4. Delete the old plugin version/history rows (all migrations are idempotent)
 */
class RenameNexuscrmTablesToAvalanchecrm extends Migration
{
    protected string $oldPlugin = 'AvalancheStudio.NexusCRM';
    protected string $newPlugin = 'AvalancheStudio.AvalancheCRM';

    /**
     * Map of old table names to new table names.
     */
    protected array $tableMap = [
        'avalanchestudio_nexuscrm_clients'             => 'avalanchestudio_avalanchecrm_clients',
        'avalanchestudio_nexuscrm_projects'            => 'avalanchestudio_avalanchecrm_projects',
        'avalanchestudio_nexuscrm_projects_clients'    => 'avalanchestudio_avalanchecrm_projects_clients',
        'avalanchestudio_nexuscrm_projects_staff'      => 'avalanchestudio_avalanchecrm_projects_staff',
        'avalanchestudio_nexuscrm_tickets'             => 'avalanchestudio_avalanchecrm_tickets',
        'avalanchestudio_nexuscrm_tickets_staff'       => 'avalanchestudio_avalanchecrm_tickets_staff',
        'avalanchestudio_nexuscrm_ticket_categories'   => 'avalanchestudio_avalanchecrm_ticket_categories',
        'avalanchestudio_nexuscrm_ticket_statuses'     => 'avalanchestudio_avalanchecrm_ticket_statuses',
        'avalanchestudio_nexuscrm_ticket_types'        => 'avalanchestudio_avalanchecrm_ticket_types',
        'avalanchestudio_nexuscrm_ticket_replies'      => 'avalanchestudio_avalanchecrm_ticket_replies',
        'avalanchestudio_nexuscrm_tasks'               => 'avalanchestudio_avalanchecrm_tasks',
        'avalanchestudio_nexuscrm_time_entries'        => 'avalanchestudio_avalanchecrm_time_entries',
        'avalanchestudio_nexuscrm_invoices'            => 'avalanchestudio_avalanchecrm_invoices',
        'avalanchestudio_nexuscrm_invoice_items'       => 'avalanchestudio_avalanchecrm_invoice_items',
        'avalanchestudio_nexuscrm_subscriptions'       => 'avalanchestudio_avalanchecrm_subscriptions',
        'avalanchestudio_nexuscrm_subscription_plans'  => 'avalanchestudio_avalanchecrm_subscription_plans',
        'avalanchestudio_nexuscrm_transactions'        => 'avalanchestudio_avalanchecrm_transactions',
        'avalanchestudio_nexuscrm_campaigns'           => 'avalanchestudio_avalanchecrm_campaigns',
        'avalanchestudio_nexuscrm_campaign_recipients' => 'avalanchestudio_avalanchecrm_campaign_recipients',
        'avalanchestudio_nexuscrm_email_templates'     => 'avalanchestudio_avalanchecrm_email_templates',
        'avalanchestudio_nexuscrm_staff'               => 'avalanchestudio_avalanchecrm_staff',
    ];

    public function up()
    {
        // 1. Rename plugin tables
        foreach ($this->tableMap as $oldName => $newName) {
            if (Schema::hasTable($oldName) && !Schema::hasTable($newName)) {
                Schema::rename($oldName, $newName);
            }
        }

        // 2. Rename the backend role code
        if (Schema::hasTable('backend_user_roles')) {
            DB::table('backend_user_roles')
                ->where('code', 'nexuscrm-staff')
                ->update([
                    'code'        => 'avalanchecrm-staff',
                    'permissions' => json_encode([
                        'avalanchestudio.avalanchecrm.*'               => 1,
                        'avalanchestudio.avalanchecrm.manage_settings' => 1,
                        'avalanchestudio.avalanchecrm.tickets.*'       => 1,
                        'avalanchestudio.avalanchecrm.marketing.*'     => 1,
                    ]),
                ]);
        }

        // 3. Rename the settings record
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')
                ->where('item', 'avalanchestudio_nexuscrm_settings')
                ->update(['item' => 'avalanchestudio_avalanchecrm_settings']);
        }

        // 4. Clean up old plugin registration (all migrations are idempotent
        //    so Winter can safely re-run them under the new plugin code)
        if (Schema::hasTable('system_plugin_versions')) {
            DB::table('system_plugin_versions')
                ->where('code', $this->oldPlugin)
                ->delete();
        }

        if (Schema::hasTable('system_plugin_history')) {
            DB::table('system_plugin_history')
                ->where('code', $this->oldPlugin)
                ->delete();
        }
    }

    public function down()
    {
        // Reverse table renames
        foreach ($this->tableMap as $oldName => $newName) {
            if (Schema::hasTable($newName) && !Schema::hasTable($oldName)) {
                Schema::rename($newName, $oldName);
            }
        }

        // Reverse role code
        if (Schema::hasTable('backend_user_roles')) {
            DB::table('backend_user_roles')
                ->where('code', 'avalanchecrm-staff')
                ->update([
                    'code'        => 'nexuscrm-staff',
                    'permissions' => json_encode([
                        'avalanchestudio.nexuscrm.*'               => 1,
                        'avalanchestudio.nexuscrm.manage_settings' => 1,
                        'avalanchestudio.nexuscrm.tickets.*'       => 1,
                        'avalanchestudio.nexuscrm.marketing.*'     => 1,
                    ]),
                ]);
        }

        // Reverse settings code
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')
                ->where('item', 'avalanchestudio_avalanchecrm_settings')
                ->update(['item' => 'avalanchestudio_nexuscrm_settings']);
        }
    }
}
