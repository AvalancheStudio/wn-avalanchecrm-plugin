<?php

namespace AvalancheStudio\AvalancheCRM\Controllers;

use Backend;
use Backend\Classes\Controller;
use Backend\Facades\BackendMenu;
use Flash;
use Redirect;
use AvalancheStudio\AvalancheCRM\Models\Project;
use AvalancheStudio\AvalancheCRM\Models\Invoice;
use AvalancheStudio\AvalancheCRM\Models\InvoiceItem;

/**
 * Projects Backend Controller
 */
class Projects extends Controller
{
    use \AvalancheStudio\AvalancheCRM\Traits\HasTaskModal;

    /**
     * @var array Behaviors that are implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\RelationController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';

    /**
     * @var array Permissions required to view this page.
     */
    protected $requiredPermissions = [
        'avalanchestudio.avalanchecrm.projects.manage_all',
    ];

    public function listExtendQuery($query)
    {
        if ($status = request()->get('status')) {
            $query->where('status', $status);
        }
    }

    /**
     * Show the Generate Invoice popup with uninvoiced billable tasks.
     */
    public function onLoadGenerateInvoiceForm()
    {
        $projectId = post('project_id') ?: $this->params[0] ?? null;
        $project = Project::with('tasks')->findOrFail($projectId);

        $billableTasks = $project->getUninvoicedBillableTasks();

        $this->vars['project'] = $project;
        $this->vars['billableTasks'] = $billableTasks;
        $this->vars['defaultRate'] = $project->hourly_rate ?? 0;

        return $this->makePartial('generate_invoice_form');
    }

    /**
     * Generate an invoice from selected billable tasks.
     */
    public function onGenerateInvoice()
    {
        $projectId = post('project_id');
        $taskIds = post('task_ids', []);
        $dueDate = post('due_date');
        $notes = post('notes');

        if (empty($taskIds)) {
            Flash::error('Please select at least one task to invoice.');
            return;
        }

        $project = Project::with(['tasks', 'clients'])->findOrFail($projectId);

        // Determine client â€” use first assigned client
        $client = $project->clients->first();

        // Generate invoice number from settings
        $invoiceNumber = Invoice::generateInvoiceNumber();

        $invoice = new Invoice();
        $invoice->invoice_number = $invoiceNumber;
        $invoice->client_id = $client ? $client->id : null;
        $invoice->project_id = $project->id;
        $invoice->internal_status = 'draft';
        $invoice->status = 'outstanding';
        $invoice->issue_date = now();
        $invoice->due_date = $dueDate ?: null;
        $invoice->notes = $notes ?: null;
        $invoice->amount = 0;
        $invoice->save();

        $totalAmount = 0;

        foreach ($project->tasks()->whereIn('id', $taskIds)->get() as $task) {
            $rate = $task->hourly_rate ?: ($project->hourly_rate ?: 0);
            $hours = $task->hours ?? 0;
            $lineTotal = round($rate * $hours, 2);

            $item = new InvoiceItem();
            $item->invoice_id = $invoice->id;
            $item->task_id = $task->id;
            $item->description = $task->title;
            $item->quantity = $hours;
            $item->unit_price = $rate;
            $item->amount = $lineTotal;
            $item->save();

            $task->is_invoiced = true;
            $task->save();

            $totalAmount += $lineTotal;
        }

        // If fixed-price project with no hourly tasks, use fixed price
        if ($totalAmount == 0 && $project->billing_type === 'fixed' && $project->fixed_price > 0) {
            $item = new InvoiceItem();
            $item->invoice_id = $invoice->id;
            $item->description = 'Fixed price â€” ' . $project->name;
            $item->quantity = 1;
            $item->unit_price = $project->fixed_price;
            $item->amount = $project->fixed_price;
            $item->save();

            $totalAmount = $project->fixed_price;
        }

        $invoice->amount = $totalAmount;
        $invoice->save();

        Flash::success('Invoice ' . $invoiceNumber . ' created for ' . number_format($totalAmount, 2) . '.');

        return Redirect::to(Backend::url('avalanchestudio/avalanchecrm/invoices/update/' . $invoice->id));
    }
}
