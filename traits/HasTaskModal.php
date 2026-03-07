<?php

namespace AvalancheStudio\AvalancheCRM\Traits;

use AvalancheStudio\AvalancheCRM\Models\Task;
use AvalancheStudio\AvalancheCRM\Models\Project;
use Backend\Classes\Controller;
use Flash;

trait HasTaskModal
{
    /**
     * AJAX handler to load the task form (Create or Update) in a modal
     */
    public function onLoadTaskForm()
    {
        $taskId = post('task_id');
        $task = $taskId ? Task::find($taskId) : new Task();

        $this->vars['widget'] = $this->makeTaskFormWidget($task);
        $this->vars['projectId'] = post('project_id');
        $this->vars['taskId'] = $taskId;
        $this->vars['task'] = $task;

        return $this->makePartial('$/avalanchestudio/avalanchecrm/controllers/tasks/_task_form_modal.php');
    }

    /**
     * AJAX handler to save a task (Create or Update) and refresh the Kanban board
     */
    public function onSaveTask()
    {
        $data = post('Task');
        $taskId = post('task_id');
        $projectId = post('project_id');

        if (isset($data['due_date']) && empty($data['due_date'])) {
            $data['due_date'] = null;
        }

        $task = $taskId ? Task::find($taskId) : new Task();

        // Preserve timer state and tracked hours when saving from the edit modal.
        // Otherwise the stale form values can overwrite changes made by the
        // start/stop timer buttons (which update the model immediately).
        $existingTimerRunning   = $task->timer_running;
        $existingTimerStartedAt = $task->timer_started_at;
        $existingHours          = $task->hours;
        $hasTimeEntries         = $task->exists ? $task->time_entries()->exists() : false;

        $task->fill($data);

        if ($taskId) {
            $task->timer_running   = $existingTimerRunning;
            $task->timer_started_at = $existingTimerStartedAt;

            // If time entries exist, hours are derived from them and should
            // not be overwritten by the (stale) manual input in the form.
            if ($hasTimeEntries) {
                $task->hours = $existingHours;
            }
        }

        if (!$taskId && $projectId) {
            $task->project_id = $projectId;
        }

        $task->save();

        Flash::success($taskId ? 'Task updated successfully' : 'Task created successfully');

        return $this->refreshKanbanBoard($projectId ?: $task->project_id);
    }

    /**
     * AJAX handler: Start the timer on a task.
     */
    public function onStartTimer()
    {
        $taskId = post('task_id');
        $task = Task::findOrFail($taskId);

        $userId = \BackendAuth::getUser()?->id;
        $task->startTimer($userId);

        Flash::success('Timer started for "' . $task->title . '".');

        $projectId = post('project_id') ?: $task->project_id;
        return $this->refreshKanbanBoard($projectId);
    }

    /**
     * AJAX handler: Stop the timer on a task.
     */
    public function onStopTimer()
    {
        $taskId = post('task_id');
        $task = Task::findOrFail($taskId);

        $task->stopTimer();

        Flash::success('Timer stopped. Logged ' . $task->formatted_hours . ' total.');

        $projectId = post('project_id') ?: $task->project_id;
        return $this->refreshKanbanBoard($projectId);
    }

    /**
     * Helper to refresh the board based on context
     */
    protected function refreshKanbanBoard($projectId = null)
    {
        if ($projectId) {
            // Refresh integrated board
            $project = Project::find($projectId);
            return [
                '#project-kanban-board-container' => $this->makePartial('$/avalanchestudio/avalanchecrm/controllers/projects/_kanban_board.php', [
                    'formModel' => $project
                ])
            ];
        } else {
            // Refresh standalone board
            $this->vars['tasks'] = Task::orderBy('sort_order')->get()->groupBy('status');
            return [
                '#tasks-kanban-board-container' => $this->makePartial('$/avalanchestudio/avalanchecrm/controllers/tasks/_kanban_board.php')
            ];
        }
    }

    /**
     * Create the form widget for the task
     */
    protected function makeTaskFormWidget($model)
    {
        $config = $this->makeConfig('$/avalanchestudio/avalanchecrm/models/task/fields.yaml');
        $config->model = $model;
        $config->arrayName = 'Task';
        $config->alias = 'taskForm';

        return $this->makeWidget(\Backend\Widgets\Form::class, $config);
    }
}
