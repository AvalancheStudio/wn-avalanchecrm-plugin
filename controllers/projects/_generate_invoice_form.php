<div class="modal-header">
    <button type="button" class="close" data-dismiss="popup">&times;</button>
    <h4 class="modal-title">
        <i class="icon-file-text-o"></i> Generate Invoice — <?= e($project->name) ?>
    </h4>
</div>

<form data-request="onGenerateInvoice" data-request-flash data-popup-load-indicator>
    <input type="hidden" name="project_id" value="<?= $project->id ?>">

    <div class="modal-body">

        <?php if ($billableTasks->isEmpty()): ?>
            <div class="callout callout-warning no-subheader">
                <div class="header">
                    <i class="icon-exclamation-triangle"></i>
                    <h3>No billable tasks available</h3>
                    <p>There are no completed billable tasks that haven't already been invoiced.</p>
                </div>
            </div>
        <?php else: ?>

            <p class="text-muted" style="margin-bottom: 15px;">
                Select the completed billable tasks to include on this invoice.
                Default hourly rate: <strong><?= number_format($defaultRate, 2) ?></strong>
            </p>

            <div class="control-list">
                <table class="table data" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th style="width: 30px;">
                                <input type="checkbox" id="select-all-tasks" checked>
                            </th>
                            <th>Task</th>
                            <th style="width: 90px; text-align: right;">Hours</th>
                            <th style="width: 100px; text-align: right;">Rate</th>
                            <th style="width: 110px; text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $grandTotal = 0; ?>
                        <?php foreach ($billableTasks as $task): ?>
                            <?php
                                $rate = $task->hourly_rate ?: $defaultRate;
                                $hours = $task->hours ?? 0;
                                $lineTotal = round($rate * $hours, 2);
                                $grandTotal += $lineTotal;
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="task_ids[]" value="<?= $task->id ?>" checked class="task-checkbox">
                                </td>
                                <td><?= e($task->title) ?></td>
                                <td style="text-align: right;"><?= number_format($hours, 2) ?></td>
                                <td style="text-align: right;"><?= number_format($rate, 2) ?></td>
                                <td style="text-align: right;"><strong><?= number_format($lineTotal, 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="text-align: right; font-weight: bold;">Total:</td>
                            <td style="text-align: right; font-weight: bold;" id="invoice-grand-total">
                                <?= number_format($grandTotal, 2) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label>Due Date</label>
                <input type="date" name="due_date" class="form-control"
                    value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
            </div>

            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Payment instructions or notes..."></textarea>
            </div>

        <?php endif; ?>

    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="popup">Cancel</button>
        <?php if (!$billableTasks->isEmpty()): ?>
            <button type="submit" class="btn btn-primary">
                <i class="icon-file-text-o"></i> Generate Invoice
            </button>
        <?php endif; ?>
    </div>
</form>

<script>
    // Select-all toggle
    document.getElementById('select-all-tasks')?.addEventListener('change', function () {
        document.querySelectorAll('.task-checkbox').forEach(cb => cb.checked = this.checked);
    });
</script>
