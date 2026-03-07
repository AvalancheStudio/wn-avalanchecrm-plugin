<?php

namespace AvalancheStudio\AvalancheCRM\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\BackendMenu;
use AvalancheStudio\AvalancheCRM\Models\Campaign;
use AvalancheStudio\AvalancheCRM\Models\Client;
use Winter\Storm\Support\Facades\Flash;

/**
 * Campaigns Backend Controller
 */
class Campaigns extends Controller
{
    /**
     * @var array Behaviors that are implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    /**
     * @var array Permissions required to view this page.
     */
    protected $requiredPermissions = [
        'avalanchestudio.avalanchecrm.campaigns.manage_all',
    ];

    public function __construct()
    {
        parent::__construct();
        if (!\AvalancheStudio\AvalancheCRM\Models\Settings::instance()->enable_marketing) {
            throw new \Winter\Storm\Exception\ApplicationException('Marketing is currently disabled in CRM settings.');
        }
        BackendMenu::setContext('AvalancheStudio.AvalancheCRM', 'avalanchecrm', 'campaigns');
    }

    /**
     * Campaigns index with marketing stats.
     */
    public function index()
    {
        $this->vars['sentCampaigns'] = Campaign::where('status', 'sent')->count();
        $this->vars['draftCampaigns'] = Campaign::where('status', 'draft')->count();
        $this->vars['scheduledCampaigns'] = Campaign::where('status', 'scheduled')->count();
        $this->vars['totalTemplates'] = \AvalancheStudio\AvalancheCRM\Models\EmailTemplate::count();
        $this->vars['marketableClients'] = Client::marketable()->count();
        $this->vars['optedOutClients'] = Client::where('marketing_opt_out', true)->count();

        $this->asExtension('ListController')->index();
    }

    /**
     * Extend the update form to show the Send Campaign button.
     */
    public function formExtendFields($form)
    {
        if ($form->getContext() !== 'update') {
            return;
        }

        $form->addFields([
            '_send_button' => [
                'type' => 'partial',
                'path' => '$/avalanchestudio/avalanchecrm/controllers/campaigns/_send_button.php',
                'span' => 'full',
                'cssClass' => 'pull-right',
            ],
        ]);
    }

    /**
     * Send a campaign to all opted-in clients.
     */
    public function onSendCampaign()
    {
        $campaignId = post('campaign_id');
        $campaign = Campaign::findOrFail($campaignId);

        if ($campaign->status === 'sent') {
            Flash::warning('This campaign has already been sent.');
            return;
        }

        if ($campaign->status === 'sending') {
            Flash::warning('This campaign is currently being sent.');
            return;
        }

        $eligibleCount = Client::marketable()->count();

        if ($eligibleCount === 0) {
            Flash::warning('No eligible recipients found. All clients may have opted out of marketing emails.');
            return;
        }

        $result = $campaign->sendToClients();

        $message = "Campaign sent! {$result['sent']} delivered";
        if ($result['failed'] > 0) {
            $message .= ", {$result['failed']} failed";
        }
        if ($result['skipped'] > 0) {
            $message .= ", {$result['skipped']} skipped (no email)";
        }
        $message .= '.';

        Flash::success($message);

        return $this->listRefresh();
    }
}
