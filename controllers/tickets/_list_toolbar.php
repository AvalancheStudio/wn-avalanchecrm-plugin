<div class="control-toolbar">
    <div class="toolbar-item toolbar-primary">
        <a href="<?= Backend::url('avalanchestudio/avalanchecrm/tickets/create') ?>" class="btn btn-primary oc-icon-plus">New
            Ticket</a>
    </div>
    <div class="toolbar-item">
        <button class="btn btn-default oc-icon-trash-o" disabled="disabled"
            onclick="$(this).data('request-data', { checked: $('.control-list').listWidget('getChecked') })"
            data-request="onDelete" data-request-confirm="<?= e(trans('backend::lang.list.delete_selected_confirm')) ?>"
            data-trigger-action="enable" data-trigger=".control-list input[type=checkbox]"
            data-trigger-condition="checked" data-stripe-load-indicator>
            Delete
        </button>
    </div>
</div>