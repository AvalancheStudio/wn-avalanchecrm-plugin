<form data-request="onCreateTask" data-request-flash>
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="popup">&times;</button>
        <h4 class="modal-title">Create New Task</h4>
    </div>
    <div class="modal-body">
        <?php if (isset($projectId)): ?>
            <input type="hidden" name="project_id" value="<?= $projectId ?>">
        <?php endif ?>

        <?= $widget->render() ?>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" data-load-indicator="Saving...">Create Task</button>
        <button type="button" class="btn btn-default" data-dismiss="popup">Cancel</button>
    </div>
</form>