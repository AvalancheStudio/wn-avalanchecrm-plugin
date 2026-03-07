<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;

/**
 * TicketStatus Model
 */
class TicketStatus extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \AvalancheStudio\AvalancheCRM\Traits\LogsActivity;


    /**
     * @var string The database table used by the model.
     */
    public $table = 'avalanchestudio_avalanchecrm_ticket_statuses';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = ['name', 'color', 'is_default'];

    /**
     * @var array Validation rules for attributes
     */
    public $rules = [
        'name' => 'required|unique:avalanchestudio_avalanchecrm_ticket_statuses',
    ];

    /**
     * @var array Relations
     */
    public $hasMany = [
        'tickets' => [\AvalancheStudio\AvalancheCRM\Models\Ticket::class, 'key' => 'status_id'],
    ];

    /**
     * Ensure only one status is set as default
     */
    public function afterSave()
    {
        if ($this->is_default) {
            $this->newQuery()->where('id', '<>', $this->id)->update(['is_default' => false]);
        }
    }
}
