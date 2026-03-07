<?php
use AvalancheStudio\AvalancheCRM\Models\Campaign;
$tags = Campaign::getAvailableTags();
?>
<div class="callout fade in callout-info" style="margin-top: 10px; margin-bottom: 10px;">
    <div class="header">
        <i class="icon-tag"></i>
        <h3 style="display:inline; margin-left:5px;">Available Data Tags</h3>
    </div>
    <p style="margin: 10px 0 5px;">Insert these tags into your content or subject line. They will be replaced with real data for each recipient when the campaign is sent.</p>
    <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;">
        <?php foreach ($tags as $tag => $description): ?>
            <span
                title="<?= e($description) ?>"
                style="background:#e8edf6;color:#333;padding:4px 10px;border-radius:4px;font-family:monospace;font-size:13px;cursor:help;border:1px solid #d0d7e6;"
            ><?= e($tag) ?></span>
        <?php endforeach; ?>
    </div>
    <div style="margin-top:12px;">
        <table class="table table-condensed" style="font-size:12px;margin-bottom:0;">
            <thead>
                <tr>
                    <th style="width:200px;">Tag</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tags as $tag => $description): ?>
                <tr>
                    <td><code><?= e($tag) ?></code></td>
                    <td><?= e($description) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
