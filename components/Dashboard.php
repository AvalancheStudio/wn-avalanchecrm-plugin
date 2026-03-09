<?php

namespace AvalancheStudio\AvalancheCRM\Components;

use Winter\User\Facades\Auth;
use Cms\Classes\ComponentBase;
use AvalancheStudio\AvalancheCRM\Models\Client;
use AvalancheStudio\AvalancheCRM\Models\Staff;
use AvalancheStudio\AvalancheCRM\Models\Invoice;
use AvalancheStudio\AvalancheCRM\Models\Settings;

/**
 * Dashboard Component
 *
 * Provides an overview of tickets, invoices, projects, and subscriptions for the logged-in client.
 */
class Dashboard extends ComponentBase
{
    public $user;
    public $client;
    public $staff;
    public $isStaff = false;
    public $stats = [];

    public function componentDetails(): array
    {
        return [
            'name' => 'Client Dashboard',
            'description' => 'Overview of tickets, invoices, projects, and subscriptions.',
        ];
    }

    public function defineProperties(): array
    {
        return [];
    }

    public function onRun()
    {
        $this->addCss('/plugins/avalanchestudio/avalanchecrm/assets/css/dashboard.css');
        $this->addCss('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
        $this->page['themeStyles'] = \AvalancheStudio\AvalancheCRM\Classes\ThemeStyles::render();

        $this->user = Auth::getUser();
        if ($this->user) {
            $this->client = Client::where('user_id', $this->user->id)->first();
            $this->staff = Staff::where('user_id', $this->user->id)->first();
            $this->isStaff = (bool) $this->staff;

            if ($this->client) {
                $this->prepareStats();
            }
        }

        $this->page['isStaff'] = $this->isStaff;
        $this->page['crmSettings'] = Settings::instance();
    }

    protected function prepareStats()
    {
        $settings = Settings::instance();

        // Tickets
        if ($settings->enable_tickets) {
            $this->stats['tickets'] = [
                'total' => $this->client->tickets()->count(),
                'open' => $this->client->tickets()->whereHas('status_relation', function ($query) {
                    $query->where('name', '!=', 'Closed');
                })->count(),
            ];
        }

        // Invoices
        if ($settings->enable_invoices) {
            $this->stats['invoices'] = [
                'total' => $this->client->invoices()->clientVisible()->count(),
                'unpaid' => $this->client->invoices()->clientVisible()
                    ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
                    ->count(),
            ];
        }

        // Projects
        if ($settings->enable_projects) {
            $this->stats['projects'] = [
                'total' => $this->client->projects()->count(),
                'active' => $this->client->projects()->whereIn('status', ['active', 'pending', 'in_progress'])->count(),
            ];
        }

        // Subscriptions
        if ($settings->enable_subscriptions) {
            $this->stats['subscriptions'] = [
                'total' => $this->client->subscriptions()->count(),
                'active' => $this->client->subscriptions()->where('status', 'active')->count(),
            ];
        }
    }

}
