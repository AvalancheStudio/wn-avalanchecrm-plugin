<?php if ($formModel->exists && $formModel->billing_type !== 'non_billable'): ?>
    <div style="margin-top: 10px;">
        <button type="button"
            class="btn btn-primary oc-icon-file-text-o"
            data-control="popup"
            data-handler="onLoadGenerateInvoiceForm"
            data-request-data="project_id: <?= $formModel->id ?>">
            Generate Invoice from Billable Tasks
        </button>

        <?php
            $uninvoicedCount = $formModel->tasks()
                ->where('is_billable', true)
                ->where('is_invoiced', false)
                ->where('hours', '>', 0)
                ->count();
        ?>
        <span class="text-muted" style="margin-left: 10px;">
            <?= $uninvoicedCount ?> uninvoiced billable task<?= $uninvoicedCount !== 1 ? 's' : '' ?> ready
        </span>
    </div>
<?php elseif ($formModel->exists): ?>
    <p class="text-muted">Set the billing type to <strong>Hourly</strong> or <strong>Fixed Price</strong> to enable invoice generation.</p>
<?php endif; ?>
