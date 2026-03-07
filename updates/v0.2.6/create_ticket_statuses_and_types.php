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
        if (!Schema::hasTable('avalanchestudio_avalanchecrm_ticket_statuses')) {
            Schema::create('avalanchestudio_avalanchecrm_ticket_statuses', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('color')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('avalanchestudio_avalanchecrm_ticket_types')) {
            Schema::create('avalanchestudio_avalanchecrm_ticket_types', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('icon')->nullable();
                $table->text('custom_fields')->nullable(); // JSON schema for custom fields
                $table->timestamps();
            });
        }

        if (Schema::hasTable('avalanchestudio_avalanchecrm_tickets')) {
            Schema::table('avalanchestudio_avalanchecrm_tickets', function (Blueprint $table) {
                if (!Schema::hasColumn('avalanchestudio_avalanchecrm_tickets', 'status_id')) {
                    $table->integer('status_id')->unsigned()->nullable()->after('category_id');
                }
                if (!Schema::hasColumn('avalanchestudio_avalanchecrm_tickets', 'ticket_type_id')) {
                    $table->integer('ticket_type_id')->unsigned()->nullable()->after('status_id');
                }
                if (!Schema::hasColumn('avalanchestudio_avalanchecrm_tickets', 'custom_fields_data')) {
                    $table->text('custom_fields_data')->nullable()->after('description');
                }
            });
        }

        // Seed default statuses
        $defaults = [
            ['name' => 'Open', 'color' => '#3498db', 'is_default' => true],
            ['name' => 'In Progress', 'color' => '#f39c12', 'is_default' => false],
            ['name' => 'Resolved', 'color' => '#27ae60', 'is_default' => false],
            ['name' => 'Closed', 'color' => '#7f8c8d', 'is_default' => false],
        ];

        foreach ($defaults as $status) {
            $exists = \Db::table('avalanchestudio_avalanchecrm_ticket_statuses')
                ->where('name', $status['name'])
                ->exists();
            if (!$exists) {
                \Db::table('avalanchestudio_avalanchecrm_ticket_statuses')->insert(array_merge($status, [
                    'created_at' => now(),
                    'updated_at' => now()
                ]));
            }
        }

        // Link existing tickets to the 'Open' status if they don't have one
        $openStatus = \AvalancheStudio\AvalancheCRM\Models\TicketStatus::where('name', 'Open')->first();
        if ($openStatus) {
            \AvalancheStudio\AvalancheCRM\Models\Ticket::whereNull('status_id')->update(['status_id' => $openStatus->id]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('avalanchestudio_avalanchecrm_tickets')) {
            Schema::table('avalanchestudio_avalanchecrm_tickets', function (Blueprint $table) {
                $table->dropColumn(['status_id', 'ticket_type_id', 'custom_fields_data']);
            });
        }

        Schema::dropIfExists('avalanchestudio_avalanchecrm_ticket_statuses');
        Schema::dropIfExists('avalanchestudio_avalanchecrm_ticket_types');
    }
};
