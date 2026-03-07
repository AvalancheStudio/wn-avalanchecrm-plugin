<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;
use Backend\Models\User as BackendUser;
use Winter\User\Models\User as FrontendUser;

/**
 * ActivityLog Model
 */
class ActivityLog extends Model
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'avalanchestudio_avalanchecrm_activity_logs';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'user_id',
        'staff_id',
        'client_id',
        'module',
        'action',
        'message',
        'object_type',
        'object_id',
        'data',
    ];

    /**
     * @var array Jsonable fields
     */
    protected $jsonable = ['data'];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'user' => [BackendUser::class],
        'staff' => [\AvalancheStudio\AvalancheCRM\Models\Staff::class],
        'client' => [\AvalancheStudio\AvalancheCRM\Models\Client::class],
    ];

    /**
     * @var array Morph To Relations
     */
    public $morphTo = [
        'object' => []
    ];

    /**
     * Log an activity
     */
    public static function log($message, $module = null, $action = null, $object = null, $data = null)
    {
        $log = new static();
        $log->message = $message;
        $log->module = $module;
        $log->action = $action;
        $log->data = $data;

        // Determine user - check frontend (Client) first as it's more specific for CRM actions
        if ($user = \Winter\User\Facades\Auth::getUser()) {
            $log->client_id = \AvalancheStudio\AvalancheCRM\Models\Client::where('user_id', $user->id)->value('id');
        }

        // If no client found, or if we want to capture staff context too
        if (!$log->client_id && ($user = \Backend\Facades\BackendAuth::getUser())) {
            $log->user_id = $user->id;
            $log->staff_id = \AvalancheStudio\AvalancheCRM\Models\Staff::where('backend_user_id', $user->id)->value('id');
        }


        // Object association
        if ($object && $object instanceof Model) {
            $log->object_type = get_class($object);
            $log->object_id = $object->getKey();
        }

        $log->save();

        return $log;
    }

    /**
     * Get project number/ID associated with this activity
     */
    public function getProjectNumberAttribute()
    {
        $object = $this->object;
        if (!$object) {
            return null;
        }

        $project = null;
        if ($object instanceof \AvalancheStudio\AvalancheCRM\Models\Project) {
            $project = $object;
        } elseif (isset($object->project) && $object->project instanceof \AvalancheStudio\AvalancheCRM\Models\Project) {
            $project = $object->project;
        } elseif ($object instanceof \AvalancheStudio\AvalancheCRM\Models\TicketReply && $object->ticket && $object->ticket->project) {
            $project = $object->ticket->project;
        } elseif ($object instanceof \AvalancheStudio\AvalancheCRM\Models\TimeEntry && $object->task && $object->task->project) {
            $project = $object->task->project;
        }

        return $project ? $project->name : (isset($object->project_id) ? 'Project #' . $object->project_id : null);
    }


    /**
     * Get ticket associated with this activity
     */
    public function getTicketIdentityAttribute()
    {
        $object = $this->object;
        if (!$object) {
            return null;
        }

        if ($object instanceof \AvalancheStudio\AvalancheCRM\Models\Ticket) {
            return '#' . $object->id;
        }

        if ($object instanceof \AvalancheStudio\AvalancheCRM\Models\TicketReply) {
            return '#' . $object->ticket_id;
        }

        if (isset($object->ticket_id)) {
            return '#' . $object->ticket_id;
        }

        return null;
    }


    /**
     * Get status of the object
     */
    public function getStatusAttribute()
    {
        $object = $this->object;
        if (!$object) {
            return null;
        }

        // Special handling for Ticket status names
        if ($object instanceof \AvalancheStudio\AvalancheCRM\Models\Ticket) {
            return $object->status_relation ? $object->status_relation->name : 'N/A';
        }

        if ($object instanceof \AvalancheStudio\AvalancheCRM\Models\TicketReply && $object->ticket) {
            return $object->ticket->status_relation ? $object->ticket->status_relation->name : 'N/A';
        }

        if (isset($object->status) && is_string($object->status)) {
            return ucfirst($object->status);
        }

        return null;
    }

    /**
     * Get priority of the object
     */
    public function getPriorityAttribute()
    {
        $object = $this->object;
        if (!$object) {
            return null;
        }

        if (isset($object->priority) && is_string($object->priority)) {
            return ucfirst($object->priority);
        }

        if ($object instanceof \AvalancheStudio\AvalancheCRM\Models\TicketReply && $object->ticket) {
            return $object->ticket->priority ? ucfirst($object->ticket->priority) : null;
        }

        return null;
    }



    /**
     * Summary of changes
     */
    public function getChangesSummaryAttribute()
    {
        if (!is_array($this->data) || !isset($this->data['changes'])) {
            return null;
        }

        $changes = $this->data['changes'];
        $summary = [];

        foreach ($changes as $field => $data) {
            // Skip timestamp fields
            if (in_array($field, ['updated_at', 'created_at'])) {
                continue;
            }

            $fieldName = str_replace('_', ' ', ucfirst($field));

            if (is_array($data) && isset($data['old'], $data['new'])) {
                // Formatting for boolean values
                $old = is_bool($data['old']) ? ($data['old'] ? 'Yes' : 'No') : $data['old'];
                $new = is_bool($data['new']) ? ($data['new'] ? 'Yes' : 'No') : $data['new'];

                $summary[] = sprintf('%s: %s -> %s', $fieldName, $old, $new);
            } else {
                $summary[] = sprintf('%s changed', $fieldName);
            }
        }


        return implode(', ', $summary);
    }
}

