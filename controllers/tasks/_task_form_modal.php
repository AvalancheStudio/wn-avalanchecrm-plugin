<form data-request="onSaveTask" data-request-flash data-request-success="$(this).trigger('close.oc.popup')">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="popup">&times;</button>
        <h4 class="modal-title">
            <?= $taskId ? 'Edit Task' : 'Create New Task' ?>
        </h4>
    </div>
    <div class="modal-body">
        <?php if (isset($projectId)): ?>
            <input type="hidden" name="project_id" value="<?= $projectId ?>">
        <?php endif ?>

        <?php if (isset($taskId)): ?>
            <input type="hidden" name="task_id" value="<?= $taskId ?>">
        <?php endif ?>

        <?php if ($taskId && isset($task) && $task->exists): ?>
            <!-- Timer Section -->
            <div class="task-timer-section" id="taskTimerSection">
                <div class="task-timer-display">
                    <span class="task-timer-icon">
                        <?php if ($task->timer_running): ?>
                            <i class="icon-stop-circle text-danger"></i>
                        <?php else: ?>
                            <i class="icon-clock-o"></i>
                        <?php endif ?>
                    </span>
                    <span class="task-timer-clock" id="taskTimerClock"
                          data-running="<?= $task->timer_running ? '1' : '0' ?>"
                          data-elapsed="<?= $task->timer_elapsed_seconds ?>">
                        00:00:00
                    </span>
                    <span class="task-timer-total">
                        Total logged: <strong><?= e($task->formatted_hours) ?></strong>
                    </span>
                </div>
                <div class="task-timer-actions">
                    <?php if ($task->timer_running): ?>
                        <button type="button" class="btn btn-danger btn-sm"
                            data-request="onStopTimer"
                            data-request-data="task_id: <?= $task->id ?>, project_id: <?= $projectId ?>"
                            data-request-flash
                            data-request-success="toggleTaskTimerUI(false)"
                            data-load-indicator="Stopping...">
                            <i class="icon-stop"></i> Stop Timer
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-success btn-sm"
                            data-request="onStartTimer"
                            data-request-data="task_id: <?= $task->id ?>, project_id: <?= $projectId ?>"
                            data-request-flash
                            data-request-success="toggleTaskTimerUI(true)"
                            data-load-indicator="Starting...">
                            <i class="icon-play"></i> Start Timer
                        </button>
                    <?php endif ?>
                </div>
            </div>

            <?php
                $entries = $task->time_entries()->orderBy('started_at', 'desc')->limit(5)->get();
            ?>
            <?php if ($entries->count()): ?>
                <div class="task-timer-history">
                    <h5>Recent Time Entries</h5>
                    <table class="table table-condensed table-striped">
                        <thead>
                            <tr>
                                <th>Started</th>
                                <th>Stopped</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry): ?>
                                <tr>
                                    <td><?= $entry->started_at ? $entry->started_at->format('M d, H:i') : '—' ?></td>
                                    <td><?= $entry->stopped_at ? $entry->stopped_at->format('M d, H:i') : '<span class="text-success">Running...</span>' ?></td>
                                    <td><?= e($entry->formatted_duration) ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>

            <hr style="margin: 10px 0 15px;">
        <?php endif ?>

        <?= $widget->render() ?>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" data-load-indicator="Saving...">
            <?= $taskId ? 'Save Changes' : 'Create Task' ?>
        </button>
        <button type="button" class="btn btn-default" data-dismiss="popup">Cancel</button>
    </div>
</form>

<style>
.task-timer-section {
    background: #f7f9fc;
    border: 1px solid #e0e6ed;
    border-radius: 6px;
    padding: 12px 16px;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.task-timer-display {
    display: flex;
    align-items: center;
    gap: 12px;
}
.task-timer-icon i {
    font-size: 20px;
}
.task-timer-clock {
    font-family: 'Courier New', Courier, monospace;
    font-size: 22px;
    font-weight: bold;
    color: #333;
    min-width: 100px;
}
.task-timer-clock[data-running="1"] {
    color: #27ae60;
}
.task-timer-total {
    font-size: 12px;
    color: #888;
}
.task-timer-history {
    margin-top: 10px;
    margin-bottom: 0;
}
.task-timer-history h5 {
    font-size: 12px;
    text-transform: uppercase;
    color: #888;
    margin-bottom: 6px;
}
.task-timer-history .table {
    font-size: 12px;
    margin-bottom: 0;
}
</style>

<script>
(function() {
    function formatTaskTimerTime(totalSeconds) {
        var h = Math.floor(totalSeconds / 3600);
        var m = Math.floor((totalSeconds % 3600) / 60);
        var s = totalSeconds % 60;
        return (h < 10 ? '0' : '') + h + ':' +
               (m < 10 ? '0' : '') + m + ':' +
               (s < 10 ? '0' : '') + s;
    }

    window.initTaskTimerClock = function() {
        var clockEl = document.getElementById('taskTimerClock');
        if (!clockEl) return;

        var running = clockEl.getAttribute('data-running') === '1';
        var elapsed = parseInt(clockEl.getAttribute('data-elapsed') || '0', 10);

        clockEl.textContent = formatTaskTimerTime(elapsed);

        // Clear any existing interval stored on the element
        if (clockEl._timerInterval) {
            clearInterval(clockEl._timerInterval);
            clockEl._timerInterval = null;
        }

        if (running) {
            var interval = setInterval(function() {
                elapsed++;
                clockEl.textContent = formatTaskTimerTime(elapsed);
                clockEl.setAttribute('data-elapsed', elapsed);
            }, 1000);

            clockEl._timerInterval = interval;

            // Clear when popup closes
            $(clockEl).closest('.control-popup').one('close.oc.popup', function() {
                clearInterval(interval);
                clockEl._timerInterval = null;
            });
        }
    };

    window.toggleTaskTimerUI = function(isRunning) {
        var $section = $('#taskTimerSection');
        var $clock = $('#taskTimerClock');
        if (!$section.length || !$clock.length) {
            return;
        }

        // Update running flag on clock
        $clock.attr('data-running', isRunning ? '1' : '0');

        // Update icon
        var $iconSpan = $section.find('.task-timer-icon');
        if (isRunning) {
            $iconSpan.html('<i class="icon-stop-circle text-danger"></i>');
        } else {
            $iconSpan.html('<i class="icon-clock-o"></i>');
        }

        // Update button appearance and behavior
        var $btn = $section.find('.task-timer-actions button');
        if ($btn.length) {
            if (isRunning) {
                // Switch to Stop state
                $btn
                    .removeClass('btn-success').addClass('btn-danger')
                    .attr('data-request', 'onStopTimer')
                    .attr('data-load-indicator', 'Stopping...')
                    .attr('data-request-success', 'toggleTaskTimerUI(false)')
                    .html('<i class="icon-stop"></i> Stop Timer');

                // Reset elapsed to 0 for the new running session
                $clock.attr('data-elapsed', '0');
            } else {
                // Switch to Start state
                $btn
                    .removeClass('btn-danger').addClass('btn-success')
                    .attr('data-request', 'onStartTimer')
                    .attr('data-load-indicator', 'Starting...')
                    .attr('data-request-success', 'toggleTaskTimerUI(true)')
                    .html('<i class="icon-play"></i> Start Timer');
            }
        }

        // Re-init the live clock based on the new state
        initTaskTimerClock();
    };

    // Initialise clock when the modal loads
    initTaskTimerClock();
})();
</script>