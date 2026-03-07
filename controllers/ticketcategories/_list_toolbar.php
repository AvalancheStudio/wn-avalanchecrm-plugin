<div class="control-toolbar">
    <div class="toolbar-item toolbar-primary">
        <a href="<?= Backend::url('avalanchestudio/avalanchecrm/ticketcategories/create') ?>"
            class="btn btn-primary oc-icon-plus">New Category</a>
    </div>
    <div class="toolbar-item">
        <button class="btn btn-default oc-icon-trash-o" disabled="disabled"
            onclick="$(this).data('request-data', { checked: $('.control-list').listWidget('getChecked') })"
            data-request="onDelete" data-request-confirm="Do you really want to delete the selected categories?"
            data-trigger-action="enable" data-trigger=".control-list input[type=checkbox]"
            data-trigger-condition="checked" data-stripe-load-indicator>
            Delete
        </button>
    </div>
</div>