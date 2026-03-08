<?php

namespace AvalancheStudio\AvalancheCRM\Console;

use Illuminate\Console\Command;
use Winter\User\Models\User;
use AvalancheStudio\AvalancheCRM\Models\Client;
use AvalancheStudio\AvalancheCRM\Models\Staff;

/**
 * Artisan command to sync existing users into the CRM.
 *
 * Finds all Winter CMS users who are in the 'client' or 'staff' group but
 * have no corresponding CRM record, and creates those records automatically.
 *
 * Usage:  php artisan avalanchecrm:sync-users
 */
class SyncCrmUsers extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'avalanchecrm:sync-users';

    /**
     * @var string The console command description.
     */
    protected $description = 'Create missing CRM Client/Staff records for users already in the client or staff group.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $clientsCreated = 0;
        $staffCreated = 0;

        // ── Clients ──────────────────────────────────────────────────────────
        $this->info('Checking for users in the [client] group with no Client CRM record…');

        User::whereHas('groups', function ($q) {
            $q->where('code', 'client');
        })->doesntHave('client')->chunk(50, function ($users) use (&$clientsCreated) {
            foreach ($users as $user) {
                $name = trim(($user->name ?? '') . ' ' . ($user->surname ?? '')) ?: $user->email;

                $client = new Client();
                $client->user_id = $user->id;
                $client->name = $name;
                $client->email = $user->email;
                $client->save();

                $clientsCreated++;
                $this->line("  ✔ Client created for user #{$user->id} ({$user->email})");
            }
        });

        // ── Staff ─────────────────────────────────────────────────────────────
        $this->info('Checking for users in the [staff] group with no Staff CRM record…');

        User::whereHas('groups', function ($q) {
            $q->where('code', 'staff');
        })->doesntHave('staff')->chunk(50, function ($users) use (&$staffCreated) {
            foreach ($users as $user) {
                $name = trim(($user->name ?? '') . ' ' . ($user->surname ?? '')) ?: $user->email;

                $staff = new Staff();
                $staff->user_id = $user->id;
                $staff->name = $name;
                $staff->email = $user->email;
                $staff->save();

                $staffCreated++;
                $this->line("  ✔ Staff created for user #{$user->id} ({$user->email})");
            }
        });

        $this->info("Done. {$clientsCreated} client(s) and {$staffCreated} staff record(s) created.");

        return 0;
    }
}
