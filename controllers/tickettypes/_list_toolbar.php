<div class="layout-row">
    <div class="toolbar-widget list-header" id="Toolbar-listToolbar">
        <div class="control-toolbar">
            <div class="toolbar-item">
                <a href="<?= Backend::url('avalanchestudio/avalanchecrm/tickettypes/create') ?>"
                    class="btn btn-primary oc-icon-plus">
                    New Ticket Type
                </a>
                <button class="btn btn-default oc-icon-trash-o" disabled="disabled" onclick="$(this).data('request-data', {
                        checked: $('.control-list').listWidget('getChecked')
                    })" data-request="onDelete"
                    data-request-confirm="<?= e(trans('backend::lang.list.delete_selected_confirm')) ?>"
                    data-trigger-action="enable" data-trigger=".control-list input[type=checkbox]"
                    data-trigger-condition="checked">
                    <?= e(trans('backend::lang.list.delete_selected')) ?>
                </button>
            </div>
        </div>
    </div>
</div>