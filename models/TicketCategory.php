<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;

/**
 * TicketCategory Model
 */
class TicketCategory extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \AvalancheStudio\AvalancheCRM\Traits\LogsActivity;


    /**
     * @var string The database table used by the model.
     */
    public $table = 'avalanchestudio_avalanchecrm_ticket_categories';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = ['name', 'color'];

    /**
     * @var array Validation rules for attributes
     */
    public $rules = [
        'name' => 'required|unique:avalanchestudio_avalanchecrm_ticket_categories',
    ];

    /**
     * @var array Relations
     */
    public $hasMany = [
        'tickets' => [\AvalancheStudio\AvalancheCRM\Models\Ticket::class],
    ];
}
