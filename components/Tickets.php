<?php

namespace AvalancheStudio\AvalancheCRM\Components;

use Winter\User\Facades\Auth;
use Winter\Storm\Support\Facades\Flash;
use Winter\Storm\Support\Facades\Input;
use Redirect;
use Winter\Storm\Support\Facades\Log;
use Cms\Classes\ComponentBase;
use AvalancheStudio\AvalancheCRM\Models\Client;
use AvalancheStudio\AvalancheCRM\Models\Ticket;
use AvalancheStudio\AvalancheCRM\Models\TicketCategory;
use AvalancheStudio\AvalancheCRM\Models\TicketStatus;
use AvalancheStudio\AvalancheCRM\Models\TicketReply;
use AvalancheStudio\AvalancheCRM\Models\Settings;
use Winter\Storm\Exception\ApplicationException;

/**
 * ClientTickets Component
 *
 * Allows frontend clients to view, create and manage support tickets.
 */
class Tickets extends ComponentBase
{
    /**
     * @var Client The authenticated client.
     */
    public $client;

    /**
     * @var \Winter\Storm\Database\Collection Tickets belonging to the client.
     */
    public $tickets;

    /**
     * @var Ticket|null The currently viewed ticket (detail view).
     */
    public $activeTicket;

    /**
     * @var Settings CRM settings instance.
     */
    public $settings;

    /**
     * Component details.
     */
    public function componentDetails(): array
    {
        return [
            'name' => 'Tickets',
            'description' => 'Allows clients to view, create and manage their support tickets.',
        ];
    }

    /**
     * Defines the properties used by this component.
     */
    public function defineProperties(): array
    {
        return [
            'ticketsPerPage' => [
                'title' => 'Tickets Per Page',
                'description' => 'Number of tickets to display per page.',
                'type' => 'string',
                'default' => '10',
                'validationPattern' => '^[0-9]+$',
                'validationMessage' => 'Please enter a number.',
            ],
            'allowCreate' => [
                'title' => 'Allow Create',
                'description' => 'Allow clients to create new tickets.',
                'type' => 'checkbox',
                'default' => true,
            ],
            'allowClose' => [
                'title' => 'Allow Close',
                'description' => 'Allow clients to close their own tickets.',
                'type' => 'checkbox',
                'default' => true,
            ],
            'allowReopen' => [
                'title' => 'Allow Reopen',
                'description' => 'Allow clients to reopen closed/resolved tickets.',
                'type' => 'checkbox',
                'default' => true,
            ],
        ];
    }

    /**
     * Prepare variables before the page loads.
     */
    public function onRun()
    {
        $settings = Settings::instance();
        if (!$settings->enable_tickets) {
            return Redirect::to('/');
        }

        $this->addCss('/plugins/avalanchestudio/avalanchecrm/assets/css/tickets.css');

        $this->page['themeStyles'] = \AvalancheStudio\AvalancheCRM\Classes\ThemeStyles::render();

        $this->prepareVars();
    }

    /**
     * Set up all page variables.
     */
    protected function prepareVars()
    {
        $user = Auth::getUser();

        if (!$user) {
            return;
        }

        $this->settings = $this->page['settings'] = Settings::instance();
        $this->client = $this->page['client'] = Client::where('user_id', $user->id)->first();

        if (!$this->client) {
            return;
        }

        // Check if a specific ticket is requested
        $ticketId = Input::get('ticket');

        if ($ticketId) {
            $this->activeTicket = $this->page['activeTicket'] = Ticket::where('id', $ticketId)
                ->where('client_id', $this->client->id)
                ->with(['project', 'category', 'staff', 'replies', 'attachments'])
                ->first();
        }

        // Load all client tickets
        $this->tickets = $this->page['tickets'] = Ticket::where('client_id', $this->client->id)
            ->with(['project', 'category', 'staff'])
            ->orderBy('created_at', 'desc')
            ->paginate($this->property('ticketsPerPage', 10));

        $this->page['allowCreate'] = $this->property('allowCreate');
        $this->page['allowClose'] = $this->property('allowClose');
        $this->page['allowReopen'] = $this->property('allowReopen');
        $this->page['categories'] = TicketCategory::orderBy('name')->get();
        $this->page['clientProjects'] = $this->client->projects()->orderBy('name')->get();
    }

    /**
     * AJAX: Load ticket detail view.
     */
    public function onViewTicket()
    {
        $client = $this->getAuthenticatedClient();

        $ticketId = Input::get('ticket_id');

        if (!$ticketId) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.ticket_not_specified'));
        }

        $ticket = Ticket::where('id', $ticketId)
            ->where('client_id', $client->id)
            ->with(['project', 'category', 'staff', 'replies', 'attachments'])
            ->first();

        if (!$ticket) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.ticket_not_found'));
        }

        $this->page['activeTicket'] = $ticket;
        $this->page['allowClose'] = $this->property('allowClose');
        $this->page['allowReopen'] = $this->property('allowReopen');

        return [
            '#tickets-detail' => $this->renderPartial('@detail'),
        ];
    }

    /**
     * AJAX: Return to the ticket list view.
     */
    public function onBackToList()
    {
        $this->prepareVars();

        return [
            '#tickets-list' => $this->renderPartial('@list'),
            '#tickets-detail' => '',
            '#tickets-create' => '',
        ];
    }

    /**
     * AJAX: Show the create ticket form.
     */
    public function onShowCreateForm()
    {
        $client = $this->getAuthenticatedClient();

        $this->page['categories'] = TicketCategory::orderBy('name')->get();
        $this->page['clientProjects'] = $client->projects()->orderBy('name')->get();

        return [
            '#tickets-create' => $this->renderPartial('@create'),
            '#tickets-list' => '',
            '#tickets-detail' => '',
        ];
    }

    /**
     * AJAX: Create a new ticket.
     */
    public function onCreateTicket()
    {
        trace_log('onCreateTicket called. Input: ' . json_encode(Input::all()));
        $client = $this->getAuthenticatedClient();

        $data = Input::get('ticket', []);

        if (empty($data['subject'])) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.subject_required'));
        }

        // Sanitise description HTML
        $description = '';
        if (!empty($data['description'])) {
            $description = strip_tags(
                $data['description'],
                '<p><br><b><strong><i><em><u><ul><ol><li><a><h1><h2><h3><h4><h5><h6><span><div>'
            );
        }

        $ticket = new Ticket();
        $ticket->client_id = $client->id;
        $ticket->subject = $data['subject'];
        $ticket->description = $description;
        $ticket->priority = $data['priority'] ?? 'medium';

        // Set default status if available
        $defaultStatus = TicketStatus::where('is_default', true)->first() ?: TicketStatus::where('name', 'open')->first();
        if ($defaultStatus) {
            $ticket->status_id = $defaultStatus->id;
            $ticket->status = strtolower($defaultStatus->name);
        } else {
            $ticket->status = 'open';
        }

        if (!empty($data['category_id'])) {
            $ticket->category_id = $data['category_id'];
        }

        if (!empty($data['project_id'])) {
            // Verify client owns the project
            $project = $client->projects()->find($data['project_id']);
            if ($project) {
                $ticket->project_id = $project->id;
            }
        }

        $ticket->save();

        // Bind any uploaded attachments
        $files = Input::file('attachments', []);
        if (!is_array($files)) {
            $files = [$files];
        }
        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                $fileModel = new \System\Models\File();
                $fileModel->fromPost($file);
                $ticket->attachments()->add($fileModel);
            }
        }

        Flash::success(trans('avalanchestudio.avalanchecrm::lang.messages.ticket_created'));

        $this->prepareVars();

        return [
            '#tickets-list' => $this->renderPartial('@list'),
            '#tickets-create' => '',
            '#tickets-detail' => '',
        ];
    }

    /**
     * AJAX: Filter tickets by status.
     */
    public function onFilterTickets()
    {
        $client = $this->getAuthenticatedClient();

        $status = Input::get('status');

        $query = Ticket::where('client_id', $client->id)
            ->with(['project', 'category', 'staff']);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $this->page['tickets'] = $query->orderBy('created_at', 'desc')
            ->paginate($this->property('ticketsPerPage', 10));

        $this->page['allowCreate'] = $this->property('allowCreate');
        $this->page['allowClose'] = $this->property('allowClose');
        $this->page['allowReopen'] = $this->property('allowReopen');

        return [
            '#tickets-list' => $this->renderPartial('@list'),
        ];
    }

    /**
     * AJAX: Close a ticket.
     */
    public function onCloseTicket()
    {
        $client = $this->getAuthenticatedClient();

        $ticketId = Input::get('ticket_id');
        $ticket = Ticket::where('id', $ticketId)
            ->where('client_id', $client->id)
            ->first();

        if (!$ticket) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.ticket_not_found'));
        }

        $ticket->status = 'closed';
        $ticket->save();

        Flash::success(trans('avalanchestudio.avalanchecrm::lang.messages.ticket_closed'));

        return $this->refreshTicketDetail($client, $ticketId);
    }

    /**
     * AJAX: Reopen a ticket.
     */
    public function onReopenTicket()
    {
        $client = $this->getAuthenticatedClient();

        $ticketId = Input::get('ticket_id');
        $ticket = Ticket::where('id', $ticketId)
            ->where('client_id', $client->id)
            ->first();

        if (!$ticket) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.ticket_not_found'));
        }

        $ticket->status = 'open';
        $ticket->save();

        Flash::success(trans('avalanchestudio.avalanchecrm::lang.messages.ticket_reopened'));

        return $this->refreshTicketDetail($client, $ticketId);
    }

    /**
     * AJAX: Update ticket priority.
     */
    public function onUpdateTicketPriority()
    {
        $client = $this->getAuthenticatedClient();

        $ticketId = Input::get('ticket_id');
        $priority = Input::get('priority');

        $ticket = Ticket::where('id', $ticketId)
            ->where('client_id', $client->id)
            ->first();

        if (!$ticket) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.ticket_not_found'));
        }

        if (!in_array($priority, ['low', 'medium', 'high'])) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.invalid_priority'));
        }

        $ticket->priority = $priority;
        $ticket->save();

        Flash::success(trans('avalanchestudio.avalanchecrm::lang.messages.priority_updated'));

        return $this->refreshTicketDetail($client, $ticketId);
    }

    /**
     * AJAX: Add a reply to a ticket (from the client frontend).
     */
    public function onAddReply()
    {
        $client = $this->getAuthenticatedClient();

        $ticketId = Input::get('ticket_id');
        $content = trim(Input::get('reply_content', ''));

        if (empty($content)) {
            throw new ApplicationException('Reply content cannot be empty.');
        }

        $ticket = Ticket::where('id', $ticketId)
            ->where('client_id', $client->id)
            ->first();

        if (!$ticket) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.ticket_not_found'));
        }

        if ($ticket->status === 'closed') {
            throw new ApplicationException('Cannot reply to a closed ticket.');
        }

        $reply = new TicketReply();
        $reply->ticket_id = $ticket->id;
        $reply->author_type = 'client';
        $reply->author_name = $client->name;
        $reply->content = $content;
        $reply->is_internal = false;
        $reply->save();

        Flash::success('Reply added successfully.');

        return $this->refreshTicketDetail($client, $ticketId);
    }

    /**
     * AJAX: Serve a ticket attachment for download (ownership-checked).
     */
    public function onDownloadAttachment()
    {
        $client = $this->getAuthenticatedClient();

        $fileId = (int) Input::get('file_id');
        $ticketId = (int) Input::get('ticket_id');

        // Verify the ticket belongs to this client
        $ticket = Ticket::where('id', $ticketId)
            ->where('client_id', $client->id)
            ->first();

        if (!$ticket) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.ticket_not_found'));
        }

        $file = $ticket->attachments()->where('id', $fileId)->first();
        if (!$file) {
            throw new ApplicationException('Attachment not found.');
        }

        return \Response::download($file->getLocalPath(), $file->file_name);
    }

    /**
     * Helper: get the authenticated client or throw.
     */
    protected function getAuthenticatedClient(): Client
    {
        $user = Auth::getUser();
        if (!$user) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.must_be_logged_in'));
        }

        $client = Client::where('user_id', $user->id)->first();
        if (!$client) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.no_client_profile'));
        }

        return $client;
    }

    /**
     * Helper: reload ticket detail after an update.
     */
    protected function refreshTicketDetail(Client $client, int $ticketId): array
    {
        $ticket = Ticket::where('id', $ticketId)
            ->where('client_id', $client->id)
            ->with(['project', 'category', 'staff', 'replies', 'attachments'])
            ->first();

        $this->page['activeTicket'] = $ticket;
        $this->page['allowClose'] = $this->property('allowClose');
        $this->page['allowReopen'] = $this->property('allowReopen');

        return [
            '#tickets-detail' => $this->renderPartial('@detail'),
        ];
    }
}
