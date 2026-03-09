<?php

namespace AvalancheStudio\AvalancheCRM\Updates;

use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;
use Winter\Storm\Database\Schema\Blueprint;

/**
 * Ticket Attachments
 *
 * Winter CMS file attachments are stored in the system_files table using a
 * polymorphic relationship – no extra columns are needed on the tickets table.
 * This migration is a no-op for the schema but documents the feature bump.
 */
class AddTicketAttachments extends Migration
{
    public function up(): void
    {
        // Attachments are persisted in system_files via the attachMany relation
        // on the Ticket model. No schema changes required.
    }

    public function down(): void
    {
        // Nothing to roll back.
    }
}
