<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;

/**
 * Transaction Model
 */
class Transaction extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \AvalancheStudio\AvalancheCRM\Traits\LogsActivity;


    /**
     * @var string The database table used by the model.
     */
    public $table = 'avalanchestudio_avalanchecrm_transactions';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'client_id',
        'invoice_id',
        'subscription_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'transaction_id',
        'description',
    ];

    /**
     * @var array Validation rules for attributes
     */
    public $rules = [
        'client_id' => 'required',
        'amount' => 'required|numeric',
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'client' => [\AvalancheStudio\AvalancheCRM\Models\Client::class],
        'invoice' => [\AvalancheStudio\AvalancheCRM\Models\Invoice::class],
        'subscription' => [\AvalancheStudio\AvalancheCRM\Models\Subscription::class],
    ];
}
