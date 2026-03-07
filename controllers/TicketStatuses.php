<?php

namespace AvalancheStudio\AvalancheCRM\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\BackendMenu;

/**
 * Ticket Statuses Backend Controller
 */
class TicketStatuses extends Controller
{
    /**
     * @var array Behaviors that are implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    /**
     * @var string Configuration file for the `FormController` behavior.
     */
    public $formConfig = 'config_form.yaml';

    /**
     * @var string Configuration file for the `ListController` behavior.
     */
    public $listConfig = 'config_list.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('AvalancheStudio.AvalancheCRM', 'avalanchecrm', 'tickets');
    }
}
