<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;

/**
 * Ticket Model
 */
class Ticket extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \AvalancheStudio\AvalancheCRM\Traits\LogsActivity;


    /**
     * After creating a ticket, send confirmation to the client.
     */
    public function afterCreate()
    {
        if ($this->client) {
            $this->client->sendNotification('ticket', 'Ticket Created Confirmation');
        }
    }

    /**
     * After saving, detect status changes and notify the client.
     */
    public function afterSave()
    {
        if ($this->wasRecentlyCreated) {
            return;
        }

        if (!$this->client) {
            return;
        }

        // Check if the status changed to resolved/closed
        if ($this->isDirty('status_id')) {
            $status = $this->status_relation;
            if ($status) {
                $this->logActivity('Status Changed', sprintf('Ticket status changed to "%s"', $status->name));
            }
            if ($status && in_array(strtolower($status->name), ['resolved', 'closed'])) {

                $this->client->sendNotification('ticket', 'Ticket Resolved');
            }
        }
    }

    /**
     * @var string The database table used by the model.
     */
    public $table = 'avalanchestudio_avalanchecrm_tickets';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array Validation rules for attributes
     */
    public $rules = [];

    /**
     * @var array Attributes to be cast to native types
     */
    protected $casts = [];

    /**
     * @var array Attributes to be cast to JSON
     */
    protected $jsonable = ['custom_fields_data'];

    /**
     * @var array Attributes to be appended to the API representation of the model (ex. toArray())
     */
    protected $appends = [];

    /**
     * @var array Attributes to be removed from the API representation of the model (ex. toArray())
     */
    protected $hidden = [];

    /**
     * @var array Attributes to be cast to Argon (Carbon) instances
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [
        'replies' => [
            \AvalancheStudio\AvalancheCRM\Models\TicketReply::class,
            'order' => 'created_at asc',
        ],
    ];
    public $hasOneThrough = [];
    public $hasManyThrough = [];
    public $belongsTo = [
        'client' => [\AvalancheStudio\AvalancheCRM\Models\Client::class],
        'project' => [\AvalancheStudio\AvalancheCRM\Models\Project::class],
        'category' => [\AvalancheStudio\AvalancheCRM\Models\TicketCategory::class],
        'status_relation' => [\AvalancheStudio\AvalancheCRM\Models\TicketStatus::class, 'key' => 'status_id'],
        'ticket_type' => [\AvalancheStudio\AvalancheCRM\Models\TicketType::class],
    ];
    public $belongsToMany = [
        'staff' => [
            \AvalancheStudio\AvalancheCRM\Models\Staff::class,
            'table' => 'avalanchestudio_avalanchecrm_tickets_staff',
            'key' => 'ticket_id',
            'otherKey' => 'staff_id'
        ]
    ];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [
        'attachments' => [\System\Models\File::class],
    ];

    /**
     * Dynamically add custom fields based on ticket type
     */
    public function filterFields($fields, $context = null)
    {
        if (isset($fields->custom_fields_section)) {
            $fields->custom_fields_section->hidden = true;
        }

        $ticketTypeId = $this->ticket_type_id ?: post('Ticket[ticket_type]');

        if (!$ticketTypeId) {
            return;
        }

        $ticketType = \AvalancheStudio\AvalancheCRM\Models\TicketType::find($ticketTypeId);
        if (!$ticketType || !is_array($ticketType->custom_fields)) {
            return;
        }

        if (isset($fields->custom_fields_section)) {
            $fields->custom_fields_section->hidden = false;
        }

        foreach ($ticketType->custom_fields as $field) {
            $fieldName = 'custom_fields_data[' . $field['name'] . ']';

            $config = [
                'label' => $field['label'],
                'type' => $field['type'] ?? 'text',
                'span' => $field['span'] ?? 'full',
            ];

            if ($field['type'] === 'dropdown' && !empty($field['options'])) {
                $options = [];
                $lines = explode("\n", str_replace("\r", "", $field['options']));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!$line)
                        continue;
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $options[trim($parts[0])] = trim($parts[1]);
                    } else {
                        $options[$line] = $line;
                    }
                }
                $config['options'] = $options;
            }

            $fields->{$fieldName} = $config;
        }
    }

    /**
     * Get display name for logging
     */
    protected function getActivityName()
    {
        return sprintf('Ticket #%s: %s', $this->id, $this->subject);
    }
}

