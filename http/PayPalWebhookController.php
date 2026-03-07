<?php

namespace AvalancheStudio\AvalancheCRM\Http;

use Log;
use AvalancheStudio\AvalancheCRM\Models\Settings;
use AvalancheStudio\AvalancheCRM\Models\Subscription;
use AvalancheStudio\AvalancheCRM\Models\Invoice;
use AvalancheStudio\AvalancheCRM\Http\InvoicePaymentController;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Handles incoming PayPal webhook events for subscription lifecycle management.
 *
 * PayPal sends events as JSON with an event_type and resource object.
 */
class PayPalWebhookController extends Controller
{
    /**
     * Handle the incoming PayPal webhook.
     */
    public function handle(Request $request)
    {
        $settings = Settings::instance();

        if (!$settings->paypal_enabled) {
            return response('PayPal not enabled', 400);
        }

        $payload = $request->getContent();
        $body = json_decode($payload, false);

        if (!$body || !isset($body->event_type)) {
            return response('Invalid payload', 400);
        }

        // Verify webhook signature if webhook ID is configured
        $webhookId = $settings->paypal_webhook_id;
        if (!empty($webhookId)) {
            $isValid = $this->verifyWebhookSignature($request, $payload, $webhookId, $settings);
            if (!$isValid) {
                Log::warning('PayPal webhook signature verification failed.');
                return response('Invalid signature', 400);
            }
        }

        $eventType = $body->event_type;
        $resource = $body->resource ?? null;

        if (!$resource) {
            return response('OK', 200);
        }

        Log::info('PayPal webhook received: ' . $eventType);

        switch ($eventType) {
            // Subscription lifecycle events
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
                $this->handleSubscriptionActivated($resource);
                break;

            case 'BILLING.SUBSCRIPTION.CANCELLED':
                $this->handleSubscriptionCancelled($resource);
                break;

            case 'BILLING.SUBSCRIPTION.SUSPENDED':
                $this->handleSubscriptionSuspended($resource);
                break;

            case 'BILLING.SUBSCRIPTION.EXPIRED':
                $this->handleSubscriptionExpired($resource);
                break;

            // Payment events
            case 'PAYMENT.SALE.COMPLETED':
                $this->handlePaymentCompleted($resource);
                break;

            case 'PAYMENT.SALE.DENIED':
            case 'PAYMENT.SALE.REFUNDED':
                $this->handlePaymentFailed($resource);
                break;

            // Invoice one-off capture events
            case 'PAYMENT.CAPTURE.COMPLETED':
                $this->handleCaptureCompleted($resource);
                break;
        }

        return response('OK', 200);
    }

    /**
     * Verify the PayPal webhook signature using the PayPal API.
     */
    protected function verifyWebhookSignature(Request $request, string $payload, string $webhookId, Settings $settings): bool
    {
        try {
            $baseUrl = ($settings->paypal_mode === 'live')
                ? 'https://api-m.paypal.com'
                : 'https://api-m.sandbox.paypal.com';

            // Get access token
            $httpClient = new \GuzzleHttp\Client();
            $tokenResponse = $httpClient->post($baseUrl . '/v1/oauth2/token', [
                'auth'        => [$settings->paypal_client_id, $settings->paypal_secret],
                'form_params' => ['grant_type' => 'client_credentials'],
                'headers'     => ['Accept' => 'application/json'],
            ]);
            $tokenData = json_decode($tokenResponse->getBody()->getContents(), true);
            $accessToken = $tokenData['access_token'];

            // Verify signature
            $verifyPayload = [
                'auth_algo'         => $request->header('PAYPAL-AUTH-ALGO', ''),
                'cert_url'          => $request->header('PAYPAL-CERT-URL', ''),
                'transmission_id'   => $request->header('PAYPAL-TRANSMISSION-ID', ''),
                'transmission_sig'  => $request->header('PAYPAL-TRANSMISSION-SIG', ''),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME', ''),
                'webhook_id'        => $webhookId,
                'webhook_event'     => json_decode($payload, true),
            ];

            $verifyResponse = $httpClient->post($baseUrl . '/v1/notifications/verify-webhook-signature', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json',
                ],
                'json' => $verifyPayload,
            ]);

            $verifyData = json_decode($verifyResponse->getBody()->getContents(), true);

            return ($verifyData['verification_status'] ?? '') === 'SUCCESS';
        } catch (\Exception $e) {
            Log::error('PayPal webhook verification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * BILLING.SUBSCRIPTION.ACTIVATED â€” activate the subscription.
     */
    protected function handleSubscriptionActivated($resource): void
    {
        $ppSubscriptionId = $resource->id ?? null;
        if (!$ppSubscriptionId) {
            return;
        }

        $subscription = Subscription::where('paypal_subscription_id', $ppSubscriptionId)->first();
        if (!$subscription) {
            Log::info("PayPal subscription not found locally: {$ppSubscriptionId}");
            return;
        }

        if ($subscription->status !== 'active') {
            $subscription->status = 'active';
            $subscription->save();
            Log::info("PayPal subscription activated: {$ppSubscriptionId}");
        }
    }

    /**
     * BILLING.SUBSCRIPTION.CANCELLED â€” cancel the subscription.
     */
    protected function handleSubscriptionCancelled($resource): void
    {
        $ppSubscriptionId = $resource->id ?? null;
        if (!$ppSubscriptionId) {
            return;
        }

        $subscription = Subscription::where('paypal_subscription_id', $ppSubscriptionId)->first();
        if (!$subscription) {
            return;
        }

        $subscription->status = 'canceled';
        $subscription->save();
        Log::info("PayPal subscription cancelled: {$ppSubscriptionId}");
    }

    /**
     * BILLING.SUBSCRIPTION.SUSPENDED â€” mark as past_due.
     */
    protected function handleSubscriptionSuspended($resource): void
    {
        $ppSubscriptionId = $resource->id ?? null;
        if (!$ppSubscriptionId) {
            return;
        }

        $subscription = Subscription::where('paypal_subscription_id', $ppSubscriptionId)->first();
        if (!$subscription) {
            return;
        }

        $subscription->status = 'past_due';
        $subscription->save();
        Log::info("PayPal subscription suspended (past_due): {$ppSubscriptionId}");
    }

    /**
     * BILLING.SUBSCRIPTION.EXPIRED â€” mark as canceled.
     */
    protected function handleSubscriptionExpired($resource): void
    {
        $ppSubscriptionId = $resource->id ?? null;
        if (!$ppSubscriptionId) {
            return;
        }

        $subscription = Subscription::where('paypal_subscription_id', $ppSubscriptionId)->first();
        if (!$subscription) {
            return;
        }

        $subscription->status = 'canceled';
        $subscription->save();
        Log::info("PayPal subscription expired: {$ppSubscriptionId}");
    }

    /**
     * PAYMENT.SALE.COMPLETED â€” payment received for a subscription.
     */
    protected function handlePaymentCompleted($resource): void
    {
        $ppSubscriptionId = $resource->billing_agreement_id ?? null;
        if (!$ppSubscriptionId) {
            return;
        }

        $subscription = Subscription::where('paypal_subscription_id', $ppSubscriptionId)->first();
        if (!$subscription) {
            return;
        }

        // If subscription was past_due, reactivate it
        if ($subscription->status === 'past_due') {
            $subscription->status = 'active';
            $subscription->save();
            Log::info("PayPal payment completed, subscription reactivated: {$ppSubscriptionId}");
        }
    }

    /**
     * PAYMENT.SALE.DENIED / REFUNDED â€” payment failed.
     */
    protected function handlePaymentFailed($resource): void
    {
        $ppSubscriptionId = $resource->billing_agreement_id ?? null;
        if (!$ppSubscriptionId) {
            return;
        }

        $subscription = Subscription::where('paypal_subscription_id', $ppSubscriptionId)->first();
        if (!$subscription) {
            return;
        }

        if ($subscription->status === 'active') {
            $subscription->status = 'past_due';
            $subscription->save();
            Log::info("PayPal payment failed, subscription past_due: {$ppSubscriptionId}");
        }
    }

    /**
     * PAYMENT.CAPTURE.COMPLETED â€” handle invoice one-off payment captures.
     */
    protected function handleCaptureCompleted($resource): void
    {
        // The capture's custom_id or invoice_id field links back to our invoice
        $captureId = $resource->id ?? null;
        $customId = $resource->custom_id ?? null;
        $invoiceId = $resource->invoice_id ?? null;

        // Try to find matching invoice by paypal_order_id stored during checkout
        // PayPal sends the order ID in supplementary_data or we check purchase_units
        $orderId = null;
        if (isset($resource->supplementary_data->related_ids->order_id)) {
            $orderId = $resource->supplementary_data->related_ids->order_id;
        }

        $invoice = null;

        if ($orderId) {
            $invoice = Invoice::where('paypal_order_id', $orderId)->first();
        }

        if (!$invoice && $customId) {
            $invoice = Invoice::find($customId);
        }

        if ($invoice && $invoice->status !== 'paid') {
            InvoicePaymentController::markInvoicePaid($invoice, 'paypal', $captureId ?: $orderId);
            Log::info("Invoice {$invoice->invoice_number} paid via PayPal webhook (capture: {$captureId})");
        }
    }
}
