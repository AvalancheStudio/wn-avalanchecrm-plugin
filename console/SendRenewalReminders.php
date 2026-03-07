<?php

namespace AvalancheStudio\AvalancheCRM\Console;

use Illuminate\Console\Command;
use Carbon\Carbon;
use AvalancheStudio\AvalancheCRM\Models\Subscription;

/**
 * Artisan command to send subscription renewal reminders.
 *
 * Finds active subscriptions whose next billing date falls within the
 * configured reminder window (default 7 days) and sends a
 * "Subscription Renewal Reminder" notification to each client.
 *
 * Usage:  php artisan avalanchecrm:send-renewal-reminders
 *         php artisan avalanchecrm:send-renewal-reminders --days=14
 */
class SendRenewalReminders extends Command
{
    /**
     * @var string The console command signature.
     */
    protected $signature = 'avalanchecrm:send-renewal-reminders {--days=7 : Number of days ahead to look for upcoming renewals}';

    /**
     * @var string The console command description.
     */
    protected $description = 'Send subscription renewal reminder emails to clients.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $now = Carbon::now();
        $cutoff = $now->copy()->addDays($days);

        $subscriptions = Subscription::where('status', 'active')
            ->whereBetween('next_billing_date', [$now, $cutoff])
            ->with('client')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info("No active subscriptions renewing within the next {$days} day(s).");
            return 0;
        }

        $sent = 0;

        foreach ($subscriptions as $subscription) {
            $client = $subscription->client;

            if (!$client) {
                $this->warn("Subscription #{$subscription->id} has no client â€“ skipped.");
                continue;
            }

            try {
                $client->sendNotification('subscription', 'Subscription Renewal Reminder');
                $sent++;
                $this->line("Reminder sent for Subscription #{$subscription->id} to {$client->email}");
            } catch (\Throwable $e) {
                $this->error("Failed for Subscription #{$subscription->id}: {$e->getMessage()}");
            }
        }

        $this->info("Done. {$sent} renewal reminder(s) sent.");

        return 0;
    }
}
