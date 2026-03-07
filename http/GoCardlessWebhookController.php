<?php

namespace AvalancheStudio\AvalancheCRM\Http;

use Log;
use AvalancheStudio\AvalancheCRM\Models\Settings;
use AvalancheStudio\AvalancheCRM\Models\Subscription;
use AvalancheStudio\AvalancheCRM\Models\Invoice;
use AvalancheStudio\AvalancheCRM\Models\Client;
use AvalancheStudio\AvalancheCRM\Http\InvoicePaymentController;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Handles incoming GoCardless webhook events for subscription lifecycle management.
 *
 * GoCardless sends events as a JSON body with an array of events
 * and uses HMAC-SHA256 signatures for verification.
 */
class GoCardlessWebhookController extends Controller
{
    /**
     * Handle the incoming GoCardless webhook.
     */
    public function handle(Request $request)
    {
        $settings = Settings::instance();

        if (!$settings->gocardless_enabled) {
            return response('GoCardless not enabled', 400);
        }

        $payload = $request->getContent();
        $signature = $request->header('Webhook-Signature');
        $webhookSecret = $settings->gocardless_webhook_secret;

        // Verify the webhook signature if a secret is configured
        if (!empty($webhookSecret)) {
            $computedSignature = hash_hmac('sha256', $payload, $webhookSecret);

            if (!hash_equals($computedSignature, $signature ?? '')) {
                Log::warning('GoCardless webhook signature verification failed.');
                return response('Invalid signature', 498);
            }
        }

        $body = json_decode($payload, false);

        if (!$body || !isset($body->events) || !is_array($body->events)) {
            return response('Invalid payload', 400);
        }

        foreach ($body->events as $event) {
            $resourceType = $event->resource_type ?? null;
            $action = $event->action ?? null;
            $links = $event->links ?? null;

            Log::info("GoCardless webhook event: {$resourceType}.{$action}");

            switch ($resourceType) {
                case 'subscriptions':
                    $this->handleSubscriptionEvent($action, $links);
                    break;

                case 'payments':
                    $this->handlePaymentEvent($action, $links, $event);
                    break;

                case 'mandates':
                    $this->handleMandateEvent($action, $links);
                    break;

                case 'billing_requests':
                    $this->handleBillingRequestEvent($action, $links, $event);
                    break;
            }
        }

        return response('OK', 200);
    }

    /**
     * Handle GoCardless subscription events.
     */
    protected function handleSubscriptionEvent(string $action, $links): void
    {
        $gcSubscriptionId = $links->subscription ?? null;
        if (!$gcSubscriptionId) {
            return;
        }

        $subscription = Subscription::where('gocardless_subscription_id', $gcSubscriptionId)->first();
        if (!$subscription) {
            Log::info("GoCardless subscription not found locally: {$gcSubscriptionId}");
            return;
        }

        switch ($action) {
            case 'created':
            case 'customer_approval_granted':
                if ($subscription->status !== 'active') {
                    $subscription->status = 'active';
                    $subscription->save();
                    Log::info("GoCardless subscription activated: {$gcSubscriptionId}");
                }
                break;

            case 'payment_created':
                // A payment was created for this subscription â€” subscription is healthy
                break;

            case 'cancelled':
            case 'finished':
                $subscription->status = 'canceled';
                $subscription->save();
                Log::info("GoCardless subscription cancelled: {$gcSubscriptionId}");
                break;
        }
    }

    /**
     * Handle GoCardless billing request events (invoice one-off payments).
     */
    protected function handleBillingRequestEvent(string $action, $links, $event): void
    {
        $billingRequestId = $links->billing_request ?? null;
        if (!$billingRequestId) {
            return;
        }

        if ($action === 'fulfilled') {
            $invoice = Invoice::where('gocardless_payment_id', $billingRequestId)->first();
            if ($invoice && $invoice->status !== 'paid') {
                InvoicePaymentController::markInvoicePaid($invoice, 'gocardless', $billingRequestId);
                Log::info("Invoice {$invoice->invoice_number} paid via GoCardless webhook (billing request: {$billingRequestId})");
            }
        }
    }

    /**
     * Handle GoCardless payment events (linked to subscriptions).
     */
    protected function handlePaymentEvent(string $action, $links, $event): void
    {
        $gcSubscriptionId = $links->subscription ?? null;
        if (!$gcSubscriptionId) {
            return;
        }

        $subscription = Subscription::where('gocardless_subscription_id', $gcSubscriptionId)->first();
        if (!$subscription) {
            return;
        }

        switch ($action) {
            case 'confirmed':
            case 'paid_out':
                // Payment succeeded â€” ensure subscription is active
                if ($subscription->status === 'past_due') {
                    $subscription->status = 'active';
                    $subscription->save();
                    Log::info("GoCardless payment confirmed, subscription reactivated: {$gcSubscriptionId}");
                }
                break;

            case 'failed':
            case 'late_failure_settled':
            case 'charged_back':
                $subscription->status = 'past_due';
                $subscription->save();
                Log::info("GoCardless payment failed, subscription past_due: {$gcSubscriptionId}");
                break;
        }
    }

    /**
     * Handle GoCardless mandate events.
     */
    protected function handleMandateEvent(string $action, $links): void
    {
        $mandateId = $links->mandate ?? null;
        $customerId = $links->customer ?? null;

        if (!$mandateId) {
            return;
        }

        switch ($action) {
            case 'cancelled':
            case 'failed':
            case 'expired':
                // Mandate is no longer valid â€” cancel any active subscriptions using it
                $client = Client::where('gocardless_mandate_id', $mandateId)->first();
                if ($client) {
                    $client->gocardless_mandate_id = null;
                    $client->save();

                    // Mark related active direct debit subscriptions as canceled
                    // Use loop instead of bulk update so model events (notifications) fire
                    $subscriptions = Subscription::where('client_id', $client->id)
                        ->where('payment_method', 'direct_debit')
                        ->whereIn('status', ['active', 'past_due'])
                        ->get();

                    foreach ($subscriptions as $subscription) {
                        $subscription->status = 'canceled';
                        $subscription->save();
                    }

                    Log::info("GoCardless mandate {$action}: {$mandateId} â€” subscriptions cancelled");
                }
                break;
        }
    }
}
