<?php

namespace AvalancheStudio\AvalancheCRM\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\BackendMenu;

/**
 * Activity Logs Backend Controller
 */
class ActivityLogs extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['avalanchestudio.avalanchecrm.*'];

    /**
     * @var string Current category filter
     */
    protected $categoryFilter = null;

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('AvalancheStudio.AvalancheCRM', 'avalanchecrm', 'logs');
    }

    public function index()
    {
        $this->pageTitle = 'Activity Log Overview';
    }

    public function category($category = null)
    {
        $this->categoryFilter = $category;
        $this->pageTitle = 'Activity Logs: ' . ($category ? ucfirst($category) : 'All');

        return $this->asExtension('ListController')->index();
    }

    public function listExtendQuery($query)
    {
        if (!$this->categoryFilter || $this->categoryFilter === 'all') {
            return;
        }

        $modules = [];
        switch ($this->categoryFilter) {
            case 'projects':
                $modules = ['Projects', 'Tasks', 'TimeEntries'];
                break;
            case 'support':
                $modules = ['Tickets', 'TicketReplies', 'TicketCategories', 'TicketStatuses', 'TicketTypes'];
                break;
            case 'billing':
                $modules = ['Invoices', 'Invoices', 'Subscriptions', 'SubscriptionPlans', 'Transactions'];
                break;
            case 'entities':
                $modules = ['Staff', 'Clients'];
                break;
            case 'marketing':
                $modules = ['Campaigns', 'EmailTemplates'];
                break;
        }

        if (!empty($modules)) {
            $query->whereIn('module', $modules);
        }
    }
}

