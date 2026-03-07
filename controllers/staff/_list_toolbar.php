<div class="control-toolbar">
    <div class="toolbar-item toolbar-primary">
        <a href="<?= \Backend::url('winter/user/users/create?is_staff=1') ?>" class="btn btn-primary oc-icon-plus">New
            Staff Member</a>
        <button class="btn btn-default oc-icon-trash-o" disabled="disabled" onclick="$(this).data('request-data', {
                checked: $('.control-list').listWidget('getChecked')
            })" data-request="onDelete" data-request-confirm="Are you sure?" data-trigger-action="enable"
            data-trigger=".control-list input[type=checkbox]:checked" data-trigger-condition="checked">
            Delete Selected
        </button>
    </div>
</div>