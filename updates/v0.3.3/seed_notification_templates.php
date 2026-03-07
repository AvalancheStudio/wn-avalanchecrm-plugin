<?php

namespace AvalancheStudio\AvalancheCRM\Updates;

use Winter\Storm\Database\Updates\Seeder;
use AvalancheStudio\AvalancheCRM\Models\EmailTemplate;

class SeedNotificationTemplates extends Seeder
{
    public function run()
    {
        $templates = [
            // â”€â”€ Client Notifications â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            [
                'name' => 'Client Welcome',
                'category' => 'client',
                'subject' => 'Welcome to {{company.name}}, {{client.first_name}}!',
                'is_active' => true,
                'content' => $this->clientWelcome(),
            ],
            [
                'name' => 'Client Account Update',
                'category' => 'client',
                'subject' => 'Your account details have been updated',
                'is_active' => true,
                'content' => $this->clientAccountUpdate(),
            ],

            // â”€â”€ Project Notifications â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            [
                'name' => 'New Project Created',
                'category' => 'project',
                'subject' => 'New project started: your project is underway',
                'is_active' => true,
                'content' => $this->projectCreated(),
            ],
            [
                'name' => 'Project Status Update',
                'category' => 'project',
                'subject' => 'Project update from {{company.name}}',
                'is_active' => true,
                'content' => $this->projectStatusUpdate(),
            ],
            [
                'name' => 'Project Completed',
                'category' => 'project',
                'subject' => 'Your project is complete!',
                'is_active' => true,
                'content' => $this->projectCompleted(),
            ],

            // â”€â”€ Ticket Notifications â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            [
                'name' => 'Ticket Created Confirmation',
                'category' => 'ticket',
                'subject' => 'Your support ticket has been received',
                'is_active' => true,
                'content' => $this->ticketCreated(),
            ],
            [
                'name' => 'Ticket Reply',
                'category' => 'ticket',
                'subject' => 'New reply on your support ticket',
                'is_active' => true,
                'content' => $this->ticketReply(),
            ],
            [
                'name' => 'Ticket Resolved',
                'category' => 'ticket',
                'subject' => 'Your support ticket has been resolved',
                'is_active' => true,
                'content' => $this->ticketResolved(),
            ],

            // â”€â”€ Invoice Notifications â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            [
                'name' => 'Invoice Sent',
                'category' => 'invoice',
                'subject' => 'You have a new invoice from {{company.name}}',
                'is_active' => true,
                'content' => $this->invoiceSent(),
            ],
            [
                'name' => 'Payment Received',
                'category' => 'invoice',
                'subject' => 'Payment received â€” thank you!',
                'is_active' => true,
                'content' => $this->paymentReceived(),
            ],
            [
                'name' => 'Invoice Overdue Reminder',
                'category' => 'invoice',
                'subject' => 'Reminder: You have an outstanding invoice',
                'is_active' => true,
                'content' => $this->invoiceOverdue(),
            ],

            // â”€â”€ Subscription Notifications â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            [
                'name' => 'Subscription Activated',
                'category' => 'subscription',
                'subject' => 'Your subscription is now active',
                'is_active' => true,
                'content' => $this->subscriptionActivated(),
            ],
            [
                'name' => 'Subscription Renewal Reminder',
                'category' => 'subscription',
                'subject' => 'Your subscription is due for renewal',
                'is_active' => true,
                'content' => $this->subscriptionRenewal(),
            ],
            [
                'name' => 'Subscription Cancelled',
                'category' => 'subscription',
                'subject' => 'Your subscription has been cancelled',
                'is_active' => true,
                'content' => $this->subscriptionCancelled(),
            ],
        ];

        foreach ($templates as $template) {
            // Only create if a template with this name doesn't already exist
            if (!\Db::table('avalanchestudio_avalanchecrm_email_templates')->where('name', $template['name'])->exists()) {
                \Db::table('avalanchestudio_avalanchecrm_email_templates')->insert(array_merge($template, [
                    'created_at' => now(),
                    'updated_at' => now()
                ]));
            }
        }
    }

    // =====================================================================
    //  Template Content Helpers
    // =====================================================================

    protected function wrapInLayout(string $body, string $accentColor = '#4a6cf7'): string
    {
        return <<<HTML
<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;color:#333333;">
    <div style="background-color:{$accentColor};padding:30px 40px;border-radius:8px 8px 0 0;text-align:center;">
        <h1 style="color:#ffffff;margin:0;font-size:24px;">{{company.name}}</h1>
    </div>
    <div style="background-color:#ffffff;padding:40px;border:1px solid #e0e0e0;border-top:none;">
        {$body}
    </div>
    <div style="background-color:#f8f9fa;padding:20px 40px;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px;text-align:center;">
        <p style="font-size:12px;color:#999999;margin:0;">
            {{company.name}} &bull; {{company.address}}<br>
            {{company.phone}} &bull; {{company.email}}
        </p>
        <p style="font-size:12px;color:#999999;margin:10px 0 0;">
            &copy; {{year}} {{company.name}}. All rights reserved.
        </p>
    </div>
</div>
HTML;
    }

    // â”€â”€ Client â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    protected function clientWelcome(): string
    {
        return $this->wrapInLayout(<<<'BODY'
<p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>
<p style="font-size:16px;line-height:1.6;">Welcome to <strong>{{company.name}}</strong>! We're delighted to have you as a client.</p>
<p style="font-size:16px;line-height:1.6;">Your account has been set up and you now have access to our client portal where you can:</p>
<ul style="font-size:16px;line-height:1.8;color:#555555;">
    <li>View and manage your projects</li>
    <li>Submit and track support tickets</li>
    <li>View invoices and make payments</li>
    <li>Manage your subscriptions</li>
</ul>
<p style="font-size:16px;line-height:1.6;">If you have any questions, contact us at <a href="mailto:{{company.email}}" style="color:#4a6cf7;">{{company.email}}</a>.</p>
<p style="font-size:16px;line-height:1.6;">Best regards,<br><strong>The {{company.name}} Team</strong></p>
BODY, '#28a745');
    }

    protected function clientAccountUpdate(): string
    {
        return $this->wrapInLayout(<<<'BODY'
<p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>
<p style="font-size:16px;line-height:1.6;">This is to confirm that your account details have been updated.</p>
<p style="font-size:16px;line-height:1.6;">If you did not make this change, please contact us immediately at <a href="mailto:{{company.email}}" style="color:#4a6cf7;">{{company.email}}</a>.</p>
<p style="font-size:16px;line-height:1.6;">Kind regards,<br><strong>{{company.name}}</strong></p>
BODY);
    }

    // â”€â”€ Project â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    protected function projectCreated(): string
    {
        return $this->wrapInLayout(<<<'BODY'
<p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>
<p style="font-size:16px;line-height:1.6;">Great news â€” a new project has been created for you and work is underway!</p>
<p style="font-size:16px;line-height:1.6;">You can track progress and milestones through your client portal at any time.</p>
<p style="font-size:16px;line-height:1.6;">If you have any questions or need to discuss requirements, please don't hesitate to reach out.</p>
<p style="font-size:16px;line-height:1.6;">Best regards,<br><strong>{{company.name}}</strong></p>
BODY, '#17a2b8');
    }

    protected function projectStatusUpdate(): string
    {
        return $this->wrapInLayout(<<<'BODY'
<p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>
<p style="font-size:16px;line-height:1.6;">We wanted to keep you updated on your project.</p>
<p style="font-size:16px;line-height:1.6;">Edit this template to include project-specific details and status updates.</p>
<p style="font-size:16px;line-height:1.6;">You can view the full project details in your client portal.</p>
<p style="font-size:16px;line-height:1.6;">Best regards,<br><strong>{{company.name}}</strong></p>
BODY, '#17a2b8');
    }

    protected function projectCompleted(): string
    {
        return $this->wrapInLayout(<<<'BODY'
<p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>
<p style="font-size:16px;line-height:1.6;">We're pleased to let you know that your project has been completed! ðŸŽ‰</p>
<p style="font-size:16px;line-height:1.6;">Please take a moment to review the deliverables in your client portal and let us know if everything looks good.</p>
<p style="font-size:16px;line-height:1.6;">It's been a pleasure working with you on this. If there's anything else we can help with, don't hesitate to get in touch.</p>
<p style="font-size:16px;line-height:1.6;">Best regards,<br><strong>{{company.name}}</strong></p>
BODY, '#28a745');
    }

    // â”€â”€ Ticket â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    protected function ticketCreated(): string
    {
        return $this->wrapInLayout(<<<'BODY'
<p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>
<p style="font-size:16px;line-height:1.6;">Thank you for contacting us. Your support ticket has been received and our team will review it shortly.</p>
<p style="font-size:16px;line-height:1.6;">You can track the status of your ticket through your client portal.</p>
<p style="font-size:16px;line-height:1.6;">We aim to respond within one business day. If your issue is urgent, please contact us directly at <a href="mailto:{{company.email}}" style="color:#4a6cf7;">{{company.email}}</a>.</p>
<p style="font-size:16px;line-height:1.6;">Kind regards,<br><strong>{{company.name}} Support</strong></p>
BODY, '#6f42c1');
    }

    protected function ticketReply(): string
    {
        return $this->wrapInLayout(<<<'BODY'
<p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>
<p style="font-size:16px;line-height:1.6;">There's a new reply on your support ticket.</p>
<p style="font-size:16px;line-height:1.6;">Please log in to your client portal to view the reply and respond if needed.</p>
<p style="font-size:16px;line-height:1.6;">Kind regards,<br><strong>{{company.name}} Support</strong></p>
BODY, '#6f42c1');
    }

    protected function ticketResolved(): string
    {
        return $this->wrapInLayout(<<<'BODY'
<p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>
<p style="font-size:16px;line-height:1.6;">Your support ticket has been marked as resolved.</p>
<p style="font-size:16px;line-height:1.6;">If you feel this issue hasn't been fully addressed, you can reopen the ticket through your client portal.</p>
<p style="font-size:16px;line-height:1.6;">Thank you for your patience â€” we're here if you need further help.</p>
<p style="font-size:16px;line-height:1.6;">Kind regards,<br><strong>{{company.name}} Support</strong></p>
BODY, '#28a745');
    }

    // â”€â”€ Invoice â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    protected function invoiceSent(): string
    {
        return $this->wrapInLayout(<<<'BODY'
<p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>
<p style="font-size:16px;line-height:1.6;">A new invoice has been generated for your account.</p>
<p style="font-size:16px;line-height:1.6;">You can view and pay this invoice online through your client portal.</p>
<p style="font-size:16px;line-height:1.6;">If you have any questions about this invoice, please contact us at <a href="mailto:{{company.email}}" style="color:#4a6cf7;">{{company.email}}</a>.</p>
<p style="font-size:16px;line-height:1.6;">Kind regards,<br><strong>{{company.name}}</strong></p>
BODY, '#fd7e14');
    }

    protected function paymentReceived(): string
    {
        return $this->wrapInLayout(<<<'BODY'
<p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>
<p style="font-size:16px;line-height:1.6;">We've received your payment â€” thank you!</p>
<p style="font-size:16px;line-height:1.6;">A receipt is available in your client portal for your records.</p>
<p style="font-size:16px;line-height:1.6;">We appreciate your prompt payment. If you need anything else, we're here to help.</p>
<p style="font-size:16px;line-height:1.6;">Kind regards,<br><strong>{{company.name}}</strong></p>
BODY, '#28a745');
    }

    protected function invoiceOverdue(): string
    {
        return $this->wrapInLayout(<<<'BODY'
<p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>
<p style="font-size:16px;line-height:1.6;">This is a friendly reminder that you have an outstanding invoice that is now overdue.</p>
<p style="font-size:16px;line-height:1.6;">Please log in to your client portal to view and settle the invoice at your earliest convenience.</p>
<p style="font-size:16px;line-height:1.6;">If you've already made the payment, please disregard this message. Otherwise, if you need to discuss payment options, contact us at <a href="mailto:{{company.email}}" style="color:#4a6cf7;">{{company.email}}</a>.</p>
<p style="font-size:16px;line-height:1.6;">Kind regards,<br><strong>{{company.name}}</strong></p>
BODY, '#dc3545');
    }

    // â”€â”€ Subscription â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    protected function subscriptionActivated(): string
    {
        return $this->wrapInLayout(<<<'BODY'
<p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>
<p style="font-size:16px;line-height:1.6;">Your subscription is now active! ðŸŽ‰</p>
<p style="font-size:16px;line-height:1.6;">You can manage your subscription, view billing details, and update your preferences through your client portal.</p>
<p style="font-size:16px;line-height:1.6;">If you have any questions, reach out to us at <a href="mailto:{{company.email}}" style="color:#4a6cf7;">{{company.email}}</a>.</p>
<p style="font-size:16px;line-height:1.6;">Kind regards,<br><strong>{{company.name}}</strong></p>
BODY, '#28a745');
    }

    protected function subscriptionRenewal(): string
    {
        return $this->wrapInLayout(<<<'BODY'
<p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>
<p style="font-size:16px;line-height:1.6;">Just a heads-up â€” your subscription is approaching its renewal date.</p>
<p style="font-size:16px;line-height:1.6;">No action is needed if you'd like to continue. Your subscription will renew automatically.</p>
<p style="font-size:16px;line-height:1.6;">If you'd like to make any changes, you can update your subscription through your client portal or contact us at <a href="mailto:{{company.email}}" style="color:#4a6cf7;">{{company.email}}</a>.</p>
<p style="font-size:16px;line-height:1.6;">Kind regards,<br><strong>{{company.name}}</strong></p>
BODY, '#fd7e14');
    }

    protected function subscriptionCancelled(): string
    {
        return $this->wrapInLayout(<<<'BODY'
<p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>
<p style="font-size:16px;line-height:1.6;">Your subscription has been cancelled as requested.</p>
<p style="font-size:16px;line-height:1.6;">If this was done in error, or if you change your mind, you can reactivate your subscription through your client portal or by contacting us.</p>
<p style="font-size:16px;line-height:1.6;">We'd love to have you back any time. Thank you for being a valued client of {{company.name}}.</p>
<p style="font-size:16px;line-height:1.6;">Kind regards,<br><strong>{{company.name}}</strong></p>
BODY, '#6c757d');
    }
}
