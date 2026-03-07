<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;
use Winter\Storm\Support\Facades\Mail;
use AvalancheStudio\AvalancheCRM\Models\Client;
use AvalancheStudio\AvalancheCRM\Models\Settings;
use Carbon\Carbon;

class Campaign extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \AvalancheStudio\AvalancheCRM\Traits\LogsActivity;


    public $table = 'avalanchestudio_avalanchecrm_campaigns';

    protected $guarded = ['*'];

    protected $fillable = [
        'name',
        'subject',
        'content',
        'status',
        'scheduled_at',
        'template_id'
    ];

    public $rules = [
        'name' => 'required',
    ];

    protected $dates = [
        'scheduled_at',
        'sent_at',
        'created_at',
        'updated_at',
    ];

    public $belongsTo = [
        'template' => [
            \AvalancheStudio\AvalancheCRM\Models\EmailTemplate::class,
            'key' => 'template_id',
            'scope' => 'marketing',
        ]
    ];

    public $belongsToMany = [
        'recipients' => [
            \AvalancheStudio\AvalancheCRM\Models\Client::class,
            'table' => 'avalanchestudio_avalanchecrm_campaign_recipients',
            'key' => 'campaign_id',
            'otherKey' => 'client_id',
            'pivot' => ['status', 'sent_at', 'error_message'],
            'timestamps' => true,
        ]
    ];

    public function getStatusOptions()
    {
        return [
            'draft' => 'Draft',
            'scheduled' => 'Scheduled',
            'sending' => 'Sending',
            'sent' => 'Sent'
        ];
    }

    /**
     * Get all available data tags with descriptions.
     *
     * @return array
     */
    public static function getAvailableTags(): array
    {
        return [
            '{{client.name}}' => 'Client full name',
            '{{client.first_name}}' => 'Client first name',
            '{{client.last_name}}' => 'Client last name (everything after first word)',
            '{{client.email}}' => 'Client email address',
            '{{client.company}}' => 'Client company name',
            '{{client.phone}}' => 'Client phone number',
            '{{company.name}}' => 'Your company name (from Settings)',
            '{{company.email}}' => 'Your company email (from Settings)',
            '{{company.phone}}' => 'Your company phone (from Settings)',
            '{{company.address}}' => 'Your company address (from Settings)',
            '{{unsubscribe_url}}' => 'Unsubscribe link URL for this client',
            '{{date}}' => 'Current date (e.g. 1 January 2025)',
            '{{year}}' => 'Current year (e.g. 2025)',
        ];
    }

    /**
     * Replace data tags in content with actual values for a given client.
     *
     * Supported tags:
     *   {{client.name}}, {{client.first_name}}, {{client.last_name}},
     *   {{client.email}}, {{client.company}}, {{client.phone}},
     *   {{company.name}}, {{company.email}}, {{company.phone}}, {{company.address}},
     *   {{unsubscribe_url}}, {{date}}, {{year}}
     *
     * @param string $content  The raw content with tags
     * @param Client $client   The client to personalise for
     * @return string
     */
    public static function parseTags(string $content, $model): string
    {
        // Model data
        $nameParts = explode(' ', trim($model->name ?? ''), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';

        // Company settings
        $companyName = Settings::get('company_name', '');
        $companyEmail = Settings::get('company_email', '');
        $companyPhone = Settings::get('company_phone', '');
        $companyAddress = Settings::get('company_address', '');

        $replacements = [
            '{{client.name}}' => $model->name ?? '',
            '{{client.first_name}}' => $firstName,
            '{{client.last_name}}' => $lastName,
            '{{client.email}}' => $model->email ?? '',
            '{{client.company}}' => $model->company ?? '',
            '{{client.phone}}' => $model->phone ?? '',
            '{{company.name}}' => $companyName,
            '{{company.email}}' => $companyEmail,
            '{{company.phone}}' => $companyPhone,
            '{{company.address}}' => $companyAddress,
            '{{unsubscribe_url}}' => method_exists($model, 'getUnsubscribeUrl') ? $model->getUnsubscribeUrl() : '',
            '{{date}}' => Carbon::now()->format('j F Y'),
            '{{year}}' => Carbon::now()->format('Y'),
        ];

        // Also support {{staff.name}} etc for clarity if it's a staff member
        if ($model instanceof \AvalancheStudio\AvalancheCRM\Models\Staff) {
            $replacements['{{staff.name}}'] = $model->name;
            $replacements['{{staff.email}}'] = $model->email;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Send this campaign to all opted-in clients.
     *
     * @return array ['sent' => int, 'failed' => int, 'skipped' => int]
     */
    public function sendToClients(): array
    {
        $clients = Client::marketable()->get();

        $this->status = 'sending';
        $this->total_recipients = $clients->count();
        $this->save();

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($clients as $client) {
            // Skip clients without valid email
            if (empty($client->email)) {
                $skipped++;
                continue;
            }

            try {
                // Parse data tags in content and subject
                $body = static::parseTags($this->content, $client);
                $subject = static::parseTags($this->subject ?: $this->name, $client);

                // Append unsubscribe footer
                $unsubscribeUrl = $client->getUnsubscribeUrl();
                $body .= '<p style="font-size:12px;color:#999;text-align:center;margin-top:30px;border-top:1px solid #eee;padding-top:15px;">';
                $body .= 'You are receiving this because you are a client of ' . e(Settings::get('company_name', '')) . '. ';
                $body .= '<a href="' . $unsubscribeUrl . '" style="color:#999;">Unsubscribe from marketing emails</a>';
                $body .= '</p>';

                Mail::raw(['html' => $body], function ($message) use ($client, $subject) {
                    $message->to($client->email, $client->name);
                    $message->subject($subject);
                });

                // Record in pivot
                $this->recipients()->syncWithoutDetaching([
                    $client->id => [
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]
                ]);

                $sent++;
            } catch (\Exception $e) {
                $this->recipients()->syncWithoutDetaching([
                    $client->id => [
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]
                ]);

                $failed++;
            }
        }

        $this->status = 'sent';
        $this->sent_at = now();
        $this->sent_count = $sent;
        $this->failed_count = $failed;
        $this->save();

        return compact('sent', 'failed', 'skipped');
    }
}
