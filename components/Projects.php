<?php

namespace AvalancheStudio\AvalancheCRM\Components;

use Winter\User\Facades\Auth;
use Winter\Storm\Support\Facades\Flash;
use Winter\Storm\Support\Facades\Input;
use Winter\Storm\Support\Facades\Redirect;
use Winter\Storm\Support\Facades\Log;
use Cms\Classes\ComponentBase;
use AvalancheStudio\AvalancheCRM\Models\Client;
use AvalancheStudio\AvalancheCRM\Models\Project;
use AvalancheStudio\AvalancheCRM\Models\Task;
use AvalancheStudio\AvalancheCRM\Models\Staff;
use AvalancheStudio\AvalancheCRM\Models\Settings;
use Winter\Storm\Exception\ApplicationException;

/**
 * ClientProjects Component
 *
 * Allows frontend clients to view and manage projects assigned to them,
 * including viewing project details, tasks, tickets, and invoices.
 */
class Projects extends ComponentBase
{
    /**
     * @var Client The authenticated client.
     */
    public $client;

    /**
     * @var \Winter\Storm\Database\Collection Projects assigned to the client.
     */
    public $projects;

    /**
     * @var Project|null The currently selected project (detail view).
     */
    public $activeProject;

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
            'name' => 'Projects',
            'description' => 'Allows clients to view and manage projects assigned to them.',
        ];
    }

    /**
     * Defines the properties used by this component.
     */
    public function defineProperties(): array
    {
        return [
            'showTasks' => [
                'title' => 'Show Tasks',
                'description' => 'Display project tasks to the client.',
                'type' => 'checkbox',
                'default' => true,
            ],
            'showTickets' => [
                'title' => 'Show Tickets',
                'description' => 'Display project tickets to the client.',
                'type' => 'checkbox',
                'default' => true,
            ],
            'showInvoices' => [
                'title' => 'Show Invoices',
                'description' => 'Display project invoices to the client.',
                'type' => 'checkbox',
                'default' => true,
            ],
            'projectsPerPage' => [
                'title' => 'Projects Per Page',
                'description' => 'Number of projects to display per page.',
                'type' => 'string',
                'default' => '10',
                'validationPattern' => '^[0-9]+$',
                'validationMessage' => 'Please enter a number.',
            ],
        ];
    }

    /**
     * Prepare variables before the page loads.
     */
    public function onRun()
    {
        $settings = Settings::instance();
        if (!$settings->enable_projects) {
            return Redirect::to('/');
        }

        $this->addCss('/plugins/avalanchestudio/avalanchecrm/assets/css/projects.css');

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

        // Check if a specific project is requested
        $projectId = Input::get('project');

        if ($projectId) {
            $this->activeProject = $this->page['activeProject'] = $this->client
                ->projects()
                ->with(['tasks.assigned_to', 'tickets', 'invoices', 'staff'])
                ->find($projectId);

            if ($this->activeProject) {
                $this->prepareProjectStaffVars($this->activeProject);
            }
        }

        // Load all client projects with counts
        $this->projects = $this->page['projects'] = $this->client
            ->projects()
            ->withCount(['tasks', 'tickets', 'invoices'])
            ->orderBy('created_at', 'desc')
            ->paginate($this->property('projectsPerPage', 10));

        $this->page['showTasks'] = $this->property('showTasks');
        $this->page['showTickets'] = $this->property('showTickets');
        $this->page['showInvoices'] = $this->property('showInvoices');
        $this->page['currencySymbol'] = $this->settings->currency_symbol ?? '$';
        $this->page['currencyCode'] = $this->settings->currency_code ?? 'USD';
    }

    /**
     * AJAX: Load project detail view.
     */
    public function onViewProject()
    {
        $user = Auth::getUser();
        if (!$user) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.must_be_logged_in'));
        }

        $client = Client::where('user_id', $user->id)->first();
        if (!$client) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.no_client_profile'));
        }

        $projectId = Input::get('project_id');
        if (!$projectId) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.project_not_specified'));
        }

        $project = $client->projects()
            ->with(['tasks.assigned_to', 'tickets', 'invoices', 'staff'])
            ->find($projectId);

        if (!$project) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.project_not_found'));
        }

        $this->page['activeProject'] = $project;
        $this->page['showTasks'] = $this->property('showTasks');
        $this->page['showTickets'] = $this->property('showTickets');
        $this->page['showInvoices'] = $this->property('showInvoices');
        $this->page['currencySymbol'] = $this->settings->currency_symbol ?? '$';
        $this->page['currencyCode'] = $this->settings->currency_code ?? 'USD';
        $this->prepareProjectStaffVars($project);

        return [
            '#projects-detail' => $this->renderPartial('@detail'),
        ];
    }

    /**
     * AJAX: Return to the project list view.
     */
    public function onBackToList()
    {
        $this->prepareVars();

        return [
            '#projects-list' => $this->renderPartial('@list'),
            '#projects-detail' => '',
        ];
    }

    /**
     * AJAX: Update a task belonging to a client's project.
     */
    public function onUpdateTask()
    {
        $user = Auth::getUser();
        if (!$user) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.must_be_logged_in'));
        }

        $client = Client::where('user_id', $user->id)->first();
        if (!$client) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.no_client_profile'));
        }

        $taskId = Input::get('task_id');
        $projectId = Input::get('project_id');

        if (!$taskId || !$projectId) {
            throw new ApplicationException('Missing task or project identifier.');
        }

        // Verify the client owns this project
        $project = $client->projects()->find($projectId);
        if (!$project) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.project_not_found'));
        }

        $task = Task::where('id', $taskId)->where('project_id', $projectId)->first();
        if (!$task) {
            throw new ApplicationException('Task not found.');
        }

        // Only allow updating specific fields
        $allowed = ['status', 'priority', 'title', 'description', 'due_date', 'assigned_to_id'];
        $data = array_intersect_key(Input::get('task', []), array_flip($allowed));

        if (isset($data['due_date']) && empty($data['due_date'])) {
            $data['due_date'] = null;
        }

        // Handle assigned_to_id (allow unassigning)
        if (array_key_exists('assigned_to_id', $data) && empty($data['assigned_to_id'])) {
            $data['assigned_to_id'] = null;
        }

        // Sanitise rich text description
        if (isset($data['description'])) {
            $data['description'] = strip_tags(
                $data['description'],
                '<p><br><b><strong><i><em><u><ul><ol><li><a><h1><h2><h3><h4><h5><h6><span><div>'
            );
        }

        $task->fill($data);
        $task->save();

        Flash::success(trans('avalanchestudio.avalanchecrm::lang.messages.task_updated'));

        return $this->refreshProjectDetail($client, $projectId);
    }

    /**
     * Helper: reload project detail after an update.
     */
    protected function refreshProjectDetail(Client $client, int $projectId): array
    {
        $project = $client->projects()
            ->with(['tasks.assigned_to', 'tickets', 'invoices', 'staff'])
            ->find($projectId);

        $this->page['activeProject'] = $project;
        $this->page['showTasks'] = $this->property('showTasks');
        $this->page['showTickets'] = $this->property('showTickets');
        $this->page['showInvoices'] = $this->property('showInvoices');
        $this->page['currencySymbol'] = (Settings::instance())->currency_symbol ?? '$';
        $this->page['currencyCode'] = (Settings::instance())->currency_code ?? 'USD';
        $this->prepareProjectStaffVars($project);

        return [
            '#projects-detail' => $this->renderPartial('@detail'),
        ];
    }

    /**
     * Helper: build staff lookup and task assignee map for the given project.
     */
    protected function prepareProjectStaffVars(Project $project): void
    {
        $this->page['projectStaff'] = $project->staff;

        // Build a display-name map: task_id => assignee name
        // Checks project staff first (by ID match), then falls back to the
        // Backend\Models\User relation for legacy assignments.
        $staffById = $project->staff->keyBy('id');
        $taskAssignees = [];

        foreach ($project->tasks as $task) {
            if ($task->assigned_to_id && isset($staffById[$task->assigned_to_id])) {
                $taskAssignees[$task->id] = $staffById[$task->assigned_to_id]->name;
            } elseif ($task->assigned_to) {
                $taskAssignees[$task->id] = trim(
                    ($task->assigned_to->first_name ?? '') . ' ' . ($task->assigned_to->last_name ?? '')
                );
            }
        }

        $this->page['taskAssignees'] = $taskAssignees;
    }

    /**
     * AJAX: Filter projects by status.
     */
    public function onFilterProjects()
    {
        $user = Auth::getUser();
        if (!$user) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.must_be_logged_in'));
        }

        $client = Client::where('user_id', $user->id)->first();
        if (!$client) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.no_client_profile'));
        }

        $status = Input::get('status');

        $query = $client->projects()->withCount(['tasks', 'tickets', 'invoices']);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $this->page['projects'] = $query->orderBy('created_at', 'desc')
            ->paginate($this->property('projectsPerPage', 10));

        return [
            '#projects-list' => $this->renderPartial('@list'),
        ];
    }

    /**
     * AJAX: Start a timer on a task belonging to a client's project.
     */
    public function onStartTaskTimer()
    {
        $user = Auth::getUser();
        if (!$user) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.must_be_logged_in'));
        }

        $client = Client::where('user_id', $user->id)->first();
        if (!$client) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.no_client_profile'));
        }

        $taskId = Input::get('task_id');
        $projectId = Input::get('project_id');

        if (!$taskId || !$projectId) {
            throw new ApplicationException('Missing task or project identifier.');
        }

        $project = $client->projects()->find($projectId);
        if (!$project) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.project_not_found'));
        }

        $task = Task::where('id', $taskId)->where('project_id', $projectId)->first();
        if (!$task) {
            throw new ApplicationException('Task not found.');
        }

        $task->startTimer(null);

        Flash::success(trans('avalanchestudio.avalanchecrm::lang.messages.timer_started', ['name' => $task->title]));

        return $this->refreshProjectDetail($client, $projectId);
    }

    /**
     * AJAX: Stop a timer on a task belonging to a client's project.
     */
    public function onStopTaskTimer()
    {
        $user = Auth::getUser();
        if (!$user) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.must_be_logged_in'));
        }

        $client = Client::where('user_id', $user->id)->first();
        if (!$client) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.no_client_profile'));
        }

        $taskId = Input::get('task_id');
        $projectId = Input::get('project_id');

        if (!$taskId || !$projectId) {
            throw new ApplicationException('Missing task or project identifier.');
        }

        $project = $client->projects()->find($projectId);
        if (!$project) {
            throw new ApplicationException('Project not found or access denied.');
        }

        $task = Task::where('id', $taskId)->where('project_id', $projectId)->first();
        if (!$task) {
            throw new ApplicationException('Task not found.');
        }

        $task->stopTimer();

        Flash::success(trans('avalanchestudio.avalanchecrm::lang.messages.timer_stopped', ['hours' => $task->formatted_hours]));

        return $this->refreshProjectDetail($client, $projectId);
    }
}
