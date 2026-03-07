<?php
    $eligibleCount = \AvalancheStudio\AvalancheCRM\Models\Client::marketable()->count();
    $totalClients = \AvalancheStudio\AvalancheCRM\Models\Client::count();
    $optedOut = $totalClients - $eligibleCount;
?>
<div class="form-group">
    <label class="control-label">Eligible Recipients</label>
    <p class="form-control-static">
        <strong><?= $eligibleCount ?></strong> of <?= $totalClients ?> clients can receive this campaign.
        <?php if ($optedOut > 0): ?>
            <br><small class="text-muted"><?= $optedOut ?> client(s) have opted out of marketing emails.</small>
        <?php endif; ?>
    </p>
</div>
