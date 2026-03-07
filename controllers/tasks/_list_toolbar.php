<div class="control-toolbar">
    <div class="toolbar-item toolbar-primary">
        <a href="<?= Backend::url('avalanchestudio/avalanchecrm/tasks/create') ?>" class="btn btn-primary oc-icon-plus">New
            Task</a>
        <a href="<?= Backend::url('avalanchestudio/avalanchecrm/tasks/kanban') ?>"
            class="btn btn-info oc-icon-th-large">Kanban Board</a>
    </div>
    <div class="toolbar-item">
        <button class="btn btn-default oc-icon-trash-o" disabled="disabled" onclick="$(this).data('request-data', {
                checked: $('.control-list').listWidget('getChecked')
            })" data-request="onDelete" data-request-confirm="Are you sure?" data-trigger-action="enable"
            data-trigger=".control-list input[type=checkbox]" data-trigger-condition="checked"
            data-stripe-load-indicator>
            Delete
        </button>
    </div>
</div>