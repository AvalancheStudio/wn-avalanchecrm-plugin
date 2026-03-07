<?php

namespace AvalancheStudio\AvalancheCRM\Updates;

use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

class CreateTicketRepliesTable extends Migration
{
    public function up()
    {
        Schema::create('avalanchestudio_avalanchecrm_ticket_replies', function ($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('ticket_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('author_type', 20)->default('client'); // client, staff, system
            $table->string('author_name')->nullable();
            $table->text('content');
            $table->boolean('is_internal')->default(false); // internal notes not visible to client
            $table->timestamps();

            $table->index('ticket_id');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('avalanchestudio_avalanchecrm_ticket_replies');
    }
}
