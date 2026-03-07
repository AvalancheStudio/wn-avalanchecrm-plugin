<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;

/**
 * Task Model
 */
class Task extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \Winter\Storm\Database\Traits\Sortable;
    use \AvalancheStudio\AvalancheCRM\Traits\LogsActivity;


    /**
     * @var string The database table used by the model.
     */
    public $table = 'avalanchestudio_avalanchecrm_tasks';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = ['title', 'description', 'priority', 'status', 'due_date', 'is_billable', 'hours', 'hourly_rate'];

    /**
     * @var array Attributes to be cast to native types
     */
    protected $casts = [
        'is_billable' => 'boolean',
        'is_invoiced' => 'boolean',
        'timer_running' => 'boolean',
        'hours' => 'float',
        'hourly_rate' => 'float',
    ];

    /**
     * @var array Validation rules for attributes
     */
    public $rules = [
        'title' => 'required',
        'status' => 'required',
    ];

    /**
     * @var array Attributes to be cast to Argon (Carbon) instances
     */
    protected $dates = [
        'due_date',
        'timer_started_at',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'project' => [\AvalancheStudio\AvalancheCRM\Models\Project::class],
        'assigned_to' => [\Backend\Models\User::class, 'key' => 'assigned_to_id'],
        'invoiceItem' => [\AvalancheStudio\AvalancheCRM\Models\InvoiceItem::class, 'key' => 'id', 'otherKey' => 'task_id'],
    ];

    public $hasMany = [
        'time_entries' => [\AvalancheStudio\AvalancheCRM\Models\TimeEntry::class, 'order' => 'started_at desc'],
    ];

    /**
     * Calculate the line total for this task.
     */
    public function getBillableAmountAttribute(): float
    {
        $rate = $this->hourly_rate ?: ($this->project ? $this->project->hourly_rate : 0) ?: 0;
        return round(($this->hours ?? 0) * $rate, 2);
    }

    /**
     * Initialize model
     */
    public function afterInit()
    {
        if (!$this->project_id && $projectId = request()->get('project')) {
            $this->project_id = $projectId;
        }
    }

    public function getDueDateAttribute($value)
    {
        if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        return $this->asDateTime($value);
    }

    public function setDueDateAttribute($value)
    {
        $this->attributes['due_date'] = empty($value) ? null : $value;
    }

    /**
     * Options for Task Status
     */
    public function getStatusOptions()
    {
        return [
            'todo' => 'To Do',
            'in_progress' => 'In Progress',
            'review' => 'Review',
            'done' => 'Done',
        ];
    }

    /**
     * Options for Task Priority
     */
    public function getPriorityOptions()
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent',
        ];
    }

    /**
     * Start the timer for this task.
     */
    public function startTimer(?int $userId = null): void
    {
        if ($this->timer_running) {
            return;
        }

        $now = now();

        $this->timer_running = true;
        $this->timer_started_at = $now;

        // Automatically mark the task as billable when time-tracking begins
        if (!$this->is_billable) {
            $this->is_billable = true;
        }

        $this->save();

        $entry = new \AvalancheStudio\AvalancheCRM\Models\TimeEntry();
        $entry->task_id = $this->id;
        $entry->user_id = $userId;
        $entry->started_at = $now;
        $entry->save();
    }

    /**
     * Stop the timer for this task and finalise the time entry.
     */
    public function stopTimer(): void
    {
        if (!$this->timer_running) {
            return;
        }

        $now = now();

        // Find the open time entry
        $entry = $this->time_entries()
            ->whereNull('stopped_at')
            ->orderBy('started_at', 'desc')
            ->first();

        if ($entry) {
            $entry->stopped_at = $now;
            $entry->save(); // beforeSave calculates duration_hours
        }

        $this->timer_running = false;
        $this->timer_started_at = null;
        $this->save();

        // Recalculate total hours from all time entries
        $this->recalculateHours();
    }

    /**
     * Recalculate total hours from time entries.
     */
    public function recalculateHours(): void
    {
        $totalHours = $this->time_entries()->sum('duration_hours');
        $this->hours = round($totalHours, 2);
        $this->save();
    }

    /**
     * Get total logged time in a human-readable format.
     */
    public function getFormattedHoursAttribute(): string
    {
        $totalMinutes = round(($this->hours ?? 0) * 60);
        $hours = intdiv((int) $totalMinutes, 60);
        $minutes = ((int) $totalMinutes) % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }

    /**
     * Get elapsed seconds since timer started (for live JS countdown).
     */
    public function getTimerElapsedSecondsAttribute(): int
    {
        if (!$this->timer_running || !$this->timer_started_at) {
            return 0;
        }

        return max(0, now()->diffInSeconds($this->timer_started_at));
    }

    /**
     * Get display name for logging
     */
    protected function getActivityName()
    {
        $name = sprintf('Task: %s', $this->title);

        if ($this->project) {
            $name .= sprintf(' (Project: %s)', $this->project->name);
        }

        return $name;
    }
}

