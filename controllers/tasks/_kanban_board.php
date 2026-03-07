<?php
$statuses = (new \AvalancheStudio\AvalancheCRM\Models\Task)->getStatusOptions();
?>

<div class="kanban-row">
    <?php foreach ($statuses as $code => $label): ?>
        <div class="kanban-column">
            <h3>
                <?= e($label) ?>
            </h3>

            <div class="kanban-tasks" data-status="<?= $code ?>" id="tasks-<?= $code ?>">
                <?php if (isset($tasks[$code])): ?>
                    <?php foreach ($tasks[$code] as $task): ?>
                        <div class="kanban-task <?= $task->timer_running ? 'kanban-task--timing' : '' ?>" data-id="<?= $task->id ?>" data-control="popup" data-handler="onLoadTaskForm"
                            data-request-data="task_id: <?= $task->id ?>">
                            <div class="task-priority priority-<?= $task->priority ?>"></div>
                            <div class="task-content">
                                <h4>
                                    <?= e($task->title) ?>
                                </h4>
                                <p>
                                    <?= e($task->project ? $task->project->name : 'No Project') ?>
                                </p>
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
                                        data-request-data="task_id: <?= $task->id ?>"
                                        data-request-flash
                                        onclick="$(this).request(); event.stopPropagation(); return false;">
                                        <i class="icon-stop"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="javascript:;" class="task-timer-toggle task-timer-toggle--start"
                                        title="Start Timer"
                                        data-request="onStartTimer"
                                        data-request-data="task_id: <?= $task->id ?>"
                                        data-request-flash
                                        onclick="$(this).request(); event.stopPropagation(); return false;">
                                        <i class="icon-play"></i>
                                    </a>
                                <?php endif ?>
                                <a href="javascript:;" data-control="popup" data-handler="onLoadTaskForm"
                                    data-request-data="task_id: <?= $task->id ?>" class="task-edit">Edit</a>
                            </div>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>