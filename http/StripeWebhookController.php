<?php

namespace AvalancheStudio\AvalancheCRM\Http;

use Log;
use AvalancheStudio\AvalancheCRM\Models\Settings;
use AvalancheStudio\AvalancheCRM\Models\Subscription;
use AvalancheStudio\AvalancheCRM\Models\Invoice;
use AvalancheStudio\AvalancheCRM\Http\InvoicePaymentController;
use AvalancheStudio\AvalancheCRM\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Handles incoming Stripe webhook events for subscription lifecycle management.
 */
class StripeWebhookController extends Controller
{
    /**
     * Handle the incoming Stripe webhook.
     */
    public function handle(Request $request)
    {
        $settings = Settings::instance();

        if (!$settings->stripe_enabled) {
            return response('Stripe not enabled', 400);
        }

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = $settings->stripe_webhook_secret;

        // Verify the webhook signature if a secret is configured
        if (!empty($webhookSecret)) {
            try {
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } catch (\Stripe\Exception\SignatureVerificationException $e) {
                Log::warning('Stripe webhook signature verification failed: ' . $e->getMessage());
                return response('Invalid signature', 400);
            } catch (\UnexpectedValueException $e) {
                Log::warning('Stripe webhook invalid payload: ' . $e->getMessage());
                return response('Invalid payload', 400);
            }
        } else {
            $event = json_decode($payload, false);
            if (!$event || !isset($event->type)) {
                return response('Invalid payload', 400);
            }
        }

        $type = $event->type ?? null;
        $data = $event->data->object ?? null;

        if (!$type || !$data) {
            return response('OK', 200);
        }

        Log::info('Stripe webhook received: ' . $type);

        switch ($type) {
            case 'checkout.session.completed':
                $this->handleCheckoutSessionCompleted($data);
                break;

            case 'invoice.payment_succeeded':
                $this->handleInvoicePaymentSucceeded($data);
                break;

            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($data);
                break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($data);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($data);
                break;
        }

        return response('OK', 200);
    }

    /**
     * checkout.session.completed â€” activate the pending subscription OR mark invoice paid.
     */
    protected function handleCheckoutSessionCompleted($session): void
    {
        $sessionId = $session->id ?? null;
        if (!$sessionId) {
            return;
        }

        // Check if this is an invoice payment
        $metadata = $session->metadata ?? null;
        if ($metadata && ($metadata->type ?? '') === 'invoice_payment') {
            $invoice = Invoice::where('stripe_checkout_session_id', $sessionId)->first();
            if ($invoice && $invoice->status !== 'paid') {
                $paymentIntent = $session->payment_intent ?? $sessionId;
                InvoicePaymentController::markInvoicePaid($invoice, 'stripe', $paymentIntent);
                Log::info("Invoice {$invoice->invoice_number} paid via Stripe webhook (session: {$sessionId})");
            }
            return;
        }

        $subscription = Subscription::where('stripe_checkout_session_id', $sessionId)->first();
        if (!$subscription) {
            return;
        }

        // Get the Stripe subscription ID from the session
        $stripeSubId = $session->subscription ?? null;

        if ($subscription->status === 'pending') {
            $subscription->status = 'active';
        }

        if ($stripeSubId) {
            $subscription->stripe_subscription_id = $stripeSubId;
            $subscription->reference_id = $stripeSubId;
        }

        $subscription->next_billing_date = $this->calculateNextBillingDate($subscription->billing_cycle);
        $subscription->save();

        Log::info("Subscription #{$subscription->id} activated via Stripe checkout session {$sessionId}");
    }

    /**
     * invoice.payment_succeeded â€” renew subscription on successful recurring payment.
     */
    protected function handleInvoicePaymentSucceeded($invoice): void
    {
        $stripeSubId = $invoice->subscription ?? null;
        if (!$stripeSubId) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $stripeSubId)->first();
        if (!$subscription) {
            return;
        }

        // If it was past_due, reactivate it
        if ($subscription->status === 'past_due') {
            $subscription->status = 'active';
        }

        $subscription->next_billing_date = $this->calculateNextBillingDate($subscription->billing_cycle);
        $subscription->save();

        Log::info("Subscription #{$subscription->id} renewed after payment for Stripe sub {$stripeSubId}");
    }

    /**
     * invoice.payment_failed â€” mark subscription as past_due.
     */
    protected function handleInvoicePaymentFailed($invoice): void
    {
        $stripeSubId = $invoice->subscription ?? null;
        if (!$stripeSubId) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $stripeSubId)->first();
        if (!$subscription) {
            return;
        }

        $subscription->status = 'past_due';
        $subscription->save();

        Log::info("Subscription #{$subscription->id} marked past_due after payment failure for Stripe sub {$stripeSubId}");
    }

    /**
     * customer.subscription.updated â€” sync status changes from Stripe.
     */
    protected function handleSubscriptionUpdated($stripeSub): void
    {
        $stripeSubId = $stripeSub->id ?? null;
        if (!$stripeSubId) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $stripeSubId)->first();
        if (!$subscription) {
            return;
        }

        $stripeStatus = $stripeSub->status ?? null;

        $statusMap = [
            'active'   => 'active',
            'past_due' => 'past_due',
            'canceled' => 'canceled',
            'unpaid'   => 'past_due',
        ];

        if (isset($statusMap[$stripeStatus])) {
            $subscription->status = $statusMap[$stripeStatus];
            $subscription->save();
            Log::info("Subscription #{$subscription->id} status synced to {$subscription->status} from Stripe");
        }
    }

    /**
     * customer.subscription.deleted â€” mark subscription as canceled.
     */
    protected function handleSubscriptionDeleted($stripeSub): void
    {
        $stripeSubId = $stripeSub->id ?? null;
        if (!$stripeSubId) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $stripeSubId)->first();
        if (!$subscription) {
            return;
        }

        $subscription->status = 'canceled';
        $subscription->save();

        Log::info("Subscription #{$subscription->id} canceled via Stripe webhook for sub {$stripeSubId}");
    }

    /**
     * Calculate the next billing date based on cycle.
     */
    protected function calculateNextBillingDate(string $cycle): \Carbon\Carbon
    {
        $now = now();

        return match ($cycle) {
            'monthly'   => $now->addMonth(),
            'quarterly' => $now->addMonths(3),
            'annual'    => $now->addYear(),
            default     => $now->addMonth(),
        };
    }
}
