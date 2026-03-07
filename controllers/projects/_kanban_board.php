<?php
$statuses = (new \AvalancheStudio\AvalancheCRM\Models\Task)->getStatusOptions();
$projectTasks = $formModel->tasks()->orderBy('sort_order')->get()->groupBy('status');
?>

<div class="kanban-row">
    <?php foreach ($statuses as $code => $label): ?>
        <div class="kanban-column">
            <h3>
                <?= e($label) ?>
            </h3>

            <div class="kanban-tasks" data-status="<?= $code ?>" id="project-tasks-<?= $code ?>">
                <?php if (isset($projectTasks[$code])): ?>
                    <?php foreach ($projectTasks[$code] as $task): ?>
                        <div class="kanban-task <?= $task->timer_running ? 'kanban-task--timing' : '' ?>" data-id="<?= $task->id ?>" data-control="popup" data-handler="onLoadTaskForm"
                            data-request-data="task_id: <?= $task->id ?>, project_id: <?= $formModel->id ?>">
                            <div class="task-priority priority-<?= $task->priority ?>"></div>
                            <div class="task-content">
                                <h4>
                                    <?= e($task->title) ?>
                                </h4>
                                <div class="task-footer">
                                    <span class="user">
                                        <?= e($task->assigned_to ? $task->assigned_to->login : 'Unassigned') ?>
                                    </span>
                                    <?php if ($task->hours > 0 || $task->timer_running): ?>
                                        <span class="task-time-badge <?= $task->timer_running ? 'task-time-badge--running' : '' ?>">
                                            <?php if ($task->timer_running): ?>
                                                <i class="icon-clock-o"></i> Running
                                            <?php else: ?>
                                                <i class="icon-hourglass-half"></i> <?= e($task->formatted_hours) ?>
                                            <?php endif ?>
                                        </span>
                                    <?php endif ?>
                                    <?php if ($task->due_date && $task->due_date instanceof \DateTimeInterface): ?>
                                        <span class="date">
                                            <?= $task->due_date->format('M d') ?>
                                        </span>
                                    <?php endif ?>
                                </div>
                            </div>
                            <div class="task-card-actions">
                                <?php if ($task->timer_running): ?>
                                    <a href="javascript:;" class="task-timer-toggle task-timer-toggle--stop"
                                        title="Stop Timer"
                                        data-request="onStopTimer"
                                        data-request-data="task_id: <?= $task->id ?>, project_id: <?= $formModel->id ?>"
                                        data-request-flash
                                        onclick="$(this).request(); event.stopPropagation(); return false;">
                                        <i class="icon-stop"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="javascript:;" class="task-timer-toggle task-timer-toggle--start"
                                        title="Start Timer"
                                        data-request="onStartTimer"
                                        data-request-data="task_id: <?= $task->id ?>, project_id: <?= $formModel->id ?>"
                                        data-request-flash
                                        onclick="$(this).request(); event.stopPropagation(); return false;">
                                        <i class="icon-play"></i>
                                    </a>
                                <?php endif ?>
                                <a href="javascript:;" data-control="popup" data-handler="onLoadTaskForm"
                                    data-request-data="task_id: <?= $task->id ?>, project_id: <?= $formModel->id ?>"
                                    class="task-edit">Edit</a>
                            </div>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
.kanban-task--timing {
    border-left: 3px solid #27ae60;
}
.task-time-badge {
    font-size: 11px;
    color: #888;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.task-time-badge--running {
    color: #27ae60;
    font-weight: 600;
    animation: timerPulse 1.5s ease-in-out infinite;
}
@keyframes timerPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.task-card-actions {
    display: flex;
    align-items: center;
    gap: 4px;
}
.task-timer-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    font-size: 11px;
    text-decoration: none;
    opacity: 0.6;
    transition: opacity 0.2s;
}
.task-timer-toggle:hover {
    opacity: 1;
    text-decoration: none;
}
.task-timer-toggle--start {
    color: #27ae60;
}
.task-timer-toggle--start:hover {
    color: #27ae60;
    background: rgba(39, 174, 96, 0.1);
}
.task-timer-toggle--stop {
    color: #e74c3c;
    opacity: 1;
}
.task-timer-toggle--stop:hover {
    color: #e74c3c;
    background: rgba(231, 76, 60, 0.1);
}
</style>