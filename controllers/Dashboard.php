<?php

namespace AvalancheStudio\AvalancheCRM\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\BackendMenu;
use AvalancheStudio\AvalancheCRM\Models\Client;
use AvalancheStudio\AvalancheCRM\Models\Project;
use AvalancheStudio\AvalancheCRM\Models\Ticket;
use AvalancheStudio\AvalancheCRM\Models\Invoice;
use AvalancheStudio\AvalancheCRM\Models\Subscription;
use AvalancheStudio\AvalancheCRM\Models\Staff;
use AvalancheStudio\AvalancheCRM\Models\Task;
use AvalancheStudio\AvalancheCRM\Models\TicketStatus;

/**
 * Dashboard Backend Controller
 */
class Dashboard extends Controller
{
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('AvalancheStudio.AvalancheCRM', 'avalanchecrm', 'dashboard');
    }

    public function index()
    {
        $this->pageTitle = 'Dashboard';
        $settings = \AvalancheStudio\AvalancheCRM\Models\Settings::instance();

        $this->vars['totalClients'] = Client::count();
        $this->vars['totalStaff'] = Staff::count();

        if ($settings->enable_projects) {
            $this->vars['activeProjects'] = Project::where('status', 'active')->count();
            $this->vars['pendingTasks'] = Task::whereIn('status', ['todo', 'in_progress'])->count();
        }

        if ($settings->enable_tickets) {
            $closedStatuses = TicketStatus::whereIn('name', ['Closed', 'Resolved'])->pluck('id')->toArray();
            $this->vars['openTickets'] = Ticket::whereNotIn('status_id', $closedStatuses)->count();
        }

        if ($settings->enable_subscriptions) {
            $this->vars['activeSubscriptions'] = Subscription::where('status', 'active')->count();
        }

        if ($settings->enable_invoices) {
            $this->vars['unpaidInvoices'] = Invoice::whereIn('status', ['outstanding', 'due', 'overdue'])->count();
        }
    }

}
