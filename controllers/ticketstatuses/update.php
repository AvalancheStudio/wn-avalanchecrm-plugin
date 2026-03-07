<?php Block::put('breadcrumb') ?>
<ul>
    <li><a href="<?= Backend::url('avalanchestudio/avalanchecrm/ticketstatuses') ?>">Ticket Statuses</a></li>
    <li>
        <?= e($this->pageTitle) ?>
    </li>
</ul>
<?php Block::endPut() ?>

<?php if (!$this->fatalError): ?>

    <?= $this->makePartial('$/avalanchestudio/avalanchecrm/controllers/tickets/_submenu.php') ?>

    <?= Form::open(['class' => 'layout']) ?>

    <div class="layout-row">
        <?= $this->formRender() ?>
    </div>

    <div class="form-buttons">
        <div class="loading-indicator-container">
            <button type="submit" data-request="onSave" data-hotkey="ctrl+s, cmd+s" class="btn btn-primary">
                <u>S</u>ave
            </button>
            <button type="button" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter"
                class="btn btn-default">
                Save and Close
            </button>
            <button type="button" class="oc-icon-trash-o btn-icon danger pull-right" data-request="onDelete"
                data-load-indicator="<?= e(trans('backend::lang.form.deleting')) ?>"
                data-request-confirm="<?= e(trans('backend::lang.form.confirm_delete')) ?>">
            </button>
            <span class="btn-text">
                or <a href="<?= Backend::url('avalanchestudio/avalanchecrm/ticketstatuses') ?>">Cancel</a>
            </span>
        </div>
    </div>

    <?= Form::close() ?>

<?php else: ?>

    <p class="flash-message static error">
        <?= e($this->fatalError) ?>
    </p>
    <p><a href="<?= Backend::url('avalanchestudio/avalanchecrm/ticketstatuses') ?>" class="btn btn-default">Return to ticket
            statuses list</a></p>

<?php endif ?>