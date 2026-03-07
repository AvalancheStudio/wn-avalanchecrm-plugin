<?php
    $campaign = $this->formGetModel();
    $canSend = in_array($campaign->status, ['draft', 'scheduled']);
?>
<?php if ($canSend): ?>
    <button
        type="button"
        class="btn btn-success wn-icon-send"
        data-request="onSendCampaign"
        data-request-data="campaign_id: '<?= $campaign->id ?>'"
        data-request-confirm="This will send the campaign to all clients who have not opted out of marketing emails. Continue?"
        data-stripe-load-indicator>
        Send Campaign Now
    </button>
<?php elseif ($campaign->status === 'sent'): ?>
    <span class="btn btn-disabled btn-default" disabled>
        <i class="icon-check"></i> Campaign Sent
    </span>
<?php elseif ($campaign->status === 'sending'): ?>
    <span class="btn btn-disabled btn-warning" disabled>
        <i class="icon-spinner icon-spin"></i> Sending…
    </span>
<?php endif; ?>
