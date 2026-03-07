<?php
    $campaign = $formModel ?? null;
    $recipients = $campaign ? $campaign->recipients()->withPivot('status', 'sent_at', 'error_message')->get() : collect();
?>
<?php if ($recipients->isEmpty()): ?>
    <p class="text-muted">No recipients yet. Send the campaign to populate this list.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Sent At</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recipients as $client): ?>
                    <tr>
                        <td><?= e($client->name) ?></td>
                        <td><?= e($client->email) ?></td>
                        <td>
                            <?php if ($client->pivot->status === 'sent'): ?>
                                <span class="label label-success">Sent</span>
                            <?php elseif ($client->pivot->status === 'failed'): ?>
                                <span class="label label-danger">Failed</span>
                            <?php else: ?>
                                <span class="label label-default">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $client->pivot->sent_at ?: '—' ?></td>
                        <td><?= e($client->pivot->error_message ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
