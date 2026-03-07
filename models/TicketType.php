<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;

/**
 * TicketType Model
 */
class TicketType extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \AvalancheStudio\AvalancheCRM\Traits\LogsActivity;


    /**
     * @var string The database table used by the model.
     */
    public $table = 'avalanchestudio_avalanchecrm_ticket_types';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = ['name', 'icon', 'custom_fields'];

    /**
     * @var array Validation rules for attributes
     */
    public $rules = [
        'name' => 'required|unique:avalanchestudio_avalanchecrm_ticket_types',
    ];

    /**
     * @var array Attributes to be cast to JSON
     */
    protected $jsonable = ['custom_fields'];

    /**
     * @var array Relations
     */
    public $hasMany = [
        'tickets' => [\AvalancheStudio\AvalancheCRM\Models\Ticket::class, 'key' => 'ticket_type_id'],
    ];
}
