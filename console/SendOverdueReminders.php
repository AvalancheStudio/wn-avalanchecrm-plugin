<?php

namespace AvalancheStudio\AvalancheCRM\Console;

use Illuminate\Console\Command;
use Carbon\Carbon;
use AvalancheStudio\AvalancheCRM\Models\Invoice;

/**
 * Artisan command to send overdue invoice reminders.
 *
 * Finds invoices that have been sent but are past their due date (and not yet
 * paid or cancelled) then sends an "Invoice Overdue Reminder" notification to
 * each invoice's client.
 *
 * Usage:  php artisan avalanchecrm:send-overdue-reminders
 */
class SendOverdueReminders extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'avalanchecrm:send-overdue-reminders';

    /**
     * @var string The console command description.
     */
    protected $description = 'Send overdue invoice reminder emails to clients.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();

        $invoices = Invoice::where('internal_status', Invoice::INTERNAL_SENT)
            ->whereIn('status', [
                Invoice::STATUS_OUTSTANDING,
                Invoice::STATUS_DUE,
                Invoice::STATUS_OVERDUE,
            ])
            ->where('due_date', '<', $now)
            ->with('client')
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No overdue invoices found.');
            return 0;
        }

        $sent = 0;

        foreach ($invoices as $invoice) {
            $client = $invoice->client;

            if (!$client) {
                $this->warn("Invoice #{$invoice->invoice_number} has no client â€“ skipped.");
                continue;
            }

            try {
                $client->sendNotification('invoice', 'Invoice Overdue Reminder');
                $sent++;
                $this->line("Reminder sent for Invoice #{$invoice->invoice_number} to {$client->email}");
            } catch (\Throwable $e) {
                $this->error("Failed for Invoice #{$invoice->invoice_number}: {$e->getMessage()}");
            }
        }

        $this->info("Done. {$sent} overdue reminder(s) sent.");

        return 0;
    }
}
