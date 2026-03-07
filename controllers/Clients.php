<?php

namespace AvalancheStudio\AvalancheCRM\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\BackendMenu;

/**
 * Clients Backend Controller
 */
class Clients extends Controller
{
    /**
     * @var array Behaviors that are implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    /**
     * @var array Permissions required to view this page.
     */
    protected $requiredPermissions = [
        'avalanchestudio.avalanchecrm.clients.manage_all',
    ];

    public function listExtendQuery($query)
    {
        $query->whereHas('user', function ($q) {
            $q->whereHas('groups', function ($g) {
                $g->where('code', 'client');
            });
        });
    }
}
