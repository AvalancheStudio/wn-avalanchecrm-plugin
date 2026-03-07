<?php if ($formModel->exists): ?>
    <div style="margin-bottom: 15px;">
        <a href="<?= \Backend\Facades\Backend::url('avalanchestudio/avalanchecrm/invoices/pdf/' . $formModel->id) ?>"
           class="btn btn-default oc-icon-file-pdf-o"
           target="_blank">
            Download PDF
        </a>
    </div>
<?php endif; ?>
