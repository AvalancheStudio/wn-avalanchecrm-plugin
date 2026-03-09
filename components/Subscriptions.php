<?php

namespace AvalancheStudio\AvalancheCRM\Components;

use Winter\User\Facades\Auth;
use Winter\Storm\Support\Facades\Flash;
use Winter\Storm\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;
use Cms\Classes\ComponentBase;
use AvalancheStudio\AvalancheCRM\Models\Client;
use AvalancheStudio\AvalancheCRM\Models\Subscription;
use AvalancheStudio\AvalancheCRM\Models\SubscriptionPlan;
use AvalancheStudio\AvalancheCRM\Models\Settings;
use Winter\Storm\Exception\ApplicationException;

/**
 * Subscriptions Component
 *
 * Allows frontend users to manage their subscriptions and payment options.
 * Integrates with Stripe Checkout for card payments and GoCardless for direct debit.
 */
class Subscriptions extends ComponentBase
{
    /**
     * @var Client The authenticated client.
     */
    public $client;

    /**
     * @var \Winter\Storm\Database\Collection Active subscriptions.
     */
    public $subscriptions;

    /**
     * @var \Winter\Storm\Database\Collection Available plans.
     */
    public $plans;

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
            'name' => 'Subscriptions',
            'description' => 'Allows clients to view and manage their subscriptions and payment methods.',
        ];
    }

    /**
     * Defines the properties used by this component.
     */
    public function defineProperties(): array
    {
        return [
            'showPlans' => [
                'title' => 'Show Available Plans',
                'description' => 'Display available subscription plans to the user.',
                'type' => 'checkbox',
                'default' => true,
            ],
            'showPaymentMethods' => [
                'title' => 'Show Payment Methods',
                'description' => 'Allow users to manage their payment methods.',
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
        if (!$settings->enable_subscriptions) {
            return Redirect::to('/');
        }

        $this->addCss('/plugins/avalanchestudio/avalanchecrm/assets/css/subscriptions.css');
        $this->addJs('/plugins/avalanchestudio/avalanchecrm/assets/js/subscriptions.js');

        $this->page['themeStyles'] = \AvalancheStudio\AvalancheCRM\Classes\ThemeStyles::render();

        // Handle Stripe Checkout return
        if (Input::get('stripe_success') && Input::get('session_id')) {
            $this->handleStripeReturn(Input::get('session_id'));
        }

        if (Input::get('stripe_cancel')) {
            Flash::warning(e(trans('avalanchestudio.avalanchecrm::lang.messages.payment_cancelled')));
        }

        // Handle GoCardless Redirect Flow return
        if (Input::get('gc_success') && Input::get('redirect_flow_id')) {
            $this->handleGoCardlessReturn(Input::get('redirect_flow_id'));
        }

        if (Input::get('gc_cancel')) {
            Flash::warning(e(trans('avalanchestudio.avalanchecrm::lang.messages.setup_cancelled')));
        }

        // Handle PayPal Subscription return
        if (Input::get('paypal_success') && Input::get('subscription_id')) {
            $this->handlePayPalReturn(Input::get('subscription_id'));
        }

        if (Input::get('paypal_cancel')) {
            Flash::warning(e(trans('avalanchestudio.avalanchecrm::lang.messages.payment_cancelled')));
        }

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

        $this->subscriptions = $this->page['subscriptions'] = Subscription::where('client_id', $this->client->id)
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($this->property('showPlans')) {
            $this->plans = $this->page['plans'] = SubscriptionPlan::where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->get();
        }

        $this->page['showPlans'] = $this->property('showPlans');
        $this->page['showPaymentMethods'] = $this->property('showPaymentMethods');
        $this->page['stripeEnabled'] = $this->settings->stripe_enabled ?? false;
        $this->page['paypalEnabled'] = $this->settings->paypal_enabled ?? false;
        $this->page['gocardlessEnabled'] = $this->settings->gocardless_enabled ?? false;
        $this->page['stripePublicKey'] = $this->settings->stripe_public_key ?? '';
        $this->page['paypalClientId'] = $this->settings->paypal_client_id ?? '';
        $this->page['paypalMode'] = $this->settings->paypal_mode ?? 'sandbox';
        $this->page['currencySymbol'] = $this->settings->currency_symbol ?? '$';
        $this->page['currencyCode'] = $this->settings->currency_code ?? 'USD';
    }

    /**
     * Get or create a Stripe customer for the given client.
     */
    protected function getOrCreateStripeCustomer(Client $client, \Stripe\StripeClient $stripe): string
    {
        if ($client->stripe_customer_id) {
            // Verify the customer still exists on Stripe
            try {
                $stripe->customers->retrieve($client->stripe_customer_id);
                return $client->stripe_customer_id;
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Customer was deleted on Stripe, create a new one
            }
        }

        $user = $client->user;

        $customer = $stripe->customers->create([
            'email' => $user->email ?? $client->email,
            'name' => $client->name,
            'metadata' => [
                'client_id' => $client->id,
                'user_id' => $user->id ?? null,
            ],
        ]);

        $client->stripe_customer_id = $customer->id;
        $client->save();

        return $customer->id;
    }

    /**
     * Initialise the Stripe client using CRM settings.
     */
    protected function getStripeClient(): \Stripe\StripeClient
    {
        $settings = Settings::instance();

        if (!$settings->stripe_enabled) {
            throw new ApplicationException('Card payments are not enabled.');
        }

        $secretKey = $settings->stripe_secret_key;

        if (empty($secretKey)) {
            throw new ApplicationException('Stripe has not been configured. Please contact support.');
        }

        return new \Stripe\StripeClient($secretKey);
    }

    /**
     * Build the current page URL (without query params) for Stripe redirects.
     */
    protected function getCurrentPageUrl(): string
    {
        $url = Request::url();
        // Strip any existing query parameters
        return strtok($url, '?');
    }

    // =========================================================================
    //  AJAX: Subscribe to a plan
    // =========================================================================

    /**
     * AJAX: Subscribe to a plan.
     *
     * For card payments â†’ creates a Stripe Checkout Session and returns the redirect URL.
     * For other methods  â†’ creates the subscription directly.
     */
    public function onSubscribe()
    {
        $user = Auth::getUser();
        if (!$user) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.must_be_logged_in'));
        }

        $client = Client::where('user_id', $user->id)->first();
        if (!$client) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.no_client_profile'));
        }

        $planId = post('plan_id');
        $paymentMethod = post('payment_method');

        $plan = SubscriptionPlan::where('is_active', true)->findOrFail($planId);

        // Validate payment method is enabled
        $settings = Settings::instance();
        $this->validatePaymentMethod($paymentMethod, $settings);

        // Check for existing active subscription to the same plan
        $existing = Subscription::where('client_id', $client->id)
            ->where('plan_id', $plan->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.already_subscribed'));
        }

        // â”€â”€ Card (Stripe Checkout) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        if ($paymentMethod === 'card') {
            return $this->createStripeCheckoutSession($client, $plan, 'subscribe');
        }

        // â”€â”€ Direct Debit (GoCardless Redirect Flow) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        if ($paymentMethod === 'direct_debit') {
            return $this->createGoCardlessRedirectFlow($client, $plan, 'subscribe');
        }

        // â”€â”€ PayPal Subscription â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        if ($paymentMethod === 'paypal') {
            return $this->createPayPalSubscription($client, $plan, 'subscribe');
        }

        // â”€â”€ Other payment methods â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $subscription = new Subscription();
        $subscription->client_id = $client->id;
        $subscription->plan_id = $plan->id;
        $subscription->plan_name = $plan->name;
        $subscription->payment_method = $paymentMethod;
        $subscription->status = 'active';
        $subscription->amount = $plan->price;
        $subscription->billing_cycle = $plan->billing_cycle;
        $subscription->next_billing_date = $this->calculateNextBillingDate($plan->billing_cycle);
        $subscription->save();

        Flash::success(trans('avalanchestudio.avalanchecrm::lang.messages.payment_successful', ['name' => $plan->name]));

        $this->prepareVars();

        return [
            '#subscriptions-active' => $this->renderPartial('@active'),
            '#subscriptions-plans' => $this->renderPartial('@plans'),
        ];
    }

    /**
     * Create a Stripe Checkout Session for a new subscription or payment method update.
     */
    protected function createStripeCheckoutSession(Client $client, SubscriptionPlan $plan, string $flow = 'subscribe'): array
    {
        $stripe = $this->getStripeClient();
        $customerId = $this->getOrCreateStripeCustomer($client, $stripe);
        $settings = Settings::instance();
        $currentUrl = $this->getCurrentPageUrl();

        // If the plan has a Stripe Price ID, use recurring subscription mode
        if (!empty($plan->stripe_price_id)) {
            $sessionParams = [
                'customer' => $customerId,
                'mode' => 'subscription',
                'line_items' => [
                    [
                        'price' => $plan->stripe_price_id,
                        'quantity' => 1,
                    ]
                ],
                'success_url' => $currentUrl . '?stripe_success=1&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $currentUrl . '?stripe_cancel=1',
                'metadata' => [
                    'client_id' => $client->id,
                    'plan_id' => $plan->id,
                    'flow' => $flow,
                ],
            ];
        } else {
            // No Stripe Price ID configured â€” create a one-off payment session with inline price
            $currencyCode = strtolower($settings->currency_code ?: 'usd');
            $sessionParams = [
                'customer' => $customerId,
                'mode' => 'payment',
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => $currencyCode,
                            'unit_amount' => (int) ($plan->price * 100),
                            'product_data' => [
                                'name' => $plan->name,
                                'description' => $plan->description ?: 'Subscription: ' . $plan->name,
                            ],
                        ],
                        'quantity' => 1,
                    ]
                ],
                'success_url' => $currentUrl . '?stripe_success=1&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $currentUrl . '?stripe_cancel=1',
                'metadata' => [
                    'client_id' => $client->id,
                    'plan_id' => $plan->id,
                    'flow' => $flow,
                ],
            ];
        }

        $session = $stripe->checkout->sessions->create($sessionParams);

        // Store a pending subscription so we can activate it on return
        $subscription = new Subscription();
        $subscription->client_id = $client->id;
        $subscription->plan_id = $plan->id;
        $subscription->plan_name = $plan->name;
        $subscription->payment_method = 'card';
        $subscription->status = 'pending';
        $subscription->amount = $plan->price;
        $subscription->billing_cycle = $plan->billing_cycle;
        $subscription->stripe_checkout_session_id = $session->id;
        $subscription->save();

        // Return the URL so JS can redirect
        return [
            'stripeRedirectUrl' => $session->url,
        ];
    }

    /**
     * Handle the return from Stripe Checkout (success URL).
     */
    protected function handleStripeReturn(string $sessionId): void
    {
        try {
            $stripe = $this->getStripeClient();
            $session = $stripe->checkout->sessions->retrieve($sessionId, [
                'expand' => ['subscription'],
            ]);

            // Find the pending subscription by checkout session ID
            $subscription = Subscription::where('stripe_checkout_session_id', $sessionId)->first();

            if (!$subscription) {
                Flash::error('Could not find the subscription for this session.');
                return;
            }

            // Verify it belongs to the current user
            $user = Auth::getUser();
            if (!$user) {
                return;
            }

            $client = Client::where('user_id', $user->id)->first();
            if (!$client || $subscription->client_id !== $client->id) {
                Flash::error('Subscription does not belong to your account.');
                return;
            }

            if ($session->payment_status === 'paid' || $session->status === 'complete') {
                $subscription->status = 'active';
                $subscription->next_billing_date = $this->calculateNextBillingDate($subscription->billing_cycle);

                // Store the Stripe subscription ID if available
                if ($session->subscription) {
                    $stripeSubId = is_string($session->subscription)
                        ? $session->subscription
                        : $session->subscription->id;
                    $subscription->stripe_subscription_id = $stripeSubId;
                    $subscription->reference_id = $stripeSubId;
                }

                $subscription->save();

                Flash::success(trans('avalanchestudio.avalanchecrm::lang.messages.payment_successful', ['name' => $subscription->plan_name]));
            } else {
                Flash::warning(trans('avalanchestudio.avalanchecrm::lang.messages.payment_processing'));
            }
        } catch (\Exception $e) {
            Log::error('Stripe return handling failed: ' . $e->getMessage());
            Flash::error('There was an issue confirming your payment. Please contact support if the problem persists.');
        }
    }

    // =========================================================================
    //  AJAX: Resume payment for a pending subscription
    // =========================================================================

    /**
     * AJAX: Resume payment for a pending subscription.
     *
     * Creates a fresh checkout / redirect session for the payment provider
     * and returns the redirect URL so the user can complete payment.
     */
    public function onResumePayment()
    {
        $user = Auth::getUser();
        if (!$user) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.must_be_logged_in'));
        }

        $client = Client::where('user_id', $user->id)->first();
        if (!$client) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.no_client_profile'));
        }

        $subscriptionId = post('subscription_id');

        $subscription = Subscription::where('client_id', $client->id)
            ->where('status', 'pending')
            ->findOrFail($subscriptionId);

        $plan = $subscription->plan;
        if (!$plan) {
            $plan = SubscriptionPlan::find($subscription->plan_id);
        }

        if (!$plan) {
            throw new ApplicationException('The plan for this subscription is no longer available.');
        }

        $settings = Settings::instance();

        // â”€â”€ Card (Stripe Checkout) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        if ($subscription->payment_method === 'card') {
            if (!$settings->stripe_enabled) {
                throw new ApplicationException('Card payments are not currently enabled. Please contact support.');
            }

            $stripe = $this->getStripeClient();
            $customerId = $this->getOrCreateStripeCustomer($client, $stripe);
            $currentUrl = $this->getCurrentPageUrl();

            // Build a fresh Stripe Checkout Session
            if (!empty($plan->stripe_price_id)) {
                $sessionParams = [
                    'customer' => $customerId,
                    'mode' => 'subscription',
                    'line_items' => [
                        [
                            'price' => $plan->stripe_price_id,
                            'quantity' => 1,
                        ]
                    ],
                    'success_url' => $currentUrl . '?stripe_success=1&session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $currentUrl . '?stripe_cancel=1',
                    'metadata' => [
                        'client_id' => $client->id,
                        'plan_id' => $plan->id,
                        'flow' => 'subscribe',
                    ],
                ];
            } else {
                $currencyCode = strtolower($settings->currency_code ?: 'usd');
                $sessionParams = [
                    'customer' => $customerId,
                    'mode' => 'payment',
                    'line_items' => [
                        [
                            'price_data' => [
                                'currency' => $currencyCode,
                                'unit_amount' => (int) ($plan->price * 100),
                                'product_data' => [
                                    'name' => $plan->name,
                                    'description' => $plan->description ?: 'Subscription: ' . $plan->name,
                                ],
                            ],
                            'quantity' => 1,
                        ]
                    ],
                    'success_url' => $currentUrl . '?stripe_success=1&session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $currentUrl . '?stripe_cancel=1',
                    'metadata' => [
                        'client_id' => $client->id,
                        'plan_id' => $plan->id,
                        'flow' => 'subscribe',
                    ],
                ];
            }

            $session = $stripe->checkout->sessions->create($sessionParams);

            // Update the pending subscription with the new session ID
            $subscription->stripe_checkout_session_id = $session->id;
            $subscription->save();

            return ['stripeRedirectUrl' => $session->url];
        }

        // â”€â”€ Direct Debit (GoCardless) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        if ($subscription->payment_method === 'direct_debit') {
            if (!$settings->gocardless_enabled) {
                throw new ApplicationException('Direct debit payments are not currently enabled. Please contact support.');
            }

            return $this->createGoCardlessRedirectFlow($client, $plan, 'subscribe');
        }

        // â”€â”€ PayPal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        if ($subscription->payment_method === 'paypal') {
            if (!$settings->paypal_enabled) {
                throw new ApplicationException('PayPal payments are not currently enabled. Please contact support.');
            }

            // Delete the old pending record â€” createPayPalSubscription will create a fresh one
            $subscription->delete();

            return $this->createPayPalSubscription($client, $plan, 'subscribe');
        }

        throw new ApplicationException('Unable to resume payment for this subscription.');
    }

    // =========================================================================
    //  AJAX: Cancel a subscription
    // =========================================================================

    public function onCancelSubscription()
    {
        $user = Auth::getUser();
        if (!$user) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.must_be_logged_in'));
        }

        $client = Client::where('user_id', $user->id)->first();
        if (!$client) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.no_client_profile'));
        }

        $subscriptionId = post('subscription_id');

        $subscription = Subscription::where('client_id', $client->id)
            ->findOrFail($subscriptionId);

        // If this is a Stripe subscription, cancel it on Stripe too
        if ($subscription->payment_method === 'card' && !empty($subscription->stripe_subscription_id)) {
            try {
                $stripe = $this->getStripeClient();
                $stripe->subscriptions->cancel($subscription->stripe_subscription_id);
            } catch (\Exception $e) {
                Log::error('Stripe subscription cancel failed: ' . $e->getMessage());
                // Continue to cancel locally even if Stripe fails
            }
        }

        // If this is a GoCardless subscription, cancel it on GoCardless too
        if ($subscription->payment_method === 'direct_debit' && !empty($subscription->gocardless_subscription_id)) {
            try {
                $gc = $this->getGoCardlessClient();
                $gc->subscriptions()->cancel($subscription->gocardless_subscription_id);
            } catch (\Exception $e) {
                Log::error('GoCardless subscription cancel failed: ' . $e->getMessage());
                // Continue to cancel locally even if GoCardless fails
            }
        }

        // If this is a PayPal subscription, cancel it on PayPal too
        if ($subscription->payment_method === 'paypal' && !empty($subscription->paypal_subscription_id)) {
            try {
                $this->cancelPayPalSubscription($subscription->paypal_subscription_id);
            } catch (\Exception $e) {
                Log::error('PayPal subscription cancel failed: ' . $e->getMessage());
                // Continue to cancel locally even if PayPal fails
            }
        }

        $subscription->status = 'canceled';
        $subscription->save();

        Flash::success(e(trans('avalanchestudio.avalanchecrm::lang.messages.subscription_cancelled')));

        $this->prepareVars();

        return [
            '#subscriptions-active' => $this->renderPartial('@active'),
            '#subscriptions-plans' => $this->renderPartial('@plans'),
        ];
    }

    // =========================================================================
    //  AJAX: Change payment method
    // =========================================================================

    /**
     * AJAX: Change the payment method on a subscription.
     *
     * When switching TO card, creates a Stripe Checkout Session in "setup" mode
     * so the user can enter new card details.
     */
    public function onUpdatePaymentMethod()
    {
        $user = Auth::getUser();
        if (!$user) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.must_be_logged_in'));
        }

        $client = Client::where('user_id', $user->id)->first();
        if (!$client) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.no_client_profile'));
        }

        $subscriptionId = post('subscription_id');
        $paymentMethod = post('payment_method');

        $settings = Settings::instance();
        $this->validatePaymentMethod($paymentMethod, $settings);

        $subscription = Subscription::where('client_id', $client->id)
            ->findOrFail($subscriptionId);

        // If changing TO card and there is an active Stripe subscription, use Stripe portal
        if ($paymentMethod === 'card' && !empty($subscription->stripe_subscription_id)) {
            try {
                $stripe = $this->getStripeClient();
                $customerId = $this->getOrCreateStripeCustomer($client, $stripe);
                $currentUrl = $this->getCurrentPageUrl();

                $portalSession = $stripe->billingPortal->sessions->create([
                    'customer' => $customerId,
                    'return_url' => $currentUrl,
                ]);

                return ['stripeRedirectUrl' => $portalSession->url];
            } catch (\Exception $e) {
                Log::error('Stripe portal session failed: ' . $e->getMessage());
                throw new ApplicationException('Could not open payment management. Please try again.');
            }
        }

        // If changing TO card but no existing Stripe subscription, create a Checkout session
        if ($paymentMethod === 'card') {
            $plan = $subscription->plan;
            if (!$plan) {
                throw new ApplicationException('No plan associated with this subscription.');
            }

            return $this->createStripeCheckoutSession($client, $plan, 'update_payment');
        }

        // If changing TO direct_debit, start GoCardless redirect flow
        if ($paymentMethod === 'direct_debit') {
            $plan = $subscription->plan;
            if (!$plan) {
                throw new ApplicationException('No plan associated with this subscription.');
            }

            return $this->createGoCardlessRedirectFlow($client, $plan, 'update_payment', $subscription->id);
        }

        // If changing TO paypal, start PayPal subscription
        if ($paymentMethod === 'paypal') {
            $plan = $subscription->plan;
            if (!$plan) {
                throw new ApplicationException('No plan associated with this subscription.');
            }

            return $this->createPayPalSubscription($client, $plan, 'update_payment', $subscription->id);
        }

        // For other methods, update directly
        $subscription->payment_method = $paymentMethod;
        $subscription->save();

        Flash::success(trans('avalanchestudio.avalanchecrm::lang.messages.payment_method_updated'));

        $this->prepareVars();

        return [
            '#subscriptions-active' => $this->renderPartial('@active'),
        ];
    }

    // =========================================================================
    //  AJAX: Change plan (upgrade / downgrade)
    // =========================================================================

    public function onChangePlan()
    {
        $user = Auth::getUser();
        if (!$user) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.must_be_logged_in'));
        }

        $client = Client::where('user_id', $user->id)->first();
        if (!$client) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.no_client_profile'));
        }

        $subscriptionId = post('subscription_id');
        $newPlanId = post('plan_id');

        $subscription = Subscription::where('client_id', $client->id)
            ->where('status', 'active')
            ->findOrFail($subscriptionId);

        $newPlan = SubscriptionPlan::where('is_active', true)->findOrFail($newPlanId);

        // If this is a Stripe subscription and both plans have Stripe price IDs, update on Stripe
        if (
            $subscription->payment_method === 'card'
            && !empty($subscription->stripe_subscription_id)
            && !empty($newPlan->stripe_price_id)
        ) {
            try {
                $stripe = $this->getStripeClient();
                $stripeSub = $stripe->subscriptions->retrieve($subscription->stripe_subscription_id);

                $stripe->subscriptions->update($subscription->stripe_subscription_id, [
                    'items' => [
                        [
                            'id' => $stripeSub->items->data[0]->id,
                            'price' => $newPlan->stripe_price_id,
                        ]
                    ],
                    'proration_behavior' => 'create_prorations',
                ]);
            } catch (\Exception $e) {
                Log::error('Stripe plan change failed: ' . $e->getMessage());
                throw new ApplicationException('Failed to change plan on Stripe. Please try again or contact support.');
            }
        }

        $subscription->plan_id = $newPlan->id;
        $subscription->plan_name = $newPlan->name;
        $subscription->amount = $newPlan->price;
        $subscription->billing_cycle = $newPlan->billing_cycle;
        $subscription->next_billing_date = $this->calculateNextBillingDate($newPlan->billing_cycle);
        $subscription->save();

        Flash::success(trans('avalanchestudio.avalanchecrm::lang.messages.plan_changed', ['name' => $newPlan->name]));

        $this->prepareVars();

        return [
            '#subscriptions-active' => $this->renderPartial('@active'),
            '#subscriptions-plans' => $this->renderPartial('@plans'),
        ];
    }

    // =========================================================================
    //  AJAX: Manage billing via Stripe Customer Portal
    // =========================================================================

    /**
     * AJAX: Open the Stripe Customer Portal for managing cards and invoices.
     */
    public function onOpenStripePortal()
    {
        $user = Auth::getUser();
        if (!$user) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.must_be_logged_in'));
        }

        $client = Client::where('user_id', $user->id)->first();
        if (!$client) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.no_client_profile'));
        }

        if (empty($client->stripe_customer_id)) {
            throw new ApplicationException('No Stripe account is associated with your profile.');
        }

        try {
            $stripe = $this->getStripeClient();
            $currentUrl = $this->getCurrentPageUrl();

            $portalSession = $stripe->billingPortal->sessions->create([
                'customer' => $client->stripe_customer_id,
                'return_url' => $currentUrl,
            ]);

            return ['stripeRedirectUrl' => $portalSession->url];
        } catch (\Exception $e) {
            Log::error('Stripe portal failed: ' . $e->getMessage());
            throw new ApplicationException('Could not open billing portal. Please try again.');
        }
    }

    // =========================================================================
    //  GoCardless: Redirect Flow & Subscription Management
    // =========================================================================

    /**
     * Initialise the GoCardless client using CRM settings.
     */
    protected function getGoCardlessClient(): \GoCardlessPro\Client
    {
        $settings = Settings::instance();

        if (!$settings->gocardless_enabled) {
            throw new ApplicationException('Direct debit payments are not enabled.');
        }

        $accessToken = $settings->gocardless_access_token;

        if (empty($accessToken)) {
            throw new ApplicationException('GoCardless has not been configured. Please contact support.');
        }

        $environment = ($settings->gocardless_environment === 'live')
            ? \GoCardlessPro\Environment::LIVE
            : \GoCardlessPro\Environment::SANDBOX;

        return new \GoCardlessPro\Client([
            'access_token' => $accessToken,
            'environment' => $environment,
        ]);
    }

    /**
     * Create a GoCardless Redirect Flow and return the redirect URL.
     *
     * @param Client $client
     * @param SubscriptionPlan $plan
     * @param string $flow  'subscribe' or 'update_payment'
     * @param int|null $existingSubscriptionId  If updating payment on an existing subscription
     * @return array
     */
    protected function createGoCardlessRedirectFlow(Client $client, SubscriptionPlan $plan, string $flow = 'subscribe', ?int $existingSubscriptionId = null): array
    {
        $gc = $this->getGoCardlessClient();
        $currentUrl = $this->getCurrentPageUrl();
        $user = $client->user;

        // Build a unique session token for this redirect flow
        $sessionToken = 'gc_' . $client->id . '_' . $plan->id . '_' . time();

        try {
            $redirectFlow = $gc->redirectFlows()->create([
                'params' => [
                    'description' => 'Subscription: ' . $plan->name,
                    'session_token' => $sessionToken,
                    'success_redirect_url' => $currentUrl . '?gc_success=1&redirect_flow_id=placeholder',
                    'scheme' => null, // GoCardless will auto-detect based on customer's country
                ],
            ]);

            // Create/update a pending subscription to track this flow
            if ($flow === 'update_payment' && $existingSubscriptionId) {
                // Store the redirect flow ID on the existing subscription temporarily
                $subscription = Subscription::find($existingSubscriptionId);
                if ($subscription) {
                    $subscription->gocardless_redirect_flow_id = $redirectFlow->id;
                    $subscription->save();
                }
            } else {
                $subscription = new Subscription();
                $subscription->client_id = $client->id;
                $subscription->plan_id = $plan->id;
                $subscription->plan_name = $plan->name;
                $subscription->payment_method = 'direct_debit';
                $subscription->status = 'pending';
                $subscription->amount = $plan->price;
                $subscription->billing_cycle = $plan->billing_cycle;
                $subscription->gocardless_redirect_flow_id = $redirectFlow->id;
                $subscription->save();
            }

            // Store session token in PHP session so we can complete the flow on return
            session(['gc_session_token' => $sessionToken, 'gc_flow' => $flow, 'gc_existing_sub_id' => $existingSubscriptionId]);

            return [
                'gcRedirectUrl' => $redirectFlow->redirect_url,
            ];
        } catch (\Exception $e) {
            Log::error('GoCardless redirect flow creation failed: ' . $e->getMessage());
            throw new ApplicationException('Could not start direct debit setup. Please try again.');
        }
    }

    /**
     * Handle the return from GoCardless Redirect Flow.
     * Completes the flow, retrieves the mandate, and creates a subscription.
     */
    protected function handleGoCardlessReturn(string $redirectFlowId): void
    {
        try {
            $gc = $this->getGoCardlessClient();
            $sessionToken = session('gc_session_token');
            $flow = session('gc_flow', 'subscribe');
            $existingSubId = session('gc_existing_sub_id');

            if (!$sessionToken) {
                Flash::error('Direct debit session expired. Please try again.');
                return;
            }

            // Complete the redirect flow
            $completedFlow = $gc->redirectFlows()->complete($redirectFlowId, [
                'params' => [
                    'session_token' => $sessionToken,
                ],
            ]);

            $mandateId = $completedFlow->links->mandate;
            $customerId = $completedFlow->links->customer;

            // Update the client with GoCardless customer and mandate
            $user = Auth::getUser();
            if (!$user) {
                return;
            }

            $client = Client::where('user_id', $user->id)->first();
            if (!$client) {
                Flash::error(trans('avalanchestudio.avalanchecrm::lang.messages.no_client_profile'));
                return;
            }

            $client->gocardless_customer_id = $customerId;
            $client->gocardless_mandate_id = $mandateId;
            $client->save();

            if ($flow === 'update_payment' && $existingSubId) {
                // Updating payment method on an existing subscription
                $subscription = Subscription::where('client_id', $client->id)->find($existingSubId);

                if (!$subscription) {
                    Flash::error('Could not find the subscription to update.');
                    return;
                }

                // Cancel old GoCardless subscription if exists and create a new one
                if (!empty($subscription->gocardless_subscription_id)) {
                    try {
                        $gc->subscriptions()->cancel($subscription->gocardless_subscription_id);
                    } catch (\Exception $e) {
                        Log::warning('Could not cancel old GC subscription: ' . $e->getMessage());
                    }
                }

                // Create a new GoCardless subscription with the new mandate
                $gcSub = $this->createGoCardlessSubscription($gc, $mandateId, $subscription);

                $subscription->payment_method = 'direct_debit';
                $subscription->gocardless_subscription_id = $gcSub->id;
                $subscription->gocardless_redirect_flow_id = $redirectFlowId;
                $subscription->save();

                Flash::success(trans('avalanchestudio.avalanchecrm::lang.messages.payment_method_updated'));
            } else {
                // New subscription flow
                $subscription = Subscription::where('gocardless_redirect_flow_id', $redirectFlowId)->first();

                if (!$subscription) {
                    Flash::error('Could not find the pending subscription.');
                    return;
                }

                if ($subscription->client_id !== $client->id) {
                    Flash::error('Subscription does not belong to your account.');
                    return;
                }

                // Create the GoCardless subscription
                $gcSub = $this->createGoCardlessSubscription($gc, $mandateId, $subscription);

                $subscription->status = 'active';
                $subscription->gocardless_subscription_id = $gcSub->id;
                $subscription->reference_id = $gcSub->id;
                $subscription->next_billing_date = $this->calculateNextBillingDate($subscription->billing_cycle);
                $subscription->save();

                Flash::success(trans('avalanchestudio.avalanchecrm::lang.messages.payment_successful', ['name' => $subscription->plan_name]));
            }

            // Clear session data
            session()->forget(['gc_session_token', 'gc_flow', 'gc_existing_sub_id']);

        } catch (\Exception $e) {
            Log::error('GoCardless return handling failed: ' . $e->getMessage());
            Flash::error('There was an issue completing your direct debit setup. Please contact support.');
        }
    }

    /**
     * Create a GoCardless subscription using a mandate.
     *
     * @param \GoCardlessPro\Client $gc
     * @param string $mandateId
     * @param Subscription $subscription
     * @return object GoCardless subscription resource
     */
    protected function createGoCardlessSubscription(\GoCardlessPro\Client $gc, string $mandateId, Subscription $subscription)
    {
        $settings = Settings::instance();
        $currencyCode = strtoupper($settings->currency_code ?: 'GBP');

        // Convert billing cycle to GoCardless interval
        $intervalUnit = match ($subscription->billing_cycle) {
            'monthly' => 'monthly',
            'quarterly' => 'monthly',
            'annual' => 'yearly',
            default => 'monthly',
        };

        $interval = match ($subscription->billing_cycle) {
            'monthly' => 1,
            'quarterly' => 3,
            'annual' => 1,
            default => 1,
        };

        // Amount in pence/cents (integer)
        $amountInMinorUnits = (int) round($subscription->amount * 100);

        return $gc->subscriptions()->create([
            'params' => [
                'amount' => $amountInMinorUnits,
                'currency' => $currencyCode,
                'name' => $subscription->plan_name,
                'interval_unit' => $intervalUnit,
                'interval' => $interval,
                'links' => [
                    'mandate' => $mandateId,
                ],
                'metadata' => [
                    'subscription_id' => (string) $subscription->id,
                    'plan_id' => (string) $subscription->plan_id,
                ],
            ],
        ]);
    }

    // =========================================================================
    //  PayPal: Subscription Management via REST API v2
    // =========================================================================

    /**
     * Get a PayPal API access token using client credentials.
     */
    protected function getPayPalAccessToken(): string
    {
        $settings = Settings::instance();

        if (!$settings->paypal_enabled) {
            throw new ApplicationException('PayPal payments are not enabled.');
        }

        $clientId = trim($settings->paypal_client_id ?? '');
        $secret = trim($settings->paypal_secret ?? '');

        if (empty($clientId) || empty($secret)) {
            throw new ApplicationException('PayPal has not been configured. Please contact support.');
        }

        $baseUrl = $this->getPayPalBaseUrl();

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($baseUrl . '/v1/oauth2/token', [
                'auth' => [$clientId, $secret],
                'form_params' => ['grant_type' => 'client_credentials'],
                'headers' => ['Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['access_token'];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            Log::error('PayPal auth failed: ' . $e->getMessage() . ' | Body: ' . $body);
            throw new ApplicationException('PayPal authentication failed. Please check the Client ID and Secret in the Avalanche CRM settings.');
        }
    }

    /**
     * Get the PayPal API base URL based on mode setting.
     */
    protected function getPayPalBaseUrl(): string
    {
        $settings = Settings::instance();
        return ($settings->paypal_mode === 'live')
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Create a PayPal Subscription and return the approval redirect URL.
     *
     * @param Client $client
     * @param SubscriptionPlan $plan
     * @param string $flow  'subscribe' or 'update_payment'
     * @param int|null $existingSubscriptionId
     * @return array
     */
    protected function createPayPalSubscription(Client $client, SubscriptionPlan $plan, string $flow = 'subscribe', ?int $existingSubscriptionId = null): array
    {
        $currentUrl = $this->getCurrentPageUrl();
        $user = $client->user;
        $settings = Settings::instance();

        try {
            $accessToken = $this->getPayPalAccessToken();
            $baseUrl = $this->getPayPalBaseUrl();
            $httpClient = new \GuzzleHttp\Client();

            // If the plan has a PayPal plan ID, create a subscription directly
            if (!empty($plan->paypal_plan_id)) {
                $subscriptionPayload = [
                    'plan_id' => $plan->paypal_plan_id,
                    'application_context' => [
                        'brand_name' => config('app.name', 'Avalanche CRM'),
                        'locale' => 'en-GB',
                        'shipping_preference' => 'NO_SHIPPING',
                        'user_action' => 'SUBSCRIBE_NOW',
                        'return_url' => $currentUrl . '?paypal_success=1&subscription_id={subscriptionId}',
                        'cancel_url' => $currentUrl . '?paypal_cancel=1',
                    ],
                ];

                // Add subscriber info if available
                if ($user) {
                    $subscriptionPayload['subscriber'] = [
                        'name' => [
                            'given_name' => $client->name,
                        ],
                        'email_address' => $user->email ?? $client->email,
                    ];
                }

                $response = $httpClient->post($baseUrl . '/v1/billing/subscriptions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => $subscriptionPayload,
                ]);

                $ppSubscription = json_decode($response->getBody()->getContents(), true);
                $ppSubscriptionId = $ppSubscription['id'];

                // Find the approval URL
                $approvalUrl = null;
                foreach ($ppSubscription['links'] as $link) {
                    if ($link['rel'] === 'approve') {
                        $approvalUrl = $link['href'];
                        break;
                    }
                }

                if (!$approvalUrl) {
                    throw new ApplicationException('Could not get PayPal approval URL.');
                }
            } else {
                // No PayPal Plan ID â€” create a one-off order instead
                $currencyCode = strtoupper($settings->currency_code ?: 'USD');

                $orderPayload = [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'amount' => [
                                'currency_code' => $currencyCode,
                                'value' => number_format($plan->price, 2, '.', ''),
                            ],
                            'description' => 'Subscription: ' . $plan->name,
                        ]
                    ],
                    'application_context' => [
                        'brand_name' => config('app.name', 'Avalanche CRM'),
                        'shipping_preference' => 'NO_SHIPPING',
                        'user_action' => 'PAY_NOW',
                        'return_url' => $currentUrl . '?paypal_success=1&order=1',
                        'cancel_url' => $currentUrl . '?paypal_cancel=1',
                    ],
                ];

                $response = $httpClient->post($baseUrl . '/v2/checkout/orders', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => $orderPayload,
                ]);

                $ppOrder = json_decode($response->getBody()->getContents(), true);
                $ppSubscriptionId = $ppOrder['id']; // Order ID used as reference

                $approvalUrl = null;
                foreach ($ppOrder['links'] as $link) {
                    if ($link['rel'] === 'approve') {
                        $approvalUrl = $link['href'];
                        break;
                    }
                }

                if (!$approvalUrl) {
                    throw new ApplicationException('Could not get PayPal approval URL.');
                }
            }

            // Store a pending subscription
            if ($flow === 'update_payment' && $existingSubscriptionId) {
                $subscription = Subscription::find($existingSubscriptionId);
                if ($subscription) {
                    $subscription->paypal_subscription_id = $ppSubscriptionId;
                    $subscription->save();
                }
                // Store flow info in session
                session(['pp_flow' => 'update_payment', 'pp_existing_sub_id' => $existingSubscriptionId, 'pp_has_plan_id' => !empty($plan->paypal_plan_id)]);
            } else {
                $subscription = new Subscription();
                $subscription->client_id = $client->id;
                $subscription->plan_id = $plan->id;
                $subscription->plan_name = $plan->name;
                $subscription->payment_method = 'paypal';
                $subscription->status = 'pending';
                $subscription->amount = $plan->price;
                $subscription->billing_cycle = $plan->billing_cycle;
                $subscription->paypal_subscription_id = $ppSubscriptionId;
                $subscription->save();

                session(['pp_flow' => 'subscribe', 'pp_existing_sub_id' => null, 'pp_has_plan_id' => !empty($plan->paypal_plan_id)]);
            }

            return [
                'paypalRedirectUrl' => $approvalUrl,
            ];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            Log::error('PayPal subscription creation failed: ' . $e->getMessage() . ' | Body: ' . $body);
            throw new ApplicationException('Could not start PayPal payment. Please try again.');
        } catch (\Exception $e) {
            Log::error('PayPal subscription creation failed: ' . $e->getMessage());
            throw new ApplicationException('Could not start PayPal payment. Please try again.');
        }
    }

    /**
     * Handle the return from PayPal after subscription/order approval.
     */
    protected function handlePayPalReturn(string $subscriptionId): void
    {
        try {
            $user = Auth::getUser();
            if (!$user) {
                return;
            }

            $client = Client::where('user_id', $user->id)->first();
            if (!$client) {
                Flash::error(trans('avalanchestudio.avalanchecrm::lang.messages.no_client_profile'));
                return;
            }

            $flow = session('pp_flow', 'subscribe');
            $existingSubId = session('pp_existing_sub_id');
            $hasPlanId = session('pp_has_plan_id', true);

            $accessToken = $this->getPayPalAccessToken();
            $baseUrl = $this->getPayPalBaseUrl();
            $httpClient = new \GuzzleHttp\Client();

            if ($hasPlanId) {
                // Verify the PayPal subscription status
                $response = $httpClient->get($baseUrl . '/v1/billing/subscriptions/' . $subscriptionId, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Accept' => 'application/json',
                    ],
                ]);

                $ppSub = json_decode($response->getBody()->getContents(), true);
                $ppStatus = $ppSub['status'] ?? '';

                $isApproved = in_array($ppStatus, ['ACTIVE', 'APPROVED']);
            } else {
                // For orders, capture the payment
                $response = $httpClient->post($baseUrl . '/v2/checkout/orders/' . $subscriptionId . '/capture', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                ]);

                $ppOrder = json_decode($response->getBody()->getContents(), true);
                $isApproved = ($ppOrder['status'] ?? '') === 'COMPLETED';
            }

            if ($flow === 'update_payment' && $existingSubId) {
                $subscription = Subscription::where('client_id', $client->id)->find($existingSubId);

                if (!$subscription) {
                    Flash::error('Could not find the subscription to update.');
                    return;
                }

                if ($isApproved) {
                    $subscription->payment_method = 'paypal';
                    $subscription->paypal_subscription_id = $subscriptionId;
                    $subscription->save();

                    Flash::success(trans('avalanchestudio.avalanchecrm::lang.messages.payment_method_updated'));
                } else {
                    Flash::warning(trans('avalanchestudio.avalanchecrm::lang.messages.payment_processing'));
                }
            } else {
                // New subscription flow
                $subscription = Subscription::where('paypal_subscription_id', $subscriptionId)->first();

                if (!$subscription) {
                    Flash::error('Could not find the pending subscription.');
                    return;
                }

                if ($subscription->client_id !== $client->id) {
                    Flash::error('Subscription does not belong to your account.');
                    return;
                }

                if ($isApproved) {
                    $subscription->status = 'active';
                    $subscription->reference_id = $subscriptionId;
                    $subscription->next_billing_date = $this->calculateNextBillingDate($subscription->billing_cycle);
                    $subscription->save();

                    Flash::success(trans('avalanchestudio.avalanchecrm::lang.messages.payment_successful', ['name' => $subscription->plan_name]));
                } else {
                    Flash::warning(trans('avalanchestudio.avalanchecrm::lang.messages.payment_processing'));
                }
            }

            // Clear session data
            session()->forget(['pp_flow', 'pp_existing_sub_id', 'pp_has_plan_id']);

        } catch (\Exception $e) {
            Log::error('PayPal return handling failed: ' . $e->getMessage());
            Flash::error('There was an issue confirming your PayPal payment. Please contact support.');
        }
    }

    /**
     * Cancel a PayPal subscription via API.
     */
    protected function cancelPayPalSubscription(string $ppSubscriptionId): void
    {
        $accessToken = $this->getPayPalAccessToken();
        $baseUrl = $this->getPayPalBaseUrl();
        $httpClient = new \GuzzleHttp\Client();

        $httpClient->post($baseUrl . '/v1/billing/subscriptions/' . $ppSubscriptionId . '/cancel', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'reason' => 'Customer requested cancellation',
            ],
        ]);
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    /**
     * Validate that the chosen payment method is enabled in settings.
     */
    protected function validatePaymentMethod(string $method, Settings $settings): void
    {
        $allowed = [
            'card' => $settings->stripe_enabled,
            'direct_debit' => $settings->gocardless_enabled,
            'paypal' => $settings->paypal_enabled,
        ];

        if (!isset($allowed[$method]) || !$allowed[$method]) {
            throw new ApplicationException('The selected payment method is not available.');
        }
    }

    /**
     * Calculate the next billing date based on cycle.
     */
    protected function calculateNextBillingDate(string $cycle): \Carbon\Carbon
    {
        $now = now();

        return match ($cycle) {
            'monthly' => $now->addMonth(),
            'quarterly' => $now->addMonths(3),
            'annual' => $now->addYear(),
            default => $now->addMonth(),
        };
    }
}
