<?php

namespace AvalancheStudio\AvalancheCRM\Components;

use Winter\User\Facades\Auth;
use Winter\Storm\Support\Facades\Flash;
use Winter\Storm\Support\Facades\Input;
use Winter\Storm\Support\Facades\Validator;
use Winter\Storm\Exception\ValidationException;
use Winter\Storm\Exception\ApplicationException;
use Cms\Classes\ComponentBase;
use AvalancheStudio\AvalancheCRM\Models\Client;

/**
 * Account Component
 *
 * Allows users to manage their account details like address, tel no etc.
 */
class Account extends ComponentBase
{
    /**
     * @var \Winter\User\Models\User The authenticated user.
     */
    public $user;

    /**
     * @var Client|null The associated client profile.
     */
    public $client;

    public function componentDetails(): array
    {
        return [
            'name' => 'Account Details',
            'description' => 'Allows users to manage their profile and account details.',
        ];
    }

    public function defineProperties(): array
    {
        return [];
    }

    public function onRun()
    {
        $this->addCss('/plugins/avalanchestudio/avalanchecrm/assets/css/account_details.css');
        $this->page['themeStyles'] = \AvalancheStudio\AvalancheCRM\Classes\ThemeStyles::render();

        $this->prepareVars();
    }

    public function prepareVars()
    {
        $this->user = $this->page['user'] = Auth::getUser();
        if ($this->user) {
            $this->client = $this->page['client'] = Client::where('user_id', $this->user->id)->first();
        }
    }

    public function onUpdate()
    {
        $user = Auth::getUser();
        if (!$user) {
            throw new ApplicationException(trans('avalanchestudio.avalanchecrm::lang.messages.must_be_logged_in'));
        }

        $data = Input::all();

        $rules = [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ];

        $validation = Validator::make($data, $rules);
        if ($validation->fails()) {
            throw new ValidationException($validation);
        }

        $user->fill($data);
        $user->save();

        // Sync with Client model if it exists
        $client = Client::where('user_id', $user->id)->first();
        if ($client) {
            $client->name = trim($user->name . ' ' . ($user->surname ?? ''));
            $client->email = $user->email;
            $client->phone = $user->phone ?? $client->phone;
            $client->company = $user->company ?? $client->company;

            // Marketing opt-out preference
            $client->marketing_opt_out = (bool) Input::get('marketing_opt_out', false);

            $client->save();
        }

        Flash::success(trans('avalanchestudio.avalanchecrm::lang.messages.account_updated'));

        $this->prepareVars();

        return [
            '#account-details-form' => $this->renderPartial('@form_fields'),
        ];
    }
}
