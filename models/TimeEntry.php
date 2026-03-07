<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;
use Carbon\Carbon;

/**
 * TimeEntry Model
 *
 * Represents a single timed work session on a task.
 */
class TimeEntry extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \AvalancheStudio\AvalancheCRM\Traits\LogsActivity;


    public $table = 'avalanchestudio_avalanchecrm_time_entries';

    protected $guarded = [];

    protected $fillable = [
        'task_id',
        'user_id',
        'started_at',
        'stopped_at',
        'duration_hours',
        'description',
    ];

    public $rules = [
        'task_id' => 'required',
        'started_at' => 'required',
    ];

    protected $casts = [
        'duration_hours' => 'float',
    ];

    protected $dates = [
        'started_at',
        'stopped_at',
        'created_at',
        'updated_at',
    ];

    public $belongsTo = [
        'task' => [\AvalancheStudio\AvalancheCRM\Models\Task::class],
        'user' => [\Backend\Models\User::class],
    ];

    /**
     * Auto-calculate duration when stopped.
     */
    public function beforeSave()
    {
        if ($this->stopped_at && $this->started_at) {
            $start = $this->started_at instanceof Carbon ? $this->started_at : Carbon::parse($this->started_at);
            $stop = $this->stopped_at instanceof Carbon ? $this->stopped_at : Carbon::parse($this->stopped_at);
            $this->duration_hours = round($stop->diffInSeconds($start) / 3600, 4);
        }
    }

    /**
     * Format duration for display (e.g. "2h 15m").
     */
    public function getFormattedDurationAttribute(): string
    {
        $totalMinutes = round($this->duration_hours * 60);
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
     * Get display name for logging
     */
    protected function getActivityName()
    {
        $name = 'Time Entry';

        if ($this->task) {
            $name .= sprintf(' (Task: %s)', $this->task->title);
        }

        return $name;
    }
}

