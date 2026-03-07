<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;

/**
 * Settings Model
 */
class Settings extends Model
{
    public $implement = [\System\Behaviors\SettingsModel::class];

    // A unique code
    public $settingsCode = 'avalanchestudio_avalanchecrm_settings';

    // Reference to field configuration
    public $settingsFields = 'fields.yaml';

    public $attachOne = [
        'company_logo' => [\System\Models\File::class]
    ];

    /**
     * Initialize default settings
     */
    public function initSettingsData()
    {
        $this->enable_projects = true;
        $this->enable_tickets = true;
        $this->enable_marketing = true;
        $this->enable_subscriptions = true;
        $this->enable_invoices = true;
        $this->invoice_prefix = 'INV';
        $this->invoice_next_number = 1;
        $this->currency_code = 'USD';
        $this->currency_symbol = '$';
    }
}

