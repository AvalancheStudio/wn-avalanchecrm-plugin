<?php

use Winter\Storm\Database\Updates\Migration;
use AvalancheStudio\AvalancheCRM\Models\Ticket;
use AvalancheStudio\AvalancheCRM\Models\TicketStatus;

return new class extends Migration {
    public function up()
    {
        // Seed default statuses
        $defaults = [
            ['name' => 'Open', 'color' => '#3498db', 'is_default' => true],
            ['name' => 'In Progress', 'color' => '#f39c12', 'is_default' => false],
            ['name' => 'Resolved', 'color' => '#27ae60', 'is_default' => false],
            ['name' => 'Closed', 'color' => '#7f8c8d', 'is_default' => false],
        ];

        foreach ($defaults as $status) {
            TicketStatus::firstOrCreate(['name' => $status['name']], $status);
        }

        // Link existing tickets to the 'Open' status if they don't have one
        $openStatus = TicketStatus::where('name', 'Open')->first();
        if ($openStatus) {
            Ticket::whereNull('status_id')->update(['status_id' => $openStatus->id]);
        }
    }

    public function down()
    {
        // No need to reverse seeding
    }
};
