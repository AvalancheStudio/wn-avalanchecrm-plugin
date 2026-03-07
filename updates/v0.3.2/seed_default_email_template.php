<?php

namespace AvalancheStudio\AvalancheCRM\Updates;

use Winter\Storm\Database\Updates\Seeder;
use Db;

class SeedDefaultEmailTemplate extends Seeder
{
    protected $table = 'avalanchestudio_avalanchecrm_email_templates';

    public function run()
    {
        // Only seed if no templates exist yet
        if (Db::table($this->table)->count() > 0) {
            return;
        }

        $now = now();

        Db::table($this->table)->insert([
            'name'       => 'Default Newsletter',
            'subject'    => 'News from {{company.name}}',
            'is_active'  => true,
            'content'    => $this->getDefaultContent(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Db::table($this->table)->insert([
            'name'       => 'Welcome Email',
            'subject'    => 'Welcome to {{company.name}}, {{client.first_name}}!',
            'is_active'  => true,
            'content'    => $this->getWelcomeContent(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Db::table($this->table)->insert([
            'name'       => 'Promotional Offer',
            'subject'    => '{{client.first_name}}, we have something special for you',
            'is_active'  => true,
            'content'    => $this->getPromoContent(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function getDefaultContent(): string
    {
        return <<<'HTML'
<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;color:#333333;">
    <!-- Header -->
    <div style="background-color:#4a6cf7;padding:30px 40px;border-radius:8px 8px 0 0;text-align:center;">
        <h1 style="color:#ffffff;margin:0;font-size:24px;">{{company.name}}</h1>
    </div>

    <!-- Body -->
    <div style="background-color:#ffffff;padding:40px;border:1px solid #e0e0e0;border-top:none;">
        <p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>

        <p style="font-size:16px;line-height:1.6;">We hope this message finds you well. Here are the latest updates and news we wanted to share with you.</p>

        <p style="font-size:16px;line-height:1.6;">Replace this content with your newsletter message. You can use data tags to personalise the email for each recipient.</p>

        <!-- CTA Button -->
        <div style="text-align:center;margin:30px 0;">
            <a href="#" style="background-color:#4a6cf7;color:#ffffff;padding:14px 30px;text-decoration:none;border-radius:5px;font-size:16px;font-weight:bold;display:inline-block;">Learn More</a>
        </div>

        <p style="font-size:16px;line-height:1.6;">If you have any questions, feel free to reach out to us at <a href="mailto:{{company.email}}" style="color:#4a6cf7;">{{company.email}}</a>.</p>

        <p style="font-size:16px;line-height:1.6;">
            Best regards,<br>
            <strong>{{company.name}}</strong>
        </p>
    </div>

    <!-- Footer -->
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

    protected function getWelcomeContent(): string
    {
        return <<<'HTML'
<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;color:#333333;">
    <!-- Header -->
    <div style="background-color:#28a745;padding:30px 40px;border-radius:8px 8px 0 0;text-align:center;">
        <h1 style="color:#ffffff;margin:0;font-size:24px;">Welcome Aboard! ðŸŽ‰</h1>
    </div>

    <!-- Body -->
    <div style="background-color:#ffffff;padding:40px;border:1px solid #e0e0e0;border-top:none;">
        <p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>

        <p style="font-size:16px;line-height:1.6;">Welcome to <strong>{{company.name}}</strong>! We're thrilled to have you on board.</p>

        <p style="font-size:16px;line-height:1.6;">As a valued client, you now have access to all our services. Here's what you can expect:</p>

        <ul style="font-size:16px;line-height:1.8;color:#555555;">
            <li>Dedicated support from our team</li>
            <li>Regular project updates and progress reports</li>
            <li>Easy online invoice management and payments</li>
            <li>Direct communication through our ticket system</li>
        </ul>

        <!-- CTA Button -->
        <div style="text-align:center;margin:30px 0;">
            <a href="#" style="background-color:#28a745;color:#ffffff;padding:14px 30px;text-decoration:none;border-radius:5px;font-size:16px;font-weight:bold;display:inline-block;">Get Started</a>
        </div>

        <p style="font-size:16px;line-height:1.6;">If you need anything at all, don't hesitate to contact us at <a href="mailto:{{company.email}}" style="color:#28a745;">{{company.email}}</a> or call us on {{company.phone}}.</p>

        <p style="font-size:16px;line-height:1.6;">
            Welcome aboard,<br>
            <strong>The {{company.name}} Team</strong>
        </p>
    </div>

    <!-- Footer -->
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

    protected function getPromoContent(): string
    {
        return <<<'HTML'
<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;color:#333333;">
    <!-- Header -->
    <div style="background-color:#ff6b35;padding:30px 40px;border-radius:8px 8px 0 0;text-align:center;">
        <h1 style="color:#ffffff;margin:0;font-size:24px;">Special Offer Just For You</h1>
    </div>

    <!-- Body -->
    <div style="background-color:#ffffff;padding:40px;border:1px solid #e0e0e0;border-top:none;">
        <p style="font-size:16px;line-height:1.6;">Hi {{client.first_name}},</p>

        <p style="font-size:16px;line-height:1.6;">We appreciate your continued trust in <strong>{{company.name}}</strong>, and we'd like to offer you something special.</p>

        <!-- Offer Box -->
        <div style="background-color:#fff8f4;border:2px dashed #ff6b35;border-radius:8px;padding:25px;text-align:center;margin:25px 0;">
            <p style="font-size:14px;color:#ff6b35;margin:0 0 5px;text-transform:uppercase;letter-spacing:1px;">Limited Time Offer</p>
            <p style="font-size:28px;font-weight:bold;color:#ff6b35;margin:0;">Your Promotion Here</p>
            <p style="font-size:14px;color:#666;margin:10px 0 0;">Customise this section with your offer details</p>
        </div>

        <p style="font-size:16px;line-height:1.6;">This offer is exclusively for our valued clients like you, {{client.first_name}}. Don't miss out!</p>

        <!-- CTA Button -->
        <div style="text-align:center;margin:30px 0;">
            <a href="#" style="background-color:#ff6b35;color:#ffffff;padding:14px 30px;text-decoration:none;border-radius:5px;font-size:16px;font-weight:bold;display:inline-block;">Claim Your Offer</a>
        </div>

        <p style="font-size:16px;line-height:1.6;">Questions? Contact us at <a href="mailto:{{company.email}}" style="color:#ff6b35;">{{company.email}}</a>.</p>

        <p style="font-size:16px;line-height:1.6;">
            Cheers,<br>
            <strong>{{company.name}}</strong>
        </p>
    </div>

    <!-- Footer -->
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
}
