<?php

namespace AvalancheStudio\AvalancheCRM\Http;

use Log;
use AvalancheStudio\AvalancheCRM\Models\Invoice;
use AvalancheStudio\AvalancheCRM\Models\Client;
use AvalancheStudio\AvalancheCRM\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Handles one-off invoice payments via Stripe, PayPal, and GoCardless.
 */
class InvoicePaymentController extends Controller
{
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    //  Stripe â€” Card Payment via Checkout Session
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Create a Stripe Checkout Session and redirect the client to pay.
     */
    public function stripeCheckout(Request $request, $invoiceId)
    {
        $invoice = $this->resolveInvoice($invoiceId);
        $settings = Settings::instance();

        if (!$settings->stripe_enabled) {
            abort(400, 'Card payments are not enabled.');
        }

        \Stripe\Stripe::setApiKey($settings->stripe_secret_key);

        $currencyCode = strtolower($settings->currency_code ?: 'usd');
        $amountInCents = (int) round($invoice->amount * 100);

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'mode'                 => 'payment',
            'client_reference_id'  => (string) $invoice->id,
            'metadata'             => [
                'invoice_id'     => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'type'           => 'invoice_payment',
            ],
            'line_items' => [[
                'price_data' => [
                    'currency'     => $currencyCode,
                    'unit_amount'  => $amountInCents,
                    'product_data' => [
                        'name'        => 'Invoice ' . $invoice->invoice_number,
                        'description' => $invoice->project ? 'Project: ' . $invoice->project->name : null,
                    ],
                ],
                'quantity' => 1,
            ]],
            'success_url' => url('avalanchecrm/invoice/' . $invoice->id . '/payment/success') . '?method=stripe&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => url('avalanchecrm/invoice/' . $invoice->id . '/payment/cancel'),
        ]);

        // Store session ID for webhook matching
        $invoice->stripe_checkout_session_id = $session->id;
        $invoice->save();

        return redirect($session->url, 303);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    //  PayPal â€” Create Order and redirect
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Create a PayPal order and redirect the client to approve it.
     */
    public function paypalCheckout(Request $request, $invoiceId)
    {
        $invoice = $this->resolveInvoice($invoiceId);
        $settings = Settings::instance();

        if (!$settings->paypal_enabled) {
            abort(400, 'PayPal payments are not enabled.');
        }

        $baseUrl = ($settings->paypal_mode === 'live')
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $httpClient = new \GuzzleHttp\Client();

        // Get access token
        $tokenResponse = $httpClient->post($baseUrl . '/v1/oauth2/token', [
            'auth'        => [$settings->paypal_client_id, $settings->paypal_secret],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);

        $accessToken = json_decode($tokenResponse->getBody()->getContents())->access_token;

        $currencyCode = strtoupper($settings->currency_code ?: 'USD');

        // Create order
        $orderResponse = $httpClient->post($baseUrl . '/v2/checkout/orders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'intent'         => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) $invoice->id,
                    'description'  => 'Invoice ' . $invoice->invoice_number,
                    'amount'       => [
                        'currency_code' => $currencyCode,
                        'value'         => number_format($invoice->amount, 2, '.', ''),
                    ],
                    'invoice_id'   => $invoice->invoice_number,
                ]],
                'application_context' => [
                    'return_url' => url('avalanchecrm/invoice/' . $invoice->id . '/payment/paypal/capture'),
                    'cancel_url' => url('avalanchecrm/invoice/' . $invoice->id . '/payment/cancel'),
                    'user_action' => 'PAY_NOW',
                ],
            ],
        ]);

        $order = json_decode($orderResponse->getBody()->getContents());

        $invoice->paypal_order_id = $order->id;
        $invoice->save();

        // Find the approval link
        $approveUrl = null;
        foreach ($order->links as $link) {
            if ($link->rel === 'approve') {
                $approveUrl = $link->href;
                break;
            }
        }

        if (!$approveUrl) {
            abort(500, 'Could not generate PayPal payment link.');
        }

        return redirect($approveUrl, 303);
    }

    /**
     * PayPal redirects here after approval â€” capture the payment.
     */
    public function paypalCapture(Request $request, $invoiceId)
    {
        $invoice = $this->resolveInvoice($invoiceId);
        $settings = Settings::instance();

        $orderId = $invoice->paypal_order_id;
        if (!$orderId) {
            return redirect(url('avalanchecrm/invoice/' . $invoiceId . '/payment/cancel'));
        }

        $baseUrl = ($settings->paypal_mode === 'live')
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $httpClient = new \GuzzleHttp\Client();

        // Get access token
        $tokenResponse = $httpClient->post($baseUrl . '/v1/oauth2/token', [
            'auth'        => [$settings->paypal_client_id, $settings->paypal_secret],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);
        $accessToken = json_decode($tokenResponse->getBody()->getContents())->access_token;

        // Capture the order
        try {
            $captureResponse = $httpClient->post($baseUrl . '/v2/checkout/orders/' . $orderId . '/capture', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json',
                ],
            ]);

            $capture = json_decode($captureResponse->getBody()->getContents());

            if (($capture->status ?? '') === 'COMPLETED') {
                $captureId = $capture->purchase_units[0]->payments->captures[0]->id ?? $orderId;
                $this->markInvoicePaid($invoice, 'paypal', $captureId);
            }
        } catch (\Exception $e) {
            Log::error('PayPal capture failed for invoice ' . $invoice->id . ': ' . $e->getMessage());
            return redirect(url('avalanchecrm/invoice/' . $invoiceId . '/payment/cancel'));
        }

        return redirect(url('avalanchecrm/invoice/' . $invoiceId . '/payment/success') . '?method=paypal');
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    //  GoCardless â€” Instant Bank Payment
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Create a GoCardless Billing Request for an instant bank payment
     * and redirect the client to the hosted payment page.
     */
    public function gocardlessCheckout(Request $request, $invoiceId)
    {
        $invoice = $this->resolveInvoice($invoiceId);
        $settings = Settings::instance();

        if (!$settings->gocardless_enabled) {
            abort(400, 'Bank payments are not enabled.');
        }

        $environment = ($settings->gocardless_environment === 'live')
            ? \GoCardlessPro\Environment::LIVE
            : \GoCardlessPro\Environment::SANDBOX;

        $client = new \GoCardlessPro\Client([
            'access_token' => $settings->gocardless_access_token,
            'environment'  => $environment,
        ]);

        $currencyCode = strtoupper($settings->currency_code ?: 'GBP');
        $amountInPence = (int) round($invoice->amount * 100);

        // Create a billing request for an instant payment
        $billingRequest = $client->billingRequests()->create([
            'params' => [
                'payment_request' => [
                    'description' => 'Invoice ' . $invoice->invoice_number,
                    'amount'      => $amountInPence,
                    'currency'    => $currencyCode,
                    'metadata'    => [
                        'invoice_id'     => (string) $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                    ],
                ],
            ],
        ]);

        // Create a billing request flow (hosted payment page)
        $flow = $client->billingRequestFlows()->create([
            'params' => [
                'redirect_uri'       => url('avalanchecrm/invoice/' . $invoice->id . '/payment/success') . '?method=gocardless',
                'exit_uri'           => url('avalanchecrm/invoice/' . $invoice->id . '/payment/cancel'),
                'links'              => [
                    'billing_request' => $billingRequest->id,
                ],
                'show_redirect_buttons' => true,
            ],
        ]);

        $invoice->gocardless_payment_id = $billingRequest->id;
        $invoice->save();

        return redirect($flow->authorisation_url, 303);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    //  Success / Cancel Pages
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Generic success page after payment redirect.
     */
    public function success(Request $request, $invoiceId)
    {
        $invoice = $this->resolveInvoice($invoiceId);
        $method = $request->get('method', 'unknown');

        // For Stripe, verify the session was paid
        if ($method === 'stripe') {
            $sessionId = $request->get('session_id');
            if ($sessionId && $invoice->stripe_checkout_session_id === $sessionId) {
                $settings = Settings::instance();
                \Stripe\Stripe::setApiKey($settings->stripe_secret_key);

                try {
                    $session = \Stripe\Checkout\Session::retrieve($sessionId);
                    if ($session->payment_status === 'paid') {
                        $this->markInvoicePaid($invoice, 'stripe', $session->payment_intent);
                    }
                } catch (\Exception $e) {
                    Log::error('Stripe session verification failed: ' . $e->getMessage());
                }
            }
        }

        // GoCardless is confirmed asynchronously via webhooks,
        // but we optimistically show success after redirect.

        // Reload fresh state
        $invoice->refresh();

        $settings = Settings::instance();
        $currencySymbol = $settings->currency_symbol ?: '$';

        return response()->view('avalanchestudio.avalanchecrm::payment.success', [
            'invoice'        => $invoice,
            'method'         => $method,
            'currencySymbol' => $currencySymbol,
        ]);
    }

    /**
     * Cancel / back page.
     */
    public function cancel(Request $request, $invoiceId)
    {
        $invoice = $this->resolveInvoice($invoiceId);

        return response()->view('avalanchestudio.avalanchecrm::payment.cancel', [
            'invoice' => $invoice,
        ]);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    //  Helpers
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Resolve the invoice and verify the authenticated client owns it.
     */
    protected function resolveInvoice($invoiceId): Invoice
    {
        $user = \Auth::getUser();
        if (!$user) {
            abort(403, 'You must be logged in.');
        }

        $client = Client::where('user_id', $user->id)->first();
        if (!$client) {
            abort(403, 'No client profile found.');
        }

        $invoice = Invoice::where('id', $invoiceId)
            ->where('client_id', $client->id)
            ->where('internal_status', 'sent')
            ->firstOrFail();

        if ($invoice->status === 'paid') {
            abort(400, 'This invoice has already been paid.');
        }

        return $invoice;
    }

    /**
     * Mark an invoice as paid.
     */
    public static function markInvoicePaid(Invoice $invoice, string $method, ?string $reference = null): void
    {
        if ($invoice->status === 'paid') {
            return;
        }

        $invoice->status = 'paid';
        $invoice->payment_method = $method;
        $invoice->payment_reference = $reference;
        $invoice->paid_at = now();
        $invoice->save();

        Log::info("Invoice {$invoice->invoice_number} marked as paid via {$method}" . ($reference ? " (ref: {$reference})" : ''));
    }
}
