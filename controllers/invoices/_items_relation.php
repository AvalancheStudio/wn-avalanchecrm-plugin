<?php if ($formModel->exists): ?>
    <?= $this->relationRender('items') ?>
<?php else: ?>
    <p class="text-muted">Please save the invoice first, then you can add line items.</p>
<?php endif; ?>
