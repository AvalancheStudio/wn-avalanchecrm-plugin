<?php

namespace AvalancheStudio\AvalancheCRM\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\BackendMenu;
use Backend\Facades\BackendAuth;
use Winter\Storm\Support\Facades\Flash;
use AvalancheStudio\AvalancheCRM\Models\Ticket;
use AvalancheStudio\AvalancheCRM\Models\TicketReply;

/**
 * Tickets Backend Controller
 */
class Tickets extends Controller
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
        'avalanchestudio.avalanchecrm.tickets.manage_all',
    ];

    public function listExtendQuery($query)
    {
        if ($status = request()->get('status')) {
            $statusModel = \AvalancheStudio\AvalancheCRM\Models\TicketStatus::where('name', 'like', $status)->first();
            if ($statusModel) {
                $query->where('status_id', $statusModel->id);
            }
        }
    }

    /**
     * AJAX handler: add a reply to a ticket from the backend.
     */
    public function onAddReply()
    {
        $ticket = $this->formFindModelObject(post('id') ?: $this->params[0]);
        $replyData = post('ticket_reply', []);

        if (empty(trim(strip_tags($replyData['content'] ?? '')))) {
            Flash::error('Reply content cannot be empty.');
            return;
        }

        $backendUser = BackendAuth::getUser();

        $reply = new TicketReply();
        $reply->ticket_id = $ticket->id;
        $reply->author_type = 'staff';
        $reply->author_name = trim($backendUser->first_name . ' ' . $backendUser->last_name);
        $reply->content = $replyData['content'];
        $reply->is_internal = !empty($replyData['is_internal']);
        $reply->save();

        Flash::success('Reply added successfully.');

        return \Redirect::refresh();
    }

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('AvalancheStudio.AvalancheCRM', 'avalanchecrm', 'tickets');
    }
}
