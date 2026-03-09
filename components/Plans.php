<?php

namespace AvalancheStudio\AvalancheCRM\Components;

use Cms\Classes\ComponentBase;
use AvalancheStudio\AvalancheCRM\Models\SubscriptionPlan;
use AvalancheStudio\AvalancheCRM\Models\Settings;
use Winter\User\Facades\Auth;
use Winter\Storm\Support\Facades\Input;
use Winter\Storm\Support\Facades\Validator;
use Winter\Storm\Exception\ValidationException;
use AvalancheStudio\AvalancheCRM\Components\Subscriptions;

/**
 * Plans Component
 *
 * Displays available subscription plans to new or existing users.
 */
class Plans extends ComponentBase
{
    /**
     * @var \Winter\Storm\Database\Collection Available plans.
     */
    public $plans;

    /**
     * @var Settings CRM settings instance.
     */
    public $settings;

    public function componentDetails(): array
    {
        return [
            'name' => 'Plans',
            'description' => 'Displays available subscription plans for users to choose from.',
        ];
    }

    public function defineProperties(): array
    {
        return [
            'registerPage' => [
                'title' => 'Registration Page',
                'description' => 'The page where users are redirected to register and pay for the selected plan.',
                'type' => 'dropdown',
                'default' => 'login'
            ],
            'buttonText' => [
                'title' => 'Button Text',
                'description' => 'Text to display on the action button',
                'type' => 'string',
                'default' => 'Choose Plan'
            ]
        ];
    }

    public function getRegisterPageOptions()
    {
        return \Cms\Classes\Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    public function onRun()
    {
        $this->addCss('/plugins/avalanchestudio/avalanchecrm/assets/css/subscriptions.css');
        $this->page['themeStyles'] = \AvalancheStudio\AvalancheCRM\Classes\ThemeStyles::render();

        $this->prepareVars();
    }

    protected function prepareVars()
    {
        $this->settings = $this->page['settings'] = Settings::instance();

        $this->plans = $this->page['plans'] = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        $this->page['currencySymbol'] = $this->settings->currency_symbol ?? '$';
        $this->page['currencyCode'] = $this->settings->currency_code ?? 'USD';

        $this->page['stripeEnabled'] = $this->settings->stripe_enabled ?? false;
        $this->page['paypalEnabled'] = $this->settings->paypal_enabled ?? false;
        $this->page['gocardlessEnabled'] = $this->settings->gocardless_enabled ?? false;

        $this->page['user'] = Auth::getUser();
    }

    public function onRegisterAndSubscribe()
    {
        $data = post();

        $user = Auth::getUser();

        if (!$user) {
            $authMode = array_get($data, 'auth_mode', 'register');

            if ($authMode === 'login') {
                $rules = [
                    'login_email' => 'required|email',
                    'login_password' => 'required'
                ];
                $validation = Validator::make($data, $rules);
                if ($validation->fails()) {
                    throw new ValidationException($validation);
                }

                $user = Auth::authenticate([
                    'login' => $data['login_email'],
                    'password' => $data['login_password']
                ], true);
            } else {
                $rules = [
                    'name' => 'required|min:2',
                    'email' => 'required|email|min:6|max:255|unique:users',
                    'password' => 'required|min:8',
                ];

                $validation = Validator::make($data, $rules);
                if ($validation->fails()) {
                    throw new ValidationException($validation);
                }

                // Register and login automatically
                $user = Auth::register([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'password_confirmation' => $data['password']
                ], true);
            }
        }

        $rules = [
            'plan_id' => 'required',
            'payment_method' => 'required'
        ];

        $validation = Validator::make($data, $rules);
        if ($validation->fails()) {
            throw new ValidationException($validation);
        }

        // Delegate subscripton/payment flow to the Subscriptions component
        $subscriptions = new Subscriptions(null, []);
        $subscriptions->init();
        return $subscriptions->onSubscribe();
    }

    public function onLogout()
    {
        Auth::logout();
        return \Illuminate\Support\Facades\Redirect::to(\Illuminate\Support\Facades\Request::url());
    }
}
