<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;

/**
 * TicketReply Model
 */
class TicketReply extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \AvalancheStudio\AvalancheCRM\Traits\LogsActivity;


    public $table = 'avalanchestudio_avalanchecrm_ticket_replies';

    protected $guarded = [];

    protected $fillable = [
        'ticket_id',
        'user_id',
        'author_type',
        'author_name',
        'content',
        'is_internal',
    ];

    public $rules = [
        'ticket_id' => 'required',
        'content' => 'required',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    public $belongsTo = [
        'ticket' => [\AvalancheStudio\AvalancheCRM\Models\Ticket::class],
        'user' => [\Winter\User\Models\User::class],
    ];

    /**
     * After creating a reply, notify the client (unless it's an internal note or from the client themselves).
     */
    public function afterCreate()
    {
        // Don't notify for internal notes
        if ($this->is_internal) {
            return;
        }

        $ticket = $this->ticket;
        if (!$ticket) {
            return;
        }

        // If client replied, notify all assigned staff
        if ($this->author_type === 'client') {
            if ($ticket->staff) {
                foreach ($ticket->staff as $staff) {
                    $staff->sendNotification('ticket', 'Staff: New Client Reply');
                }
            }
            return;
        }

        // If staff (or other) replied, notify the client
        if ($ticket->client) {
            $ticket->client->sendNotification('ticket', 'Ticket Reply');
        }
    }

    /**
     * Get the author display name.
     */
    public function getAuthorDisplayNameAttribute(): string
    {
        if ($this->author_name) {
            return $this->author_name;
        }

        if ($this->user) {
            return trim($this->user->name . ' ' . $this->user->surname);
        }

        return ucfirst($this->author_type);
    }

    /**
     * Get display name for logging
     */
    protected function getActivityName()
    {
        $name = 'Reply';

        if ($this->ticket) {
            $name .= sprintf(' (Ticket #%s: %s)', $this->ticket->id, $this->ticket->subject);
        }

        return $name;
    }
}

