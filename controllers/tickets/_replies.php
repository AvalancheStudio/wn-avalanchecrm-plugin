<?php
if (!$formModel || !$formModel->id) {
    echo '<p class="text-muted">Save the ticket first to add replies.</p>';
    return;
}

$replies = $formModel->replies()->orderBy('created_at', 'asc')->get();
$backendUser = \Backend\Facades\BackendAuth::getUser();
?>

<div id="ticket-replies-container">
    <?php if ($replies->isEmpty()): ?>
        <p class="text-muted" style="padding: 15px 0;">No replies yet.</p>
    <?php else: ?>
        <div class="ticket-replies-list">
            <?php foreach ($replies as $reply): ?>
                <div class="reply-item reply-<?= e($reply->author_type) ?> <?= $reply->is_internal ? 'reply-internal' : '' ?>">
                    <div class="reply-header">
                        <span class="reply-author">
                            <i class="icon-<?= $reply->author_type === 'client' ? 'user' : 'user-circle' ?>"></i>
                            <?= e($reply->author_display_name) ?>
                        </span>
                        <span class="reply-type-badge reply-type-<?= e($reply->author_type) ?>">
                            <?= e(ucfirst($reply->author_type)) ?>
                        </span>
                        <?php if ($reply->is_internal): ?>
                            <span class="reply-type-badge reply-type-internal">
                                <i class="icon-lock"></i> Internal Note
                            </span>
                        <?php endif; ?>
                        <span class="reply-date"><?= $reply->created_at->format('M d, Y \a\t h:i A') ?></span>
                    </div>
                    <div class="reply-body">
                        <?= $reply->content ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Reply Form -->
    <div class="reply-form" style="margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px;">
        <h5 style="margin-bottom: 15px;"><i class="icon-reply"></i> Add Reply</h5>

        <div class="form-group">
            <textarea
                name="ticket_reply[content]"
                class="form-control"
                rows="5"
                placeholder="Type your reply here..."
                style="width: 100%;"
            ></textarea>
        </div>

        <div class="form-group" style="margin-top: 10px;">
            <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                <input type="checkbox" name="ticket_reply[is_internal]" value="1">
                <span>Internal note (not visible to client, no notification sent)</span>
            </label>
        </div>

        <button
            type="button"
            class="btn btn-primary"
            data-request="onAddReply"
            data-request-flash
            style="margin-top: 10px;"
        >
            <i class="icon-paper-plane"></i> Send Reply
        </button>
    </div>
</div>

<style>
    .ticket-replies-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .reply-item {
        background: #f9fafb;
        border: 1px solid #e8e8e8;
        border-radius: 6px;
        padding: 15px;
        border-left: 3px solid #d0d0d0;
    }

    .reply-item.reply-staff {
        border-left-color: #4a6cf7;
        background: #f5f7ff;
    }

    .reply-item.reply-client {
        border-left-color: #27ae60;
        background: #f5fdf8;
    }

    .reply-item.reply-internal {
        border-left-color: #f39c12;
        background: #fffcf0;
    }

    .reply-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
        flex-wrap: wrap;
    }

    .reply-author {
        font-weight: 600;
        color: #333;
        font-size: 13px;
    }

    .reply-type-badge {
        font-size: 11px;
        padding: 2px 8px;
        border-radius: 10px;
        font-weight: 500;
    }

    .reply-type-staff {
        background: #e8edff;
        color: #4a6cf7;
    }

    .reply-type-client {
        background: #e8f5e9;
        color: #27ae60;
    }

    .reply-type-internal {
        background: #fff3cd;
        color: #856404;
    }

    .reply-date {
        font-size: 12px;
        color: #999;
        margin-left: auto;
    }

    .reply-body {
        font-size: 14px;
        line-height: 1.6;
        color: #444;
    }

    .reply-body p:last-child {
        margin-bottom: 0;
    }
</style>
