<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;
use AvalancheStudio\AvalancheCRM\Models\Campaign;
use AvalancheStudio\AvalancheCRM\Models\EmailTemplate;
use Winter\Storm\Support\Facades\Mail;

/**
 * Project Model
 */
class Project extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \AvalancheStudio\AvalancheCRM\Traits\LogsActivity;


    /**
     * @var string The database table used by the model.
     */
    public $table = 'avalanchestudio_avalanchecrm_projects';

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
    protected $casts = [
        'hourly_rate' => 'float',
        'fixed_price' => 'float',
    ];

    /**
     * @var array Attributes to be cast to JSON
     */
    protected $jsonable = [];

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
        'tickets' => [\AvalancheStudio\AvalancheCRM\Models\Ticket::class],
        'invoices' => [\AvalancheStudio\AvalancheCRM\Models\Invoice::class],
        'tasks' => [\AvalancheStudio\AvalancheCRM\Models\Task::class],
    ];
    public $hasOneThrough = [];
    public $hasManyThrough = [];
    public $belongsTo = [];
    public $belongsToMany = [
        'staff' => [
            \AvalancheStudio\AvalancheCRM\Models\Staff::class,
            'table' => 'avalanchestudio_avalanchecrm_projects_staff',
            'key' => 'project_id',
            'otherKey' => 'staff_id'
        ],
        'clients' => [
            \AvalancheStudio\AvalancheCRM\Models\Client::class,
            'table' => 'avalanchestudio_avalanchecrm_projects_clients',
            'key' => 'project_id',
            'otherKey' => 'client_id'
        ]
    ];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];

    /**
     * After creating a project, notify all attached clients.
     */
    public function afterCreate()
    {
        $this->notifyClients('project', 'New Project Created');
    }

    /**
     * After saving, detect status changes and notify clients.
     */
    public function afterSave()
    {
        if ($this->wasRecentlyCreated) {
            return;
        }

        if ($this->isDirty('status')) {
            $newStatus = $this->status;
            $this->logActivity('Status Changed', sprintf('Project status changed to "%s"', ucfirst($newStatus)));

            if (in_array($newStatus, ['completed', 'complete', 'finished'])) {

                $this->notifyClients('project', 'Project Completed');
            } else {
                $this->notifyClients('project', 'Project Status Update');
            }
        }
    }

    /**
     * Send a notification to all clients associated with this project.
     */
    public function notifyClients(string $category, string $templateName): void
    {
        foreach ($this->clients as $client) {
            $client->sendNotification($category, $templateName);
        }
    }

    /**
     * Dropdown options for billing type.
     */
    public function getBillingTypeOptions(): array
    {
        return [
            'non_billable' => 'Non-Billable',
            'hourly' => 'Hourly',
            'fixed' => 'Fixed Price',
        ];
    }

    /**
     * Get billable tasks that have not yet been invoiced.
     */
    public function getUninvoicedBillableTasks()
    {
        return $this->tasks()
            ->where('is_billable', true)
            ->where('is_invoiced', false)
            ->where('hours', '>', 0)
            ->get();
    }

    /**
     * Calculate total uninvoiced amount from billable tasks.
     */
    public function getUninvoicedTotal(): float
    {
        $total = 0;

        foreach ($this->getUninvoicedBillableTasks() as $task) {
            $rate = $task->hourly_rate ?: ($this->hourly_rate ?: 0);
            $hours = $task->hours ?? 0;
            $total += $rate * $hours;
        }

        return round($total, 2);
    }
}
