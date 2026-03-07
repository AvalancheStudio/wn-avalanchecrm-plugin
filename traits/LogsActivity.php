<?php

namespace AvalancheStudio\AvalancheCRM\Traits;

use AvalancheStudio\AvalancheCRM\Models\ActivityLog;

trait LogsActivity
{
    /**
     * Boot the trait
     */
    public static function bootLogsActivity()
    {
        static::created(function ($model) {
            $model->logActivity('Created');
        });

        static::updated(function ($model) {
            $attributes = $model->getChanges();

            // Ignore if no changes or only updated_at changed
            unset($attributes['updated_at']);
            if (empty($attributes)) {
                return;
            }

            $changes = [];
            foreach ($attributes as $key => $newValue) {
                $changes[$key] = [
                    'old' => $model->getOriginal($key),
                    'new' => $newValue
                ];
            }

            $model->logActivity('Updated', null, ['changes' => $changes]);
        });


        static::deleted(function ($model) {
            $model->logActivity('Deleted');
        });
    }


    /**
     * Log a custom activity
     */
    public function logActivity($action, $message = null, $data = null)
    {
        $module = $this->getActivityModule();

        if (!$message) {
            $name = $this->getActivityName() ?: 'item';
            $message = sprintf('%s %s #%s', $action, $name, $this->getKey());
        }

        return ActivityLog::log($message, $module, $action, $this, $data);
    }

    /**
     * Get module name for logging
     */
    protected function getActivityModule()
    {
        if (property_exists($this, 'activityModule')) {
            return $this->activityModule;
        }

        $className = class_basename($this);
        return str_plural($className);
    }

    /**
     * Get display name for logging
     */
    protected function getActivityName()
    {
        if (property_exists($this, 'activityName')) {
            return $this->activityName;
        }

        return class_basename($this);
    }
}
