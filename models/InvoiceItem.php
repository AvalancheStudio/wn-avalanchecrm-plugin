<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;

/**
 * InvoiceItem Model
 *
 * Represents a single line item on an invoice, optionally linked to a task.
 */
class InvoiceItem extends Model
{
    use \Winter\Storm\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'avalanchestudio_avalanchecrm_invoice_items';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'invoice_id',
        'task_id',
        'description',
        'quantity',
        'unit_price',
        'amount',
    ];

    /**
     * @var array Validation rules
     */
    public $rules = [
        'description' => 'required',
        'quantity'    => 'required|numeric|min:0',
        'unit_price'  => 'required|numeric|min:0',
    ];

    /**
     * @var array Attributes to be cast to native types
     */
    protected $casts = [
        'quantity'   => 'float',
        'unit_price' => 'float',
        'amount'     => 'float',
    ];

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
    public $belongsTo = [
        'invoice' => [\AvalancheStudio\AvalancheCRM\Models\Invoice::class],
        'task'    => [\AvalancheStudio\AvalancheCRM\Models\Task::class],
    ];

    /**
     * Auto-calculate amount before saving.
     */
    public function beforeSave()
    {
        $this->amount = round(($this->quantity ?? 0) * ($this->unit_price ?? 0), 2);
    }
}
